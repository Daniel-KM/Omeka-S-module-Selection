<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2023-2026
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Selection\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class SelectionRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Selection\Entity\Selection
     */
    protected $resource;

    public function getControllerName()
    {
        return 'selection';
    }

    public function getJsonLdType()
    {
        return 'o:Selection';
    }

    public function getJsonLd()
    {
        $searchQuery = $this->searchQuery();

        if ($searchQuery) {
            $selectionResources = null;
            $resources = $this->dynamicResources();
            foreach ($this->resources() as $resource) {
                $resources[] = $resource->getReference()->jsonSerialize();
            }
        } else {
            // The selection resources is useful only to get the date.
            // So another way to present it is to merge the date in the resource
            // reference, but it is not standard.
            $selectionResources = [];
            foreach ($this->selectionResources() as $selectionResource) {
                $selectionResources[] = $selectionResource->getReference()->jsonSerialize();
            }
            $resources = [];
            foreach ($this->resources() as $resource) {
                $resources[] = $resource->getReference()->jsonSerialize();
            }
        }

        $modified = $this->modified();

        return [
            'o:owner' => $this->owner()->getReference()->jsonSerialize(),
            'o:is_public' => $this->isPublic(),
            'o:is_validated' => $this->isValidated(),
            'o:is_dynamic' => $this->isDynamic(),
            'o:is_default' => $this->isDefault(),
            'o:label' => $this->label(),
            'o:comment' => $this->comment(),
            'o:search_query' => $searchQuery,
            'o:structure' => $this->structure(),
            'o:selection_resources' => $selectionResources,
            'o:resources' => $resources,
            'o:created' => [
                '@value' => $this->getDateTime($this->created())->jsonSerialize(),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ],
            'o:modified' => $modified
                ? [
                    '@value' => $this->getDateTime($modified)->jsonSerialize(),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ] : null,
        ];
    }

    public function owner(): UserRepresentation
    {
        // The selection is removed when the user is deleted, so there is
        // always a user.
        $adapter = $this->getAdapter('users');
        return $adapter->getRepresentation($this->resource->getOwner());
    }

    public function isPublic(): bool
    {
        return $this->resource->isPublic();
    }

    public function isValidated(): ?bool
    {
        return $this->resource->isValidated();
    }

    public function isPublicAndValidated(): bool
    {
        return $this->resource->isPublic()
            && $this->resource->isValidated();
    }

    public function isDynamic(): bool
    {
        return $this->resource->isDynamic();
    }

    public function isDefault(): bool
    {
        return $this->resource->isDefault();
    }

    public function label(): ?string
    {
        return $this->resource->getLabel();
    }

    /**
     * Alias of label().
     */
    public function title(): ?string
    {
        return $this->resource->getLabel();
    }

    public function displayTitle($default = null): string
    {
        $title = $this->resource->getLabel();
        if ($title !== null && $title !== '') {
            return $title;
        }
        if ($default !== null) {
            return $default;
        }
        $translator = $this->getServiceLocator()
            ->get('MvcTranslator');
        return $translator->translate('[Untitled]');
    }

    public function comment(): ?string
    {
        return $this->resource->getComment();
    }

    /**
     * Alias of comment().
     */
    public function description(): ?string
    {
        return $this->resource->getComment();
    }

    public function searchQuery(): ?string
    {
        return $this->resource->getSearchQuery();
    }

    /**
     * The structure follows the flat format of jstree.
     *
     * @see https://www.jstree.com/docs/json/
     */
    public function structure(): array
    {
        return $this->resource->getStructure() ?? [];
    }

    /**
     * @return SelectionResourceRepresentation[]
     */
    public function selectionResources(): array
    {
        if ($this->isDynamic()) {
            return [];
        }

        $selectionResources = [];
        $selectionResourceAdapter = $this->getAdapter('selection_resources');
        foreach ($this->resource->getSelectionResources() as $selectionResourceEntity) {
            $selectionResources[$selectionResourceEntity->getId()] = $selectionResourceAdapter->getRepresentation($selectionResourceEntity);
        }
        return $selectionResources;
    }

    /**
     * Get list of resources directly without checking each selection element.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     */
    public function resources(): array
    {
        if ($this->isDynamic()) {
            return $this->dynamicResources();
        }

        $resources = [];
        $resourceAdapter = $this->getAdapter('resources');
        /** @var \Selection\Entity\SelectionResource $selectionResourceEntity */
        foreach ($this->resource->getSelectionResources() as $selectionResourceEntity) {
            $resource = $selectionResourceEntity->getResource();
            // Avoid rights issues.
            if ($resource) {
                $resources[$resource->getId()] = $resourceAdapter->getRepresentation($resource);
            }
        }
        return $resources;
    }

    /**
     * Get list of resources belonging to a group or no group.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     *
     * @see \Selection\Controller\Site\TraitSessionController::resourcesForGroupSession()
     */
    public function resourcesForGroup(?string $group): array
    {
        if ($this->isDynamic()) {
            return [];
        }

        $structure = $this->structure();
        if (!$structure) {
            return [];
        }

        $group = (string) $group;

        // Get the selected resources or the remaining ones.
        // TODO Optimize process to prepare only needed resources (api call with ids? But types are various).
        if (strlen($group)) {
            $resourceIds = $structure[$group]['resources'] ?? [];
            return $resourceIds
                ? array_intersect_key($this->resources(), array_flip($resourceIds))
                : [];
        } else {
            $resourceIds = array_merge(...array_column($structure, 'resources'));
            return $resourceIds
                ? array_diff_key($this->resources(), array_flip($resourceIds))
                : $this->resources();
        }
    }

    /**
     * List resources by type directly without check of selection elements.
     */
    public function resourcesByType(): array
    {
        if ($this->isDynamic()) {
            return $this->dynamicResources();
        }

        $classesToNames = [
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Media::class => 'media',
            \DigitalObject\Entity\DigitalObject::class => 'digital_objects',
            \Annotate\Entity\Annotation::class => 'annotations',
        ];

        $resources = [];
        $resourceAdapter = $this->getAdapter('resources');
        /** @var \Selection\Entity\SelectionResource $selectionResourceEntity */
        foreach ($this->resource->getSelectionResources() as $selectionResourceEntity) {
            $resource = $selectionResourceEntity->getResource();
            $resourceName = $classesToNames[$resource->getResourceId()] ?? 'resources';
            $resources[$resourceName][$resource->getId()] = $resourceAdapter->getRepresentation($resource);
        }
        return $resources;
    }

    protected function dynamicResources(): array
    {
        // TODO Determine what to output and set a limit.
        if (!$this->isDynamic()) {
            return [];
        }
        return [];
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?DateTime
    {
        return $this->resource->getModified();
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        if ($siteSlug === null) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $url = $this->getViewHelper('Url');
        return $url(
            'site/selection-id',
            [
                'site-slug' => $siteSlug,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    /**
     * Get pretty link to this selection containing thumbnail and label.
     *
     * The thumbnail is the one of the first resource with an image.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::linkPretty()
     *
     * @param string $thumbnailType Type of thumbnail to show
     * @param string|null $titleDefault See $default param for displayTitle()
     * @param string|null $action Action to link to (see link() and linkRaw())
     * @param array $attributes HTML attributes, key and value
     * @return string
     */
    public function linkPretty(
        string $thumbnailType = 'square',
        ?string $titleDefault = null,
        ?string $action = null,
        array $attributes = []
    ) {
        $escape = $this->getViewHelper('escapeHtml');
        $thumbnail = $this->getViewHelper('thumbnail');

        $linkContent = sprintf(
            '%s<span class="resource-name">%s</span>',
            $thumbnail($this, $thumbnailType),
            $escape($this->label())
        );

        if (empty($attributes['class'])) {
            $attributes['class'] = 'resource-link selection-link';
        } else {
            $attributes['class'] .= ' resource-link selection-link';
        }

        return $this->linkRaw($linkContent, $action, $attributes);
    }

    /**
     * Get the number of resources in this selection.
     *
     * @todo Manage count of dynamic selections.
     */
    public function resourceCount(): int
    {
        return $this->resource->getSelectionResources()->count();
    }

    /**
     * Get total resource of this selection by type.
     */
    public function resourceCountByType(): array
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $classesToNames = [
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Media::class => 'media',
            \DigitalObject\Entity\DigitalObject::class => 'digital_objects',
        ];

        if (class_exists(\Annotate\Entity\Annotation::class, false)) {
            $classesToNames[\Annotate\Entity\Annotation::class] = 'annotations';
        }

        $result = [];
        foreach ($classesToNames as $resourceName) {
            $result[$resourceName] = $api
                ->search($resourceName, [
                    'selection_id' => $this->resource->getId(),
                    'limit' => 0,
                ])->getTotalResults();
        }
        return $result;
    }

    public function thumbnail(): ?AssetRepresentation
    {
        foreach ($this->resource->getSelectionResources() as $selectionResource) {
            $resource = $selectionResource->getResource();
            $representation = $this->getAdapter('resources')
                ->getRepresentation($resource);
            if ($representation) {
                $thumbnail = $representation->thumbnail();
                if ($thumbnail) {
                    return $thumbnail;
                }
            }
        }
        return null;
    }

    public function thumbnailDisplayUrl($type)
    {
        $asset = $this->thumbnail();
        if ($asset) {
            return $asset->assetUrl();
        }
        $media = $this->primaryMedia();
        if ($media) {
            return $media->thumbnailUrl($type);
        }
        return null;
    }

    /**
     * First resource of the selection as a representation, or null.
     */
    public function primaryResource(): ?\Omeka\Api\Representation\AbstractResourceEntityRepresentation
    {
        foreach ($this->resource->getSelectionResources() as $selectionResource) {
            $resource = $selectionResource->getResource();
            if ($resource instanceof \Omeka\Entity\Media) {
                return $this->getAdapter('media')->getRepresentation($resource);
            }
            if (class_exists(\DigitalObject\Entity\DigitalObject::class)
                && $resource instanceof \DigitalObject\Entity\DigitalObject
            ) {
                return $this->getAdapter('digital_objects')->getRepresentation($resource);
            }
            $rep = $this->getAdapter('resources')->getRepresentation($resource);
            if ($rep) {
                return $rep;
            }
        }
        return null;
    }

    public function primaryMedia()
    {
        // Pure delegation: iterate selected resources and return the first
        // non-null primaryMedia(). Each representation knows what to return
        // (Item -> first Media, Media/DigitalObject -> $this, etc.). A filter
        // event lets modules supply a fallback (e.g. DigitalObject linked to an
        // item without native media).
        $eventManager = $this->getEventManager();
        foreach ($this->resources() as $representation) {
            $primary = $representation->primaryMedia();
            if (!$primary) {
                $args = $eventManager->prepareArgs(['resource' => $representation, 'primary' => null]);
                $eventManager->trigger('rep.resource.primary_media', $this, $args);
                $primary = $args['primary'];
            }
            if ($primary) {
                return $primary;
            }
        }
        return null;
    }
}
