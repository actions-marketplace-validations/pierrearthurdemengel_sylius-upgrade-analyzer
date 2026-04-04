<?php

declare(strict_types=1);

namespace App\Mailer;

use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;

class OrderMailer
{
    public function __construct(
        private readonly Swift_Mailer $mailer,
    ) {
    }

    public function sendOrderConfirmation(string $to, array $orderData): void
    {
        $message = (new Swift_Message('Order Confirmation'))
            ->setFrom('shop@example.com')
            ->setTo($to)
            ->setBody(
                sprintf('Thank you for your order #%s', $orderData['number']),
                'text/html'
            );

        if (isset($orderData['invoice_path'])) {
            $message->attach(
                Swift_Attachment::fromPath($orderData['invoice_path'])
            );
        }

        $this->mailer->send($message);
    }

    public function sendShippingNotification(string $to, string $trackingNumber): void
    {
        $message = (new Swift_Message('Shipping Notification'))
            ->setFrom('shop@example.com')
            ->setTo($to)
            ->setBody(
                sprintf('Your order has been shipped. Tracking: %s', $trackingNumber),
                'text/html'
            );

        $this->mailer->send($message);
    }
}
