<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as PGetPeriodDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

class Ov
{

    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->procPeriodGet = $procPeriodGet;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("OV calculation is started.");
        /**
         * perform processing
         */
        /* get dependent calculation data */
        list($compressCalc, $ovCalc) = $this->getCalcData();
        $compressCalcId = $compressCalc->getId();
        $ovCalcId = $ovCalc->getId();
        /* get compressed downline & populate it with OV data */
        $dwnl = $this->subCalc->exec($compressCalcId);
        /* save updates into repo */
        $this->updateOv($dwnl);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($ovCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("OV calculation is completed.");
    }

    /**
     * Get data for periods/calculations.
     *
     * @return array [$compressCalc, $ovCalc]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PGetPeriodDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(PGetPeriodDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_OV);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get(PGetPeriodDep::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $ovCalc */
        $ovCalc = $ctx->get(PGetPeriodDep::CTX_OUT_DEP_CALC_DATA);
        $result = [$compressCalc, $ovCalc];
        return $result;
    }

    /**
     * Update downline tree with calculated OV values.
     *
     * @param EBonDwnl[] $dwnl
     */
    private function updateOv($dwnl)
    {
        $entity = new  EBonDwnl();
        /** @var EBonDwnl $one */
        foreach ($dwnl as $one) {
            $ov = $one->getOv();
            $calcId = $one->getCalculationRef();
            $custId = $one->getCustomerRef();
            $entity->setOv($ov);
            $id = [
                EBonDwnl::A_CALC_REF => $calcId,
                EBonDwnl::A_CUST_REF => $custId
            ];
            $this->daoBonDwnl->updateById($id, $entity);
        }
    }
}