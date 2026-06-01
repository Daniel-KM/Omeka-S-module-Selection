<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Selection\Api\Representation\SelectionRepresentation;
use Selection\Api\Representation\SelectionResourceRepresentation;

class SelectionRecords extends AbstractHelper
{
    /**
     * Format all resources to be stored or displayed (for user or anonymous).
     *
     * This method works for anonymous local container and for db selections.
     *
     * @todo Do not returns records but resources (except for anonymous local records, so separate helpers?).
     */
    public function __invoke(?SelectionRepresentation $selection, ?array $selectionResources = null, bool $isSelected = false): array
    {
        static $siteSlug;
        static $defaultLang;
        static $headingTerm;
        static $bodyTerm;
        static $url;
        static $user;
        static $canViewAll;

        if (!$selection && !$selectionResources) {
            return [];
        }

        $explicitResources = $selectionResources !== null;

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
            $canViewAll = $user && $view->userIsAllowed(\Selection\Entity\Selection::class, 'view-all');
        }

        // Locally stored selection for anonymous: visible only to anonymous.
        // When $selectionResources is provided explicitly (e.g. by the
        // SelectionContainer helper for a logged-in user), the call is valid
        // and must not be short-circuited.
        if (!$selection && $user && !$explicitResources) {
            return [];
        }

        // Visibility rules for db selections:
        // - Admins: can view all.
        // - Anonymous: only public and validated.
        // - Authenticated non-admin: own or public+validated.
        if ($selection && !$canViewAll) {
            $isPublicAndValidated = $selection->isPublicAndValidated();
            if ($user) {
                $isOwner = $selection->owner()
                    && $selection->owner()->id() === $user->getId();
                if (!$isOwner && !$isPublicAndValidated) {
                    return [];
                }
            } else {
                if (!$isPublicAndValidated) {
                    return [];
                }
            }
        }

         $records = [];

        foreach ($selectionResources as $selectionResource) {
            $resource = $selectionResource instanceof SelectionResourceRepresentation
                ? $selectionResource->resource()
                : $selectionResource->resource;
            if (!$resource) {
                continue;
            }

            $sel = $selectionResource instanceof SelectionResourceRepresentation
                ? $selectionResource->selection()
                : $selectionResource->selection;

            // For local selections, there is no db id, so use 0.
            $selectionId = $sel ? $sel->id() : ($selection ? $selection->id() : 0);
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
