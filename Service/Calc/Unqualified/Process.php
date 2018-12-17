<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\GetInactive as QGetInact;

/**
 * Change status for unqualified customers and change downline tree.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Process
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process\A\Calc */
    private $aCalc;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Query\GetInactive */
    private $qGetInact;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Query\GetInactive $qGetInact,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process\A\Calc $aCalc
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->qGetInact = $qGetInact;
        $this->servPeriodGet = $servPeriodGet;
        $this->aCalc = $aCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Unqualified Process' calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        /**
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc
         * @var \Praxigento\BonusBase\Repo\Data\Period $writeOffPeriod
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $processCalc
         */
        list($writeOffCalc, $writeOffPeriod, $processCalc) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $inactStats = $this->getInactivePrev($writeOffCalcId);
        $this->aCalc->exec($inactStats);
        /* mark this calculation complete */
        $calcId = $processCalc->getId();
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Unqualified Process' calculation is completed.");
    }

    /**
     * Get inactivity statistics for previous period.
     *
     * @param int $calcId
     * @return array
     */
    private function getInactivePrev($calcId)
    {
        $result = [];

        $query = $this->qGetInact->build();
        $conn = $query->getConnection();
        $bind = [
            QGetInact::BND_CALC_ID => $calcId
        ];
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            $custId = $one[QGetInact::A_CUST_REF];
            $months = $one[QGetInact::A_MONTHS];
            $result[$custId] = $months;
        }
        return $result;
    }
    /**
     * Get data for periods & calculations.
     *
     * @return array [$writeOffCalc, $writeOffPeriod, $processCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /**
         * Get PV Write Off data - to access plain tree & qualified customers data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Period $writeOffPeriod */
        $writeOffPeriod = $resp->getBasePeriodData();
        /**
         * Create Unqualified Process calculation.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_PROCESS);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $pwWriteOffCalc */
        $processCalc = $resp->getDepCalcData();
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffPeriod, $processCalc];
        return $result;
    }

}