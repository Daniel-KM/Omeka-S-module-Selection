<?php declare(strict_types=1);

namespace Selection\Service\Form;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Selection\Form\SiteSettingsFieldset;

class SiteSettingsFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        try {
            $searchConfigs = $services->get('Omeka\ApiManager')->search('search_configs')->getContent();
        } catch (\Exception $e) {
            $searchConfigs = [];
        }

        $valueOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $labelSearchConfig = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->slug());
            $valueOptions[$searchConfig->id()] = $labelSearchConfig;
        }

        $fieldset = new SiteSettingsFieldset(null, $options ?? []);
        return $fieldset
            ->setSearchConfigs($valueOptions)
        ;
    }
}
