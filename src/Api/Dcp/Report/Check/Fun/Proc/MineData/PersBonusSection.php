<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder as QBGetPeriodCalcs;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer as DCustomer;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus as DPersonalBonus;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus\Item as DItem;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Db\Query\GetItems as QBGetItems;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Action to build "Personal Bonus" section of the DCP's "Check" report.
 */
class PersBonusSection
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Db\Query\GetItems */
    private $qbGetItems;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder */
    private $qbGetPeriodCalcs;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwn;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwn,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder $qbGetPeriodCalcs,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Db\Query\GetItems $qbGetItems
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->repoBonDwn = $repoBonDwn;
        $this->qbGetPeriodCalcs = $qbGetPeriodCalcs;
        $this->qbGetItems = $qbGetItems;
    }

    public function exec($custId, $period): DPersonalBonus
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->getCalcs($dsBegin, $dsEnd);
        $calcPvWriteOff = $calcs[Cfg::CODE_TYPE_CALC_PV_WRITE_OFF];
        $calcCompress = $calcs[Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1];

        $pvOwn = $this->getPv($calcPvWriteOff, $custId);
        $pvCompress = $this->getPv($calcCompress, $custId);
        $items = $this->getItems($calcPvWriteOff, $calcCompress, $custId);

        /* compose result */
        $result = new DPersonalBonus();
        $result->setCompressedVolume($pvCompress);
        $result->setItems($items);
        $result->setOwnVolume($pvOwn);
        /** TODO: calc value or remove attr */
        $result->setPercent(0);
        return $result;
    }

    /**
     * Get calculations IDs by calc type code for given period bounds.
     *
     * @param $dsBegin
     * @param $dsEnd
     * @return array [$calcTypeCode => $calcId]
     */
    private function getCalcs($dsBegin, $dsEnd)
    {
        $query = $this->qbGetPeriodCalcs->build();
        $bind = [
            QBGetPeriodCalcs::BND_DATE_BEGIN => $dsBegin,
            QBGetPeriodCalcs::BND_DATE_END => $dsEnd,
            QBGetPeriodCalcs::BND_STATE => Cfg::CALC_STATE_COMPLETE,
        ];

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            $calcType = $one[QBGetPeriodCalcs::A_CALC_TYPE_CODE];
            $calcId = $one[QBGetPeriodCalcs::A_CALC_ID];
            $result[$calcType] = $calcId;
        }
        return $result;
    }

    /**
     * Get DB data and compose API data.
     *
     * @param $calcPvWriteOff
     * @param $calcCompressPhase1
     * @param $custId
     * @return array
     */
    private function getItems($calcPvWriteOff, $calcCompressPhase1, $custId)
    {
        $query = $this->qbGetItems->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetItems::BND_CALC_ID_COMPRESS_PHASE1 => $calcCompressPhase1,
            QBGetItems::BND_CALC_ID_PV_WRITE_OFF => $calcPvWriteOff,
            QBGetItems::BND_CUST_ID => $custId
        ];
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            /* get DB data */
            $custId = $one[QBGetItems::A_CUST_ID];
            $depth = $one[QBGetItems::A_DEPTH];
            $mlmId = $one[QBGetItems::A_MLM_ID];
            $nameFirst = $one[QBGetItems::A_NAME_FIRST];
            $nameLast = $one[QBGetItems::A_NAME_LAST];
            $pv = $one[QBGetItems::A_PV];

            /* composite values */
            $name = "$nameFirst $nameLast";

            /* compose API data */
            $customer = new DCustomer();
            $customer->setId($custId);
            $customer->setMlmId($mlmId);
            $customer->setName($name);
            $customer->setLevel($depth);
            $item = new DItem();
            $item->setCustomer($customer);
            $item->setVolume($pv);
            /** TODO calculate amount or remove attribute  */
            $item->setAmount(0);

            $result[] = $item;
        }
        return $result;
    }

    /**
     * Get PV (& RankID ???) for given calculation & customer.
     *
     * @param $calcId
     * @param $custId
     * @return float
     */
    private function getPv($calcId, $custId)
    {
        $byCalcId = EBonDwnl::ATTR_CALC_REF . '=' . (int)$calcId;
        $byCustId = EBonDwnl::ATTR_CUST_REF . '=' . (int)$custId;
        $where = "($byCalcId) AND ($byCustId)";
        $rs = $this->repoBonDwn->get($where);
        $row = reset($rs);
        $pv = $row->get(EBonDwnl::ATTR_PV);
//        $rankId = $row->get(EBonDwnl::ATTR_RANK_REF);
//        return [$pv, $rankId];
        return $pv;
    }
}