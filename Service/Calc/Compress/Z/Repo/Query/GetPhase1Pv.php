<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Z\Repo\Query;

use Praxigento\Accounting\Repo\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Data\Operation as EOper;
use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOpers;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Build query to get PV data for phase 1 compression.
 */
class GetPhase1Pv
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /**
     * Tables aliases.
     */
    const AS_ACC = 'acc';
    const AS_LOG = 'log';
    const AS_OPER = 'oper';
    const AS_TRANS = 'trans';
    /**
     * Attributes aliases.
     */
    const A_CUST_ID = EAcc::A_CUST_ID;
    const A_PV = ETrans::A_VALUE;

    /**
     * Bound variables names
     */
    const BND_CALC_ID = 'calcId';

    /** @var  \Praxigento\Accounting\Repo\Dao\Type\Operation */
    private $daoTypeOper;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Accounting\Repo\Dao\Type\Operation $daoTypeOper
    )
    {
        parent::__construct($resource);
        $this->daoTypeOper = $daoTypeOper;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {

        $result = $this->conn->select(); // this is root builder

        /* define tables aliases */
        $asAcc = self::AS_ACC;
        $asLog = self::AS_LOG;
        $asOper = self::AS_OPER;
        $asTrans = self::AS_TRANS;

        /* SELECT FROM prxgt_bon_base_log_opers */
        $tbl = $this->resource->getTableName(ELogOpers::ENTITY_NAME);
        $as = $asLog;
        $cols = [];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_operation */
        $tbl = $this->resource->getTableName(EOper::ENTITY_NAME);
        $as = $asOper;
        $on = $as . '.' . EOper::A_ID . '=' . $asLog . '.' . ELogOpers::A_OPER_ID;
        $cols = [];
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_acc_transaction */
        $tbl = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $as = $asTrans;
        $on = $as . '.' . ETrans::A_OPERATION_ID . '=' . $asOper . '.' . EOper::A_ID;
        $cols = [
            self::A_PV => ETrans::A_VALUE
        ];
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_acc_account */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $on = $as . '.' . EAcc::A_ID . '=' . $asTrans . '.' . ETrans::A_DEBIT_ACC_ID;
        $cols = [
            self::A_CUST_ID => EAcc::A_CUST_ID
        ];
        $result->joinLeft([$as => $tbl], $on, $cols);

        // where
        $operTypeId = (int)$this->getPvWriteOffOperTypeId();
        $whereByCalcId = "($asLog." . ELogOpers::A_CALC_ID . '=:' . self::BND_CALC_ID . ')';
        $whereByOperType = "($asOper." . EOper::A_TYPE_ID . "=$operTypeId)";
        $result->where("$whereByOperType AND $whereByCalcId");

        return $result;
    }

    /**
     * Get operation type id for PV Write Off operation.
     *
     * @return int
     */
    private function getPvWriteOffOperTypeId()
    {
        $result = $this->daoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        return $result;
    }
}