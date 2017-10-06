<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Accounting;

/**
 * Request to get data for DCP Accounting report.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Praxigento\Core\Api\Request\WithCond
{

    /**
     * Reporting period.
     *
     * @return string|null 'YYYYMM'
     */
    public function getPeriod()
    {
        $result = parent::getPeriod();
        return $result;
    }

    /**
     * Customer ID for whom to get report.
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        $result = parent::getCustomerId();
        return $result;
    }

    /**
     * Reporting period.
     *
     * @param string $data 'YYYYMM'
     */
    public function setPeriod($data)
    {
        parent::setPeriod($data);
    }

    /**
     * Customer ID for whom to get report.
     *
     * @param int $data
     */
    public function setCustomerId($data)
    {
        parent::setCustomerId($data);
    }
}