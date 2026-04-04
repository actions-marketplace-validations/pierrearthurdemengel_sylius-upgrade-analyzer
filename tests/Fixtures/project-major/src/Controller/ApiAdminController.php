<?php

declare(strict_types=1);

namespace App\Controller;

class ApiAdminController
{
    public function uploadAvatar(): void
    {
        $endpoint = '/api/v2/admin/avatar-images/';
    }

    public function resetPassword(): void
    {
        $url = '/api/v2/shop/reset-password-requests';
    }

    public function verifyAccount(): void
    {
        $url = '/api/v2/shop/account-verification-requests';
    }

    public function getGatewayConfigs(): void
    {
        $url = '/api/v2/admin/gateway-configs';
    }

    public function getPriceHistoryConfigs(): void
    {
        $url = '/api/v2/admin/channel-price-history-configs';
    }

    public function getShopBillingData(): void
    {
        $url = '/api/v2/admin/shop-billing-datas';
    }

    public function getZoneMembers(): void
    {
        $url = '/api/v2/admin/zone-members';
    }

    public function getOrderItemUnits(): void
    {
        $url = '/api/v2/admin/order-item-units';
    }
}
