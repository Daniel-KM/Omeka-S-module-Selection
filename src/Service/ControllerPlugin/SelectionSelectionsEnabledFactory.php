<?php declare(strict_types=1);

namespace Selection\Service\ControllerPlugin;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Selection\Mvc\Controller\Plugin\SelectionSelectionsEnabled;

class SelectionSelectionsEnabledFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new SelectionSelectionsEnabled(
            $services->get('Omeka\Settings\Site')
        );
    }
}
