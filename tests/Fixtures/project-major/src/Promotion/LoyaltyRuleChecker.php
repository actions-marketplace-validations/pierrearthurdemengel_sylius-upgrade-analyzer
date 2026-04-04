<?php

declare(strict_types=1);

namespace App\Promotion;

use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class LoyaltyRuleChecker implements RuleCheckerInterface
{
    public const TYPE = 'loyalty_tier';

    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        $customer = $subject->getCustomer();
        if ($customer === null) {
            return false;
        }

        $requiredTier = $configuration['tier'] ?? 'gold';
        $customerTier = $customer->getLoyaltyTier();

        return $customerTier === $requiredTier;
    }
}
