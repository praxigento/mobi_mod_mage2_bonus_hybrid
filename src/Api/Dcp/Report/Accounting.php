<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder as QBBal;
use Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Data as DRespData;
use Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Data\Balance as DRespBalance;
use Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Data\Trans as DRespTrans;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder as QBAccTrans;
use Praxigento\Core\Tool\IPeriod as HPeriod;

class Accounting
    extends \Praxigento\Core\Api\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\AccountingInterface
{
    /**
     * Parameter names for local customizations of the queries.
     */
    const BND_CUST_ID = 'custId'; // to get asset balances
    const CTX_QUERY_BAL = 'queryBalance';
    /**
     * Name of the local context variables.
     */
    const VAR_CUST_ID = 'custId';
    /** @deprecated remove it if not used */
    const VAR_CUST_PATH = 'path';
    const VAR_DATE_CLOSE = 'dateClose';
    const VAR_DATE_FROM = 'dateTo'; // date before period start
    const VAR_DATE_OPEN = 'dateOpen'; // the last date for period
    const VAR_DATE_TO = 'dateFrom';
    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder */
    private $qbBalance;
    /** @var \Praxigento\Downline\Repo\Entity\Snap */
    private $repoSnap;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder $qbDcpTrans,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder $qbBalance
    )
    {
        parent::__construct($manObj, $qbDcpTrans, $hlpCfg);
        $this->authenticator = $authenticator;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoSnap = $repoSnap;
        $this->qbBalance = $qbBalance;
    }

    protected function authorize(\Praxigento\Core\Data $ctx)
    {
        /* do nothing - in Production Mode current customer's ID is used as root customer ID */
    }

    protected function createQuerySelect(\Praxigento\Core\Data $ctx)
    {
        parent::createQuerySelect($ctx);
        /* add more query builders */
        $query = $this->qbBalance->build();
        $ctx->set(self::CTX_QUERY_BAL, $query);

    }

    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    protected function performQuery(\Praxigento\Core\Data $ctx)
    {
        /* re-assemble result data (there are more than 1 query in operation) */
        $trans = $this->queryTrans($ctx); // this is primary query (sorted, filtered, etc.)

        /* get working vars from context */
        $var = $ctx->get(self::CTX_VARS);
        $custId = $var->get(self::VAR_CUST_ID);
        $dsOpen = $var->get(self::VAR_DATE_OPEN);
        $dsClose = $var->get(self::VAR_DATE_CLOSE);
        /** @var \Magento\Framework\DB\Select $queryBal */
        $queryBal = $ctx->get(self::CTX_QUERY_BAL);
        $bindBal = [
            QBBal::BIND_MAX_DATE => $dsOpen,
            self::BND_CUST_ID => $custId
        ];
        $balOpen = $this->queryBalances($queryBal, $bindBal);
        $bindBal [QBBal::BIND_MAX_DATE] = $dsClose;
        $balClose = $this->queryBalances($queryBal, $bindBal);


        $result = new DRespData();
        $result->setTrans($trans);
        $result->setBalanceOpen($balOpen);
        $result->setBalanceClose($balClose);

        $ctx->set(self::CTX_RESULT, $result);
    }

    protected function populateQuery(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $bind */
        $bind = $ctx->get(self::CTX_BIND);
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* get working vars */
        $custId = $vars->get(self::VAR_CUST_ID);
        $rootPath = $vars->get(self::VAR_CUST_PATH);
        $dateFrom = $vars->get(self::VAR_DATE_FROM);
        $dateTo = $vars->get(self::VAR_DATE_TO);
        $path = $rootPath . $custId . Cfg::DTPS . '%';

        /* bind values for query parameters */
        $bind->set(QBAccTrans::BND_CUST_ID, $custId);
        $bind->set(QBAccTrans::BND_DATE_FROM, $dateFrom);
        $bind->set(QBAccTrans::BND_DATE_TO, $dateTo);

    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract HTTP request parameters */
        $period = $req->getPeriod();
        $custId = $req->getCustomerId();

        /**
         * Define period.
         */
        if (!$period) {
            /* CAUTION: this code will be failed after 2999 year. Please, call to the author in this case. */
            $period = '2999';
        }
        /* apply dates for transactions */
        $dateFrom = $this->hlpPeriod->getTimestampFrom($period, HPeriod::TYPE_MONTH);
        $dateTo = $this->hlpPeriod->getTimestampTo($period, HPeriod::TYPE_MONTH);
        /* dates for balances */
        $dateFirst = $this->hlpPeriod->getPeriodFirstDate($period, HPeriod::TYPE_MONTH);
        $dsOpen = $this->hlpPeriod->getPeriodPrev($dateFirst);
        $dsClose = $this->hlpPeriod->getPeriodLastDate($period, HPeriod::TYPE_MONTH);


        /**
         * Define root customer & path to the root customer on the date.
         */
        $isLiveMode = !$this->hlpCfg->getApiAuthenticationEnabledDevMode();
        if (is_null($custId) || $isLiveMode) {
            $custId = $this->authenticator->getCurrentCustomerId();
        }
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($custId, $period);
        $path = $customerRoot->getPath();


        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $custId);
        $vars->set(self::VAR_CUST_PATH, $path);
        $vars->set(self::VAR_DATE_FROM, $dateFrom);
        $vars->set(self::VAR_DATE_TO, $dateTo);
        $vars->set(self::VAR_DATE_OPEN, $dsOpen);
        $vars->set(self::VAR_DATE_CLOSE, $dsClose);
    }

    /**
     * Perform 'get balance' query (for open/close balance) and compose API compatible result.
     *
     * @param \Magento\Framework\DB\Select $query
     * @param $bind
     * @return DRespBalance[]
     */
    private function queryBalances(\Magento\Framework\DB\Select $query, $bind)
    {
        $result = [];
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            $asset = $one[QBBal::A_ASSET];
            $value = $one[QBBal::A_BALANCE];
            $item = new DRespBalance();
            $item->setAsset($asset);
            $item->setValue($value);
            $result[] = $item;
        }
        return $result;
    }


    private function queryTrans(\Praxigento\Core\Data $ctx)
    {
        $result = [];
        /* get transactions details */
        parent::performQuery($ctx);
        $rs = $ctx->get(self::CTX_RESULT);
        foreach ($rs as $tran) {
            /* parse query entry */
            $asset = $tran[QBAccTrans::A_ASSET];
            $date = $tran[QBAccTrans::A_DATE];
            $details = $tran[QBAccTrans::A_DETAILS];
            $itemId = $tran[QBAccTrans::A_ITEM_ID];
            $otherCustId = $tran[QBAccTrans::A_OTHER_CUST_ID];
            $type = $tran[QBAccTrans::A_TYPE];
            $value = $tran[QBAccTrans::A_VALUE];
            /* compose API entry */
            $item = new DRespTrans();
            $item->setAsset($asset);
            $item->setCustomerId($otherCustId);
            $item->setDate($date);
            $item->setDetails($details);
            $item->setItemId($itemId);
            $item->setType($type);
            $item->setValue($value);
            $result[] = $item;
        }
        return $result;
    }

}