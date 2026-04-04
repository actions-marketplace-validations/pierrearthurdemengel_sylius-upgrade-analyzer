<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\ApiBundle\Command\Account\VerifyCustomerAccount;
use Sylius\Bundle\ApiBundle\Command\Account\ResendVerificationEmail;
use Sylius\Bundle\ApiBundle\CommandHandler\Account\VerifyCustomerAccountHandler;

class VerificationService
{
    public function verifyAccount(string $token): void
    {
        $command = new VerifyCustomerAccount($token);
    }

    public function resendVerification(string $email): void
    {
        $command = new ResendVerificationEmail($email);
    }
}
