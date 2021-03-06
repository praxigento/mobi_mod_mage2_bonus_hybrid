<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Override;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Override as ECfgOvrd;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Override\Calc\Entry as DEntry;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

class Calc
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Cfg\Override */
    private $daoCfgOvrd;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $daoRank;
    /** @var \Praxigento\Core\Api\Helper\Format */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnl,
        \Praxigento\BonusBase\Repo\Dao\Rank $daoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Cfg\Override $daoCfgOvrd,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
        $this->daoDwnl = $daoDwnl;
        $this->daoRank = $daoRank;
        $this->daoCfgOvrd = $daoCfgOvrd;
        $this->daoBonDwnl = $daoBonDwnl;
    }

    /**
     * @param $custId int Customer ID
     * @param $cfgOvr array override bonus configuration parameters for the customer
     * @param $mapGen array generations mapping
     * @param $mapById array customer data by ID mapping
     *
     * @return number
     */
    private function calcOverrideBonusByRank($custId, $cfgOvr, $mapGen, $mapById)
    {
        $result = [];
        if (isset($mapGen[$custId])) {
            $generations = $mapGen[$custId];
            /* this customer has generations in downline */
            /**
             * @var int $gen
             * @var ECfgOvrd $cfgData
             */
            foreach ($cfgOvr as $gen => $cfgData) {
                $percent = $cfgData->getPercent();
                if ($percent > 0) {
                    if (isset($generations[$gen])) {
                        /* this generation exists for the customer */
                        $team = $mapGen[$custId][$gen];
                        foreach ($team as $childId) {
                            /** @var EBonDwnl $childData */
                            $childData = $mapById[$childId];
                            $pv = $childData->getPv();
                            $bonus = $this->hlpFormat->roundBonus($pv * $percent);
                            $this->logger->debug("Customer #$custId has '$pv' PV for '$gen' generation and '$bonus' as override bonus part from child #$childId .");
                            $resultEntry = new DBonus();
                            $resultEntry->setCustomerRef($custId);
                            $resultEntry->setDonatorRef($childId);
                            $resultEntry->setValue($bonus);
                            $result[] = $resultEntry;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function exec($compressCalcId, $scheme)
    {
        $result = [];
        /* collect additional data */
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($compressCalcId);
        $dwnlPlain = $this->daoDwnl->get();
        $cfgOverride = $this->getCfgOverride();
        /* create maps to access data */
        $mapCmprsById = $this->hlpTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapPlainById = $this->hlpTree->mapById($dwnlPlain, ECustomer::A_CUSTOMER_REF);
        $mapTeams = $this->hlpTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        /* populate compressed data with depth & path values */
        $mapByDepthDesc = $this->hlpTree->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_DEPTH);
        /* scan all levels starting from the bottom and collect PV by generations */
        $mapGenerations = $this->mapByGeneration($mapByDepthDesc,
            $mapCmprsById); // [ $custId=>[$genId => $totalPv, ...], ... ]
        /* 'distributor' is the minimal rank in compressed trees */
        $defRankId = $this->daoRank->getIdByCode(Cfg::RANK_DISTRIBUTOR);
        /* scan all customers and calculate bonus values */
        /** @var EBonDwnl $custCompress */
        foreach ($dwnlCompress as $custCompress) {
            $custId = $custCompress->getCustomerRef();
            $rankId = $custCompress->getRankRef();
            /** @var ECustomer $custPlain */
            $custPlain = $mapPlainById[$custId];
            $custRef = $custPlain->getMlmId();
            $custScheme = $this->hlpScheme->getSchemeByCustomer($custPlain);
            if (
                ($rankId != $defRankId) &&
                ($custScheme == $scheme)
            ) {
                /* this is qualified manager */
                $this->logger->debug("Customer #$custId (#$custRef ) from scheme '$custScheme' is qualified to rank #$rankId.");
                if (isset($cfgOverride[$scheme][$rankId])) {
                    $cfgOvrEntry = $cfgOverride[$scheme][$rankId];
                    // calculate bonus value for $custId according rank configuration
                    $bonusData = $this->calcOverrideBonusByRank($custId, $cfgOvrEntry, $mapGenerations, $mapCmprsById);
                    /* ... and add to result set */
                    $entry = new DEntry();
                    $entry->setCustomerRef($custId);
                    $entry->setRankRef($rankId);
                    $entry->setEntries($bonusData);
                    $result[] = $entry;
                } else {
                    /* this rank is not qualified to the bonus */
                }
            }
        }
        unset($mapGenerations);
        unset($mapByDepthDesc);
        unset($mapTreeExp);
        unset($mapTeams);
        unset($mapCmprsById);
        /* convert 2D array with results into plain array */
        $result = $this->plainBonus($result);
        return $result;
    }

    /**
     * @return array [$scheme][$rankId][$gen] => $cfg;
     */
    private function getCfgOverride()
    {
        $result = [];
        $data = $this->daoCfgOvrd->get();
        /** @var ECfgOvrd $one */
        foreach ($data as $one) {
            $scheme = $one->getScheme();
            $rankId = $one->getRankId();
            $gen = $one->getGeneration();
            $result[$scheme][$rankId][$gen] = $one;
        }
        return $result;
    }

    /**
     * Generate map of the customer generations.
     *
     * @param $mapByDepthDesc
     * @param $mapById
     * @param $mapById
     *
     * @return array [$custId=>[$genNum=>[$childId, ...], ...], ...]
     */
    private function mapByGeneration($mapByDepthDesc, $mapById)
    {
        $result = []; // [ $custId=>[$genId => $totalPv, ...], ... ]
        foreach ($mapByDepthDesc as $depth => $ids) {
            foreach ($ids as $custId) {
                /** @var EBonDwnl $entry */
                $entry = $mapById[$custId];
                $path = $entry->getPath();
                $parents = $this->hlpTree->getParentsFromPathReversed($path);
                $level = 0;
                foreach ($parents as $parentId) {
                    $level += 1;
                    if (!isset($result[$parentId])) {
                        $result[$parentId] = [];
                    }
                    if (!isset($result[$parentId][$level])) {
                        $result[$parentId][$level] = [];
                    }
                    $result[$parentId][$level][] = $custId;
                }
            }
        }
        return $result;
    }

    /**
     * Convert 2D array with bonuses into 1D array.
     *
     * @param $bonus
     * @return array
     */
    private function plainBonus($bonus)
    {
        /* prepare data for updates */
        $result = [];
        /** @var DEntry $item */
        foreach ($bonus as $item) {
            $bonusData = $item->getEntries();
            /** @var DBonus $entry */
            foreach ($bonusData as $entry) {
                $bonus = $entry->getValue();
                if ($bonus > Cfg::DEF_ZERO) {
                    $result[] = $entry;
                }
            }
        }
        return $result;
    }

}