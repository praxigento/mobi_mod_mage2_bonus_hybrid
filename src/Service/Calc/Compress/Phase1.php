<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;
use Praxigento\BonusHybrid\Service\Calc\Compress\Phase1\Calc as SubCalc;
use Praxigento\Downline\Repo\Entity\Data\Snap as ESnap;

class Phase1
    implements \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase1
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapValueById as protected;
    }
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder */
    private $qbGetPv;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnapOnDate;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $repoRank;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv */
    private $repoTransPv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv $repoTransPv,
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbGetPv,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnapOnDate,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->repoDwnl = $repoDwnl;
        $this->repoCalc = $repoCalc;
        $this->repoRank = $repoRank;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->repoTransPv = $repoTransPv;
        $this->qbGetPv = $qbGetPv;
        $this->qbSnapOnDate = $qbSnapOnDate;
        $this->procPeriodGet = $procPeriodGet;
        $this->subCalc = $subCalc;
    }

    /**
     * Wrapper for compression sub-process.
     *
     * @param \Praxigento\Downline\Repo\Entity\Data\Customer[] $dwnlCurrent
     * @param array $dwnlSnap see \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder
     * @param array $pv [custId => pv]
     * @param int $calcId
     * @return array [$updates, $pvTransfers]
     */
    private function compress($dwnlCurrent, $dwnlSnap, $pv, $calcId)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SubCalc::CTX_DWNL_CUST, $dwnlCurrent);
        $ctx->set(SubCalc::CTX_DWNL_SNAP, $dwnlSnap);
        $ctx->set(SubCalc::CTX_PV, $pv);
        $ctx->set(SubCalc::CTX_CALC_ID, $calcId);
        $this->subCalc->exec($ctx);
        $updates = $ctx->get(SubCalc::CTX_COMPRESSED);
        $pvTransfers = $ctx->get(SubCalc::CTX_PV_TRANSFERS);
        $result = [$updates, $pvTransfers];
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Phase1 compression is started.");
        /* get dependent calculation data */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $compressPeriod */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        list($writeOffCalc, $compressPeriod, $compressCalc) = $this->getCalcData();
        $compressPeriodId = $compressPeriod->getId();
        $dsBegin = $compressPeriod->getDstampBegin();
        $dsEnd = $compressPeriod->getDstampEnd();
        $writeOffCalcId = $writeOffCalc->getId();
        $compressCalcId = $compressCalc->getId();
        $this->logger->info("Phase1 compression period #$compressPeriodId ($dsBegin-$dsEnd)");
        /* load source data for calculation */
        /* TODO: move source data collection into sub-class */
        $dwnlSnap = $this->getDownlineSnapshot($dsEnd);
        $dwnlCurrent = $this->repoDwnl->get();
        $dataPv = $this->getPv($writeOffCalcId);
        /** @var \Praxigento\Downline\Repo\Entity\Data\Snap[] $updates */
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv[] $pvTransfers */
        list($updates, $pvTransfers) = $this->compress($dwnlCurrent, $dwnlSnap, $dataPv, $compressCalcId);
        /* save compressed downline & PV transfers into DB */
        $this->saveBonusDownline($updates, $compressCalcId);
        $this->savePvTransfers($pvTransfers);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($compressCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Phase1 compression is completed.");
    }

    /**
     * Get data for calculations/periods.
     *
     * @return array $writeOffCalc, $compressPeriod, $compressCalc
     */
    private function getCalcData()
    {
        /* get data for compression & PV write off calculations */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $writeOffCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $compressPeriod */
        $compressPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$writeOffCalc, $compressPeriod, $compressCalc];
        return $result;
    }

    /**
     * Get ID for default rank.
     *
     * @return int
     */
    private function getDefaultRankId()
    {
        $result = $this->repoRank->getIdByCode(Def::RANK_DISTRIBUTOR);
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $dateOn 'YYYYMMDD'
     *
     * @return array|null
     */
    private function getDownlineSnapshot($dateOn)
    {
        /* collect downline data to given date */
        $query = $this->qbSnapOnDate->getSelectQuery();
        $conn = $query->getConnection();
        $bind = [$this->qbSnapOnDate::BIND_ON_DATE => $dateOn];
        $result = $conn->fetchAll($query, $bind);
        return $result;
    }

    /**
     *Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     * @param int $calcId
     * @return array
     */
    private function getPv($calcId)
    {
        $query = $this->qbGetPv->getSelectQuery();
        $conn = $query->getConnection();
        $bind = [QBldGetPv::BIND_CALC_ID => $calcId];
        $data = $conn->fetchAll($query, $bind);
        $result = $this->mapValueById($data, QBldGetPv::A_CUST_ID, QBldGetPv::A_PV);
        return $result;
    }

    /**
     * @param array $snap snap data with PV (see \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1\Calc::populateCompressedSnapWithPv)
     * @param int $calcId
     */
    private function saveBonusDownline($snap, $calcId)
    {
        $rankId = $this->getDefaultRankId();

        foreach ($snap as $one) {
            /* get working vars */
            $custId = $one[ESnap::ATTR_CUSTOMER_ID];
            $depth = $one[ESnap::ATTR_DEPTH];
            $parentId = $one[ESnap::ATTR_PARENT_ID];
            $path = $one[ESnap::ATTR_PATH];
            $pv = $one[EBonDwnl::ATTR_PV];
            /* compose new entity to save */
            $entity = new EBonDwnl();
            $entity->setCalculationRef($calcId);
            $entity->setCustomerRef($custId);
            $entity->setDepth($depth);
            $entity->setOv(0);
            $entity->setParentRef($parentId);
            $entity->setPath($path);
            $entity->setPv($pv);
            $entity->setRankRef($rankId);
            $entity->setTv(0);
            $entity->setUnqMonths(0);
            $this->repoDwnlBon->create($entity);
        }
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv[] $data
     */
    private function savePvTransfers($data)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv $one */
        foreach ($data as $one) {
            $this->repoTransPv->create($one);
        }
    }
}