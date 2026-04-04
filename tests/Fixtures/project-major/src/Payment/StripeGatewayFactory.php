<?php

declare(strict_types=1);

namespace App\Payment;

use Payum\Core\GatewayFactory;
use Payum\Core\Bridge\Spl\ArrayObject;

class StripeGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'stripe_custom',
            'payum.factory_title' => 'Stripe Custom',
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new StripeApi(
                $config['publishable_key'],
                $config['secret_key'],
            );
        };
    }
}
