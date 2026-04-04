<?php

declare(strict_types=1);

namespace App\Controller;

class AvatarController
{
    public function uploadAvatar(): void
    {
        $endpoint = '/api/v2/admin/avatar-images/';
    }

    public function resetPassword(): void
    {
        $url = '/api/v2/shop/reset-password-requests';
    }
}
