<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Cfg;


class Override
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_GENERATION = 'generation';
    const ATTR_PERCENT = 'percent';
    const ATTR_RANK_ID = 'rank_id';
    const ATTR_SCHEME = 'scheme';
    const ENTITY_NAME = 'prxgt_bon_hyb_cfg_override';

    /**
     * @return integer
     */
    public function getGeneration()
    {
        $result = parent::get(self::ATTR_GENERATION);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setGeneration($data)
    {
        parent::set(self::ATTR_GENERATION, $data);
    }

    /**
     * @return integer
     */
    public function getPercent()
    {
        $result = parent::get(self::ATTR_PERCENT);
        return $result;
    }

    /**
     * @param float $data
     */
    public function setPercent($data)
    {
        parent::set(self::ATTR_PERCENT, $data);
    }

    /**
     * @return integer
     */
    public function getRankId()
    {
        $result = parent::get(self::ATTR_RANK_ID);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setRankId($data)
    {
        parent::set(self::ATTR_RANK_ID, $data);
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        $result = parent::get(self::ATTR_SCHEME);
        return $result;
    }

    /**
     * @param string $data
     */
    public function setScheme($data)
    {
        parent::set(self::ATTR_SCHEME, $data);
    }

    public function getPrimaryKeyAttrs()
    {
        return [self::ATTR_RANK_ID, self::ATTR_SCHEME, self::ATTR_GENERATION];

    }
}