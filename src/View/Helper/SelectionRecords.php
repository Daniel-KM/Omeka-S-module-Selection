<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Selection\Api\Representation\SelectionRepresentation;
use Selection\Api\Representation\SelectionResourceRepresentation;

class SelectionRecords extends AbstractHelper
{
    /**
     * Format all resources to be stored or displayed.
     *
     * @todo Use this helper only for container.
     */
    public function __invoke(?SelectionRepresentation $selection, ?array $selectionResources = null, bool $isSelected = false): array
    {
        static $siteSlug;
        static $defaultLang;
        static $headingTerm;
        static $bodyTerm;
        static $url;
        static $user;

        if (!$selection && !$selectionResources) {
            return [];
        }

        /** @var \Selection\Api\Representation\SelectionResourceRepresentation[] $selectionResources */
        $selectionResources ??= $selection->selectionResources();
        if (!$selectionResources) {
            return [];
        }

        if ($siteSlug === null) {
            $view = $this->getView();
            $plugins = $view->getHelperPluginManager();
            $siteSlug = $plugins->get('currentSite')()->slug();
            $lang = $plugins->get('lang')();
            $siteSetting = $plugins->get('siteSetting');
            $filterLocale = (bool) $siteSetting('filter_locale_values');
            $headingTerm = $siteSetting('browse_heading_property_term');
            $bodyTerm = $siteSetting('browse_body_property_term');
            $defaultLang = $filterLocale ? [$lang, ''] : null;
            $url = $plugins->get('url');
            $user = $plugins->get('identity')();
        }

        if (!$user) {
            return [];
        }

         $records = [];

        foreach ($selectionResources as $selectionResource) {
            $resource = $selectionResource instanceof SelectionResourceRepresentation ? $selectionResource->resource() : $selectionResource->resource;
            if (!$resource) {
                continue;
            }
            $selection = $selectionResource instanceof SelectionResourceRepresentation ? $selectionResource->selection() : $selectionResource->selection;
            $selectionId = $selection ? $selection->id() : 0;
            $resourceId = $resource->id();
            // String is required to avoid error in container when the title is
            // a resource.
            $title = (string) $resource->displayTitle(null, $defaultLang);
            $description = (string) $resource->displayDescription(null, $defaultLang);
            $heading = $headingTerm ? (string) $resource->value($headingTerm, ['default' => $title]) : $title;
            $body = $bodyTerm ? (string) $resource->value($bodyTerm, ['default' => $description]) : $description;
            $records[$selectionId][$resourceId] = [
                'id' => $resourceId,
                'type' => $resource->getControllerName(),
                'resource_name' => $resource->resourceName(),
                'selection_id' => $selectionId,
                'url' => $resource->siteUrl($siteSlug, true),
                'url_remove' => $selectionId
                    ? $url('site/selection-id', ['site-slug' => $siteSlug, 'action' => 'exclude', 'id' => $selectionId], ['query' => ['id' => $resourceId]])
                    : $url('site/selection', ['site-slug' => $siteSlug, 'action' => 'exclude'], ['query' => ['id' => $resourceId]]),
                'title' => $title,
                'description' => $description,
                'heading' => $heading,
                'body' => $body,
                'value' => $isSelected ? 'selected' : 'unselected',
            ];
        }

        return $records;
    }
}
