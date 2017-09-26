<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Calculate OV on the compressed downline tree.
 */
class Ov
    implements \Praxigento\Core\Service\IProcess
{
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] downline with PV & TV */
    const IN_DWNL = 'downline';
    /** bool 'false' - don't use "Sign Up" bonus values in OV calculation */
    const IN_USE_SIGN_UP = 'useSignUp';
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] updated downline with OV*/
    const OUT_DWNL = 'downline';


    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    private $hlpSignupDebitCust;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust
    )
    {
        $this->logger = $logger;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        /** @var EBonDwnl[] $dwnlBonus */
        $dwnlCompress = $ctx->get(self::IN_DWNL);
        $useSignUp = (bool)$ctx->get(self::IN_DWNL);

        /* define local working data */

        /* create maps to access data */
        $mapById = $this->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapDepth = $this->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_DEPTH);
        $mapTeams = $this->mapByTeams($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        $signupDebitCustomers = [];
        if ($useSignUp) {
            $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        }
        /**
         * Scan downline by level from bottom to top
         */
        $out = [];
        foreach ($mapDepth as $depth => $levelCustomers) {
            $this->logger->debug("Process level #$depth of the downline tree.");
            /* ... then scan customers on each level */
            foreach ($levelCustomers as $custId) {
                /** @var EBonDwnl $entity */
                $entity = $mapById[$custId];
                $ov = $entity->getPv(); // initial OV equals to customer's own PV
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if ($isSignupDebit) {
                    /* add written-off PV if customer was qualified to Sign Up Debit bonus */
                    $ov += Def::SIGNUP_DEBIT_PV;
                }
                if (isset($mapTeams[$custId])) {
                    /* add OV from front team members */
                    $team = $mapTeams[$custId];
                    foreach ($team as $memberId) {
                        /** @var EBonDwnl $member */
                        $member = $out[$memberId];
                        $memberOv = $member->getOv();
                        $ov += $memberOv;
                    }
                }
                $entity->setOv($ov);
                $out[$custId] = $entity;
            }
        }
        unset($mapPv);
        unset($mapTeams);
        unset($mapDepth);

        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL, $out);
        return $result;
    }

}