<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as ASnap;

/**
 * Process to calculate phase1 compression.
 */
class Phase1
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** Calculation ID to reference in compressed PV transfers  */
    const IN_CALC_ID = 'calcId';
    /** Customers downline tree to the end of calculation period */
    const IN_DWNL_SNAP = 'dwnlSnap';
    /** PV by customer id map */
    const IN_PV = 'pv';
    /** Calculation results, data for compression table */
    const OUT_COMPRESSED = 'compressed';
    /** Compressed PV transfers to save in DB */
    const OUT_PV_TRANSFERS = 'pvTransfers';

    /** @var    \Praxigento\Downline\Service\ISnap */
    private $callDwnlSnap;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    private $hlpSignupDebitCust;
    /** @var \Praxigento\Downline\Tool\ITree */
    private $hlpTree;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoCustDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\Downline\Tool\ITree $hlpTree,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Downline\Repo\Entity\Customer $repoCustDwnl,
        \Praxigento\Downline\Service\ISnap $callDwnlSnap
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->hlpTree = $hlpTree;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->repoCustDwnl = $repoCustDwnl;
        $this->callDwnlSnap = $callDwnlSnap;
    }

    /**
     * @param array $compressionData array [$custId=>[$pv, $parentId], ... ] with compression data.
     * @return array
     */
    private function composeTree($compressionData)
    {
        /* prepare request data: convert to [$customer=>parent, ... ] form */
        $converted = [];
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $converted[$custId] = $data[1];
        }
        $req = new \Praxigento\Downline\Service\Snap\Request\ExpandMinimal();
        $req->setTree($converted);
        $resp = $this->callDwnlSnap->expandMinimal($req);
        unset($converted);
        $result = $resp->getSnapData();
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* extract working variables from execution context */
        $calcId = $ctx->get(self::IN_CALC_ID);
        $pv = $ctx->get(self::IN_PV);
        $snap = $ctx->get(self::IN_DWNL_SNAP);

        /* prepare results structures */
        /* array with PV compression transfers results (see \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv) */
        $pvTransfers = [];

        /* compose intermediary working data */
        /** @var \Praxigento\Downline\Repo\Entity\Data\Customer[] $customers */
        $customers = $this->repoCustDwnl->get();

        /* perform action */
        $qLevels = $this->hlpScheme->getQualificationLevels();
        $forcedCustomers = $this->hlpScheme->getForcedQualificationCustomersIds();
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();

        /* prepare intermediary structures for calculation */
        $mapCustomer = $this->mapById($customers, ECustomer::ATTR_CUSTOMER_ID);
        $mapSnap = $this->mapById($snap, ASnap::A_CUST_ID);
        $mapPv = $pv;
        $mapDepth = $this->mapByTreeDepthDesc($snap, ASnap::A_CUST_ID, ASnap::A_DEPTH);
        $mapTeams = $this->mapByTeams($snap, ASnap::A_CUST_ID, ASnap::A_PARENT_ID);

        /* compression itself */
        /* array for compression results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
        $compression = [];
        foreach ($mapDepth as $depth => $levelCustomers) {
            foreach ($levelCustomers as $custId) {
                $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $parentId = $mapSnap[$custId][ASnap::A_PARENT_ID];
                $custData = $mapCustomer[$custId];
                $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
                $level = $qLevels[$scheme]; // qualification level for current customer
                $isForced = in_array($custId, $forcedCustomers);
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if (($pv >= $level) || $isForced || $isSignupDebit) {
                    if (isset($compression[$custId])) {
                        $pvExist = $compression[$custId][0];
                        $pvNew = $pv + $pvExist;
                        $compression[$custId] = [$pvNew, $parentId];
                    } else {
                        $compression[$custId] = [$pv, $parentId];
                    }
                } else {
                    /* move PV up to the closest qualified parent (parent's level is used for qualification) */
                    $path = $mapSnap[$custId][ASnap::A_PATH];
                    $parents = $this->hlpTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach ($parents as $newParentId) {
                        $parentData = $mapCustomer[$newParentId];
                        $parentScheme = $this->hlpScheme->getSchemeByCustomer($parentData);
                        $parentLevel = $qLevels[$parentScheme]; // qualification level for current parent
                        $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        if (
                            ($pvParent >= $parentLevel) ||
                            (in_array($newParentId, $forcedCustomers))
                        ) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    /* add PV to the closest qualified parent */
                    if (
                        !is_null($foundParentId) &&
                        ($pv > 0)
                    ) {
                        if (isset($compression[$foundParentId])) {
                            $pvExist = $compression[$foundParentId][0];
                            $pvNew = $pv + $pvExist;
                            $compression[$foundParentId][0] = $pvNew;
                        } else {
                            $compression[$foundParentId] [0] = $pv;
                        }
                        // $pv PV are transferred from customer #$custId to his qualified parent #$foundParentId
                        $pvTransferItem = new \Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv();
                        $pvTransferItem->setCalcRef($calcId);
                        $pvTransferItem->setCustFromRef($custId);
                        $pvTransferItem->setCustToRef($foundParentId);
                        $pvTransferItem->setPv($pv);
                        $pvTransfers[] = $pvTransferItem;
                    }
                    /* change parent for all siblings of the unqualified customer */
                    if (isset($mapTeams[$custId])) {
                        $team = $mapTeams[$custId];
                        foreach ($team as $memberId) {
                            if (isset($compression[$memberId])) {
                                /* if null set customer own id to indicate root node */
                                $compression[$memberId][1] = is_null($foundParentId) ? $memberId : $foundParentId;
                            }
                        }
                    }
                }
            }
        }
        unset($mapCustomer);
        unset($mapDepth);
        unset($mapTeams);
        /* compose compressed tree */
        $compressedTree = $this->composeTree($compression);
        /* add compressed PV data */
        $compressedTree = $this->populateCompressedSnapWithPv($compressedTree, $compression);

        /* place results to context */
        $ctx->set(self::OUT_COMPRESSED, $compressedTree);
        $ctx->set(self::OUT_PV_TRANSFERS, $pvTransfers);
    }

    /**
     * Populate phase 1 compressed $tree with compressed PV data.
     *
     * @param array $tree compressed tree w/o PV data
     * @param array $compressionData compression data with compressed PV
     * @return array compressed tree with PV data
     */
    private function populateCompressedSnapWithPv($tree, $compressionData)
    {
        $result = $tree;
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $result[$custId][EBonDwnl::ATTR_PV] = $data[0];
        }
        return $result;
    }
}