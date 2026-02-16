<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2020-2026
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

namespace Selection\Api\Adapter;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;
use Selection\Entity\Selection;
use Selection\Entity\SelectionResource;

class SelectionAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'label' => 'label',
        'owner_id' => 'owner',
        'is_public' => 'isPublic',
        'is_validated' => 'isValidated',
        'is_dynamic' => 'isDynamic',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'owner' => 'owner',
        'is_public' => 'isPublic',
        'is_validated' => 'isValidated',
        'is_dynamic' => 'isDynamic',
        'label' => 'label',
        'comment' => 'comment',
        'search_query' => 'searchQuery',
        'structure' => 'structure',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'selections';
    }

    public function getRepresentationClass()
    {
        return \Selection\Api\Representation\SelectionRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Selection\Entity\Selection::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['owner_id']) && is_numeric($query['owner_id'])) {
            $userAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    'omeka_root.owner',
                    $userAlias
                )
                ->andWhere($expr->eq(
                    "$userAlias.id",
                    $this->createNamedParameter($qb, $query['owner_id'])
                ));
        }

        if (isset($query['label'])) {
            $qb->andWhere($expr->eq(
                "omeka_root.label",
                $this->createNamedParameter($qb, $query['label'])
            ));
        }

        if (isset($query['is_public'])
            && (is_numeric($query['is_public']) || is_bool($query['is_public']))
        ) {
            $qb->andWhere($expr->eq(
                'omeka_root.isPublic',
                $this->createNamedParameter($qb, (bool) $query['is_public'])
            ));
        }

        if (isset($query['is_validated'])
            && (is_numeric($query['is_validated']) || is_bool($query['is_validated']))
        ) {
            $qb->andWhere($expr->eq(
                'omeka_root.isValidated',
                $this->createNamedParameter($qb, (bool) $query['is_validated'])
            ));
        }

        if (isset($query['is_dynamic'])
            && (is_numeric($query['is_dynamic']) || is_bool($query['is_dynamic']))
        ) {
            $qb->andWhere($expr->eq(
                'omeka_root.isDynamic',
                $this->createNamedParameter($qb, (bool) $query['is_dynamic'])
            ));
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Selection\Entity\Selection $entity */

        // Check if current user can validate.
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $canValidate = $acl->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate');

        $this->hydrateOwner($request, $entity);

        // Store previous state for validation check.
        $wasPublic = $entity->isPublic();
        $wasLabel = $entity->getLabel();
        $wasValidated = $entity->isValidated();
        $wasResourceIds = array_map(
            fn ($sr) => $sr->getResource()->getId(),
            $entity->getSelectionResources()->toArray()
        );
        sort($wasResourceIds);

        if ($this->shouldHydrate($request, 'o:is_public')) {
            // Unlike resources, the selections are always private by default.
            $entity->setIsPublic($request->getValue('o:is_public', false));
        }

        if ($this->shouldHydrate($request, 'o:label')) {
            $entity->setLabel(trim((string) $request->getValue('o:label')));
        }

        if ($this->shouldHydrate($request, 'o:comment')) {
            $entity->setComment($request->getValue('o:comment'));
        }

        // The query is updatable: if not, check it at another layer.
        if ($this->shouldHydrate($request, 'o:search_query')) {
            $searchQuery = $this->cleanSearchQuery($request->getValue('o:search_query'));
            $entity->setSearchQuery($searchQuery);
        }

        $hasSearchQuery = $entity->getSearchQuery();
        if ($hasSearchQuery) {
            $entity->setIsDynamic(true);
            $entity->setStructure(null);
            $this->removeSelectionResources($entity);
        } else {
            $entity->setIsDynamic(false);
            if ($this->shouldHydrate($request, 'o:structure')) {
                $entity->setStructure($request->getValue('o:structure') ?: null);
            }
            $this->hydrateSelectionResources($request, $entity);
        }

        // Compare old and new state to determine if revalidation is needed.
        $isPublic = $entity->isPublic();
        $labelChanged = $wasLabel !== $entity->getLabel();
        $publicChanged = $wasPublic !== $isPublic;

        $newResourceIds = array_map(
            fn ($sr) => $sr->getResource()->getId(),
            $entity->getSelectionResources()->toArray()
        );
        sort($newResourceIds);
        $resourcesModified = $wasResourceIds !== $newResourceIds;

        $needsRevalidation = $publicChanged || $labelChanged || $resourcesModified;

        // Update validation status based on changes.
        if ($this->shouldHydrate($request, 'o:is_validated')) {
            $entity->setisValidated($isPublic ? $request->getValue('o:is_validated') : null);
        } elseif ($needsRevalidation) {
            if ($canValidate && $isPublic) {
                // Users with moderation rights: auto-validate public selections.
                $entity->setisValidated(true);
            } elseif ($wasValidated !== null) {
                // Regular users: reset validation to undetermined if modified.
                $entity->setisValidated(null);
            }
        }

        // Unlike core, don't set modified date the first time.
        // $this->updateTimestamps($request, $entity);
        if (Request::CREATE === $request->getOperation()) {
            $entity->setCreated(new DateTime('now'));
        } else {
            $entity->setModified(new DateTime('now'));
        }
    }

    public function validateEntity(
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $owner = $entity->getOwner();
        if (!$owner) {
            $errorStore->addError('o:owner', 'A selection must have an owner.'); // @translate
        }
        // The label can't be empty for the default selection.
        $label = trim((string) $entity->getLabel());
        if (!strlen($label)) {
            $errorStore->addError('o:label', 'A selection must have a label.'); // @translate
        } elseif (!$this->isUnique($entity, ['owner' => $owner, 'label' => $label])) {
            $errorStore->addError('o:label', 'The label is already taken by the owner.'); // @translate
        }
        $searchQuery = trim((string) $entity->getSearchQuery());
        if ($searchQuery && $entity->getSelectionResources()->count()) {
            $errorStore->addError('o:search_query', 'The selection cannot have selection resources when a query is set.'); // @translate
        }
    }

    protected function removeSelectionResources(Selection $selection): void
    {
        $entityManager = $this->getEntityManager();
        foreach ($selection->getSelectionResources() as $selectionResource) {
            $entityManager->remove($selectionResource);
        }
        if ($selection->getId()) {
            $entityManager->refresh($selection);
        }
    }

    protected function hydrateSelectionResources(Request $request, Selection $selection): void
    {
        $owner = $selection->getOwner();
        if (!$owner) {
            return;
        }

        if ($selection->isDynamic()) {
            $this->removeSelectionResources($selection);
            return;
        }

        $oResources = $request->getValue('o:resources', null);
        $resources = $request->getValue('resources', null);
        if ($oResources === null && $resources === null) {
            return;
        }

        $default = [
            'replace' => null,
            'append' => [],
            'remove' => [],
            'toggle' => [],
        ];
        $resources = is_array($resources) ? $resources + $default : $default;

        foreach ($resources as $key => $resource) {
            if (is_numeric($key)) {
                if (is_numeric($resource)) {
                    if ($resources['replace'] === null) {
                        $resources['replace'] = [(int) $resource];
                    } else {
                        $resources['replace'][] = (int) $resource;
                    }
                }
                unset($resources[$key]);
            }
        }
        $resources = array_intersect_key($resources, $default);

        if (is_array($oResources)) {
            if ($resources['replace'] === null) {
                $resources['replace'] = [];
            }
            foreach ($oResources as $resource) {
                // "o:resources" should not manage numeric resources, else it
                // will be a duplicate of "resources".
                if (is_object($resource)) {
                    if ($resource instanceof AbstractResourceEntityRepresentation) {
                        $resources['replace'][] = (int) $resource->id();
                    } elseif ($resource instanceof Resource) {
                        $resources['replace'][] = (int) $resource->getId();
                    }
                } elseif (is_array($resource) && !empty($resource['o:id'])) {
                    $resources['replace'][] = (int) $resource['o:id'];
                }
            }
        }

        // Convert each sub-list into numeric id.
        foreach ($resources as &$list) {
            if (!is_array($list)) {
                if ($list !== null) {
                    $list = [];
                }
                continue;
            }
            foreach ($list as $key => &$resource) {
                if (is_numeric($resource)) {
                    $resource = (int) $resource;
                } elseif (is_object($resource)) {
                    if ($resource instanceof AbstractResourceEntityRepresentation) {
                        $resource = (int) $resource->id();
                    } elseif ($resource instanceof Resource) {
                        $resource = (int) $resource->getId();
                    }
                } elseif (is_array($resource) && !empty($resource['o:id'])) {
                    $resource = (int) $resource['o:id'];
                } else {
                    $resource = null;
                }
                if (empty($resource)) {
                    unset($list[$key]);
                }
            }
            unset($resource);
        }
        unset($list);

        // Get the current list of resource ids in order to update it partially.
        // Get only resource ids to simplify checks, not the useless ids of the
        // selection resources.
        $resourceIds = array_map(fn ($v) => (int) $v->getId(), $selection->getResources()->toArray());

        if (is_array($resources['replace'])) {
            $resourceIds = array_unique(array_filter(array_map('intval', array_filter($resources['replace'], 'is_numeric'))));
        }
        if (is_array($resources['append'])) {
            $resourceIds = array_merge(
                $resourceIds,
                array_unique(array_filter(array_map('intval', array_filter($resources['append'], 'is_numeric'))))
            );
        }
        if (is_array($resources['remove'])) {
            $resourceIds = array_diff(
                $resourceIds,
                array_unique(array_filter(array_map('intval', array_filter($resources['remove'], 'is_numeric'))))
            );
            $resourceIds = array_unique(array_filter(array_map('intval', $resourceIds)));
        }
        if (is_array($resources['toggle'])) {
            $toggle = array_unique(array_filter(array_map('intval', array_filter($resources['toggle'], 'is_numeric'))));
            $resourceIds = array_merge(
                array_diff($resourceIds, $toggle),
                array_diff($toggle, $resourceIds)
            );
        }

        $resourceIds = array_unique(array_filter($resourceIds));

        // Remove existing selection resources.
        $toAppend = array_combine($resourceIds, $resourceIds);

        $selectionResources = $selection->getSelectionResources();
        foreach ($selectionResources as $key => $selectionResource) {
            $resourceId = $selectionResource->getResource()->getId();
            if (in_array($resourceId, $resourceIds)) {
                unset($toAppend[$resourceId]);
            } else {
                $selectionResources->remove($key);
            }
        }

        if (!count($toAppend)) {
            return;
        }

        $entityManager = $this->getEntityManager();

        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->in('id', $toAppend));
        $now = new DateTime('now');
        foreach ($entityManager->getRepository(\Omeka\Entity\Resource::class)->matching($criteria) as $resource) {
            $selectionResource = new SelectionResource();
            $selectionResource
                ->setOwner($owner)
                ->setResource($resource)
                ->setSelection($selection)
                ->setCreated($now);
            $entityManager->persist($selectionResource);
        }

        // Refresh the selection with the appended resources.
        if ($selection->getId()) {
            $entityManager->refresh($selection);
        }
    }

    /**
     * Clean a search query: remove empty and useless arguments.
     *
     * @param string $searchQuery
     * @return string|null
     */
    protected function cleanSearchQuery(?string $searchQuery): ?string
    {
        $searchQuery = trim((string) $searchQuery, "? \t\n\r\0\x0B");
        if (empty($searchQuery)) {
            return null;
        }

        $query = [];
        parse_str($searchQuery, $query);
        unset($query['page'], $query['per_page'], $query['limit'], $query['offset'], $query['submit']);
        $query = array_filter($query, function ($v) {
            if (is_array($v)) {
                // TODO Filter other useless values (properties, numeric, created...).
                return count($v);
            }
            return strlen(trim((string) $v));
        });
        return count($query)
            ? rawurldecode(http_build_query($query, '', '&', PHP_QUERY_RFC3986))
            : null;
    }
}
