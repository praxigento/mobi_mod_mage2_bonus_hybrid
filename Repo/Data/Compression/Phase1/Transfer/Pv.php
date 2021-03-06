<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer;

/**
 * PV transfers between customers during phase I compression.
 */
class Pv
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_CALC_REF = 'calc_ref';
    const A_CUST_FROM_REF = 'cust_from_ref';
    const A_CUST_TO_REF = 'cust_to_ref';
    const A_PV = 'pv';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_ph1_trn_pv';

    public function getCalcRef()
    {
        $result = parent::get(self::A_CALC_REF);
        return $result;
    }

    public function getCustFrom()
    {
        $result = parent::get(self::A_CUST_FROM_REF);
        return $result;
    }

    public function getCustToRef()
    {
        $result = parent::get(self::A_CUST_TO_REF);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        return [self::A_CALC_REF, self::A_CUST_FROM_REF, self::A_CUST_TO_REF];
    }

    public function getPv()
    {
        $result = parent::get(self::A_PV);
        return $result;
    }

    public function setCalcRef($data)
    {
        parent::set(self::A_CALC_REF, $data);
    }

    public function setCustFromRef($data)
    {
        parent::set(self::A_CUST_FROM_REF, $data);
    }

    public function setCustToRef($data)
    {
        parent::set(self::A_CUST_TO_REF, $data);
    }

    public function setPv($data)
    {
        parent::set(self::A_PV, $data);
    }

}