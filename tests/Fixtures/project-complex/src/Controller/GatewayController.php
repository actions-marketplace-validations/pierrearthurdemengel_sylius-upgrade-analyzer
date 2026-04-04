<?php

declare(strict_types=1);

namespace App\Controller;

class GatewayController
{
    public function listGateways(): void
    {
        $url = '/api/v2/admin/gateway-configs';
    }

    public function getZoneMembers(): void
    {
        $url = '/api/v2/admin/zone-members';
    }
}
