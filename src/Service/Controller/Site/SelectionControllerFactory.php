<?php declare(strict_types=1);

namespace Selection\Service\Controller\Site;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Selection\Controller\Site\SelectionController;

class SelectionControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Guest');
        $isGuestActive = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        return new SelectionController(
            $isGuestActive
        );
    }
}
