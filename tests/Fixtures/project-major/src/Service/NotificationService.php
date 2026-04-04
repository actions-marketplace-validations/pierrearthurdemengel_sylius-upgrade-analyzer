<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Bundle\AdminBundle\Controller\NotificationController;
use Sylius\Bundle\CoreBundle\Templating\Helper\PriceHelper;
use Sylius\Bundle\MoneyBundle\Templating\Helper\FormatMoneyHelper;
use Sylius\Bundle\UiBundle\Console\Command\DebugTemplateEventCommand;
use Sylius\Bundle\UiBundle\Storage\FilterStorage;
use Sylius\Bundle\UiBundle\DataCollector\TemplateBlockDataCollector;
use Sylius\Bundle\UiBundle\Twig\TestHtmlAttributeExtension;

class NotificationService
{
    public function notify(): void
    {
    }
}
