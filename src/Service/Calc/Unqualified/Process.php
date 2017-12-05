<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as SPeriodGetDep;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Change status for unqualified customers and change downline tree.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Process
    implements \Praxigento\Core\Service\IProcess
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process\Calc */
    private $rouCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process\Calc $rouCalc
    )
    {
        $this->logger = $logger;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoCalc = $repoCalc;
        $this->procPeriodGet = $procPeriodGet;
        $this->rouCalc = $rouCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Unqualified Process' calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($writeOffCalc, $collectCalc, $processCalc) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $treePlain = $this->repoBonDwnl->getByCalcId($writeOffCalcId);
        $this->rouCalc->exec($treePlain);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Unqualified Process' calculation is completed.");
    }

    /**
     * Get data for periods & calculations.
     *
     * @return array [$writeOffCalc, $collectCalc, $processCalc]
     */
    private function getCalcData()
    {
        /**
         * Get PV Write Off data - to access plain tree & qualified customers data.
         */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc */
        $writeOffCalc = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        $collectCalc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        /**
         * Create Unqualified Process calculation.
         */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_UNQUALIFIED_PROCESS);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $pwWriteOffCalc */
        $processCalc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $collectCalc, $processCalc];
        return $result;
    }

}