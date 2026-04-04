<?php

declare(strict_types=1);

namespace App\Payment;

use Payum\Core\GatewayFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactoryInterface;

class PaypalGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'paypal_custom',
            'payum.factory_title' => 'PayPal Custom',
        ]);
    }
}
