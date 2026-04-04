<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Component\Promotion\Checker\Rule\ItemTotalRuleChecker;
use Sylius\Bundle\PayumBundle\Validator\GatewayFactoryExistsValidator;

class PromotionService
{
    public function __construct(
        private readonly ItemTotalRuleChecker $checker,
    ) {
    }

    public function checkPromotion(array $data): bool
    {
        return $this->checker->isEligible($data);
    }
}
