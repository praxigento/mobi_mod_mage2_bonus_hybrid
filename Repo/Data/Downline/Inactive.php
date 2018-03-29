<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Downline;

/**
 * Customers inactivity for downline trees.
 */
class Inactive
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    /* names of the entity attributes (table columns) */
    const A_INACT_MONTHS = 'inact_months';
    const A_TREE_ENTRY_REF = 'tree_entry_ref';

    /* entity (table) name */
    const ENTITY_NAME = 'prxgt_bon_hyb_dwnl_inact';

    public function getInactMonths()
    {
        $result = parent::get(self::A_INACT_MONTHS);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        return [self::A_TREE_ENTRY_REF];
    }

    public function getTreeEntryRef()
    {
        $result = parent::get(self::A_TREE_ENTRY_REF);
        return $result;
    }

    public function setInactMonths($data)
    {
        parent::set(self::A_INACT_MONTHS, $data);
    }

    public function setTreeEntryRef($data)
    {
        parent::set(self::A_TREE_ENTRY_REF, $data);
    }

}