<?php declare(strict_types=1);

namespace Selection\Service\ViewHelper;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Selection\View\Helper\SelectionContainer;

class SelectionContainerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SelectionContainer(
            $services->get('Omeka\ApiManager')
        );
    }
}
