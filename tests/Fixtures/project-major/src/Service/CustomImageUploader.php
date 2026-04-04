<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Component\Core\Uploader\ImageUploader;

class CustomImageUploader extends ImageUploader
{
    public function upload(string $path): void
    {
        // Custom upload logic
    }
}
