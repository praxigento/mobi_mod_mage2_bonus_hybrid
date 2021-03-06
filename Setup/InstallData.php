<?php
/**
 * Populate DB schema with module's initial data
 * .
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\Accounting\Repo\Data\Type\Asset as TypeAsset;
use Praxigento\Accounting\Repo\Data\Type\Operation as TypeOperation;
use Praxigento\BonusBase\Repo\Data\Type\Calc as TypeCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class InstallData extends \Praxigento\Core\App\Setup\Data\Base
{
    protected function _setup()
    {
        $this->addAccountingAssetsTypes();
        $this->addAccountingOperationsTypes();
        $this->addBonusCalculationsTypes();
    }

    private function addAccountingAssetsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeAsset::ENTITY_NAME),
            [
                TypeAsset::A_CODE,
                TypeAsset::A_NOTE,
                TypeAsset::A_IS_TRANSFERABLE
            ], [
                [
                    Cfg::CODE_TYPE_ASSET_BONUS,
                    'Asset to calculate bonus. This asset is aggregated and transferred to WALLET as one sum.',
                    false
                ]
            ]
        );
    }

    private function addAccountingOperationsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeOperation::ENTITY_NAME),
            [TypeOperation::A_CODE, TypeOperation::A_NOTE],
            [
                [Cfg::CODE_TYPE_OPER_BONUS_AGGREGATE, 'Aggregate all bonus payments in one check.'],
                [Cfg::CODE_TYPE_OPER_BONUS_COURTESY, 'Courtesy bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_INFINITY, 'Infinity bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_OVERRIDE, 'Override bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_PERSONAL, 'Personal bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_CREDIT, 'Sign Up Bonus Credit operation  (EU only).'],
                [Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_DEBIT, 'Sign Up PV Debit operation (EU only).'],
                [Cfg::CODE_TYPE_OPER_BONUS_TEAM, 'Team bonus.'],
                [Cfg::CODE_TYPE_OPER_PV_FORWARD, 'PV transfer from one not closed period to other period in the future for the same customer.'],
                [Cfg::CODE_TYPE_OPER_PV_WRITE_OFF, 'PV write off in the end of the bonus calculation period.'],
            ]
        );
    }

    private function addBonusCalculationsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeCalc::ENTITY_NAME),
            [TypeCalc::A_CODE, TypeCalc::A_NOTE],
            [
                [Cfg::CODE_TYPE_CALC_BONUS_AGGREGATE, 'Aggregate all bonus payments in one check.'],
                [Cfg::CODE_TYPE_CALC_BONUS_COURTESY, 'Courtesy bonus calculation.'],
                [Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF, 'Infinity bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU, 'Infinity bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF, 'Override bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU, 'Override bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_PERSONAL, 'Personal bonus calculation.'],
                [Cfg::CODE_TYPE_CALC_BONUS_QUICK_START, 'Quick Start (EU only).'],
                [Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_CREDIT, 'Sign Up Bonus Credit (EU only).'],
                [Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT, 'Sign Up Volume Debit (EU only).'],
                [Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF, 'Team bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU, 'Team bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1, 'Compression calculation for Personal, Team & Courtesy bonuses).'],
                [Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF, 'Compression calculation for Override & Infinity bonuses (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU, 'Compression calculation for Override & Infinity bonuses (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_FORECAST_PHASE1, 'Daily forecast calculation (phase1 compression).'],
                [Cfg::CODE_TYPE_CALC_FORECAST_PLAIN, 'Daily forecast calculation (plain tree).'],
                [Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT, 'Inactive customers stats collection.'],
                [Cfg::CODE_TYPE_CALC_INACTIVE_PROCESS, 'Inactive customers stats processing.'],
                [Cfg::CODE_TYPE_CALC_PV_WRITE_OFF, 'PV write off calculation.'],
                [Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT, 'Unqualified customers stats collection.'],
                [Cfg::CODE_TYPE_CALC_UNQUALIFIED_PROCESS, 'Unqualified customers stats processing.'],
                [Cfg::CODE_TYPE_CALC_VALUE_OV, 'Organizational Volumes calculation.'],
                [Cfg::CODE_TYPE_CALC_VALUE_TV, 'Team Volumes calculation.']
            ]
        );
    }

}
