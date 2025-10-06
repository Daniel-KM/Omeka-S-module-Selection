<?php declare(strict_types=1);

namespace Selection\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Selection\View\Helper\SelectionReset;

class SelectionResetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Guest');
        $isGuestActive = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        return new SelectionReset(
            $isGuestActive
        );
    }
}
