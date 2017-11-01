<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;

/**
 * Process step to authorize process further completion.
 */
class Authorize
{

    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator
    )
    {
        $this->authenticator = $authenticator;
    }

    public function exec(AContext $ctx): AContext
    {
        /* if current instance is active */
        if ($ctx->state == AContext::DEF_STATE_ACTIVE) {

            /* get step's local data from the context */
            $customerId = $ctx->getCustomerId();

            /* step's activity */
            $customerId = $this->authenticator->getCurrentCustomerId($customerId);

            /* put step's result data back into the context */
            $ctx->setCustomerId($customerId);
        }
        return $ctx;
    }
}