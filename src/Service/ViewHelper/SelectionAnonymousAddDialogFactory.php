<?php declare(strict_types=1);

namespace Selection\Service\ViewHelper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Selection\View\Helper\SelectionAnonymousAddDialog;

class SelectionAnonymousAddDialogFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new SelectionAnonymousAddDialog(
            $services->get('Omeka\Settings\Site')
        );
    }
}
