<?php declare(strict_types=1);

namespace Selection\Controller\Site;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Exception;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\User;
use Selection\Api\Representation\SelectionRepresentation;

/**
 * Manage selections via db for authenticated user with or without module Guest.
 *
 * There may be multiple selection by user, but only one most of the time.
 * A selected resource must belong to a selection.
 * A static selection is created on first action.
 * There may be multiple static selections, but the action is processed on the
 * first selection by default.
 * Dynamic selections (search requests) are managed only via api currently.
 */
trait TraitDbController
{
    protected function browseDb(User $user)
    {
        // Browse the list of selections.
        // The redirection for mode single selection is already done.

        $query = $this->params()->fromQuery();

        $site = $this->currentSite();
        $user = $this->identity();

        $this->browse()->setDefaults('items');

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        // The selection is always the one of the user here for now.
        // In the user view, limit to the selections of the user.
        // For now, it is always the case.
        if ($this->isGuestActive && $user) {
            $query['owner_id'] = $user->getId();
        }

        $response = $this->api()->search('selections', $query);
        $this->paginator($response->getTotalResults());
        $selections = $response->getContent();

        $selectionShare = $this->siteSettings()->get('selection_share');

        // TODO Records are loaded dynamically?

        $view = new ViewModel([
            'site' => $site,
            'selections' => $selections,
            'resources' => $selections,
            'user' => $user,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => false,
            'mode' => $this->settings()->get('selection_mode'),
            'disposition' => $this->currentDisposition($query),
            'allowIndividualSelect' => $this->allowIndividualSelect(),
            'selectionShare' => $selectionShare,
            'canPublishAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'publish'),
            'canValidateAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate'),
            'maxResources' => (int) $this->siteSettings()->get('selection_max_resources'),
        ]);

        return $view
            ->setTemplate($this->isGuestActive
                ? 'guest/site/guest/selection-browse'
                : 'selection/site/selection/browse'
            );
    }

    protected function viewDb(User $user)
    {
        // Display the current selection.

        $query = $this->params()->fromQuery();
        // $query['owner_id'] = $user->getId();

        // Most of the time, the selection is not defined, so take the first or
        // create a static one.
        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $this->getSelectionStaticOrFirstStaticOrCreate($selectionId, $user);
        $selectionId = $selection->id();

        // Fill selection from session.
        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $this->selectionContainer();

        $disposition = $this->currentDisposition($query);
        $allowIndividualSelect = $this->allowIndividualSelect();
        $selectionShare = $this->siteSettings()->get('selection_share');

        $view = new ViewModel([
            'site' => $this->currentSite(),
            'user' => $user,
            'selection' => null,
            'resource' => null,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => false,
            'mode' => $this->settings()->get('selection_mode'),
            'disposition' => $disposition,
            'allowIndividualSelect' => $allowIndividualSelect,
            'selectionShare' => $selectionShare,
            'canPublishAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'publish'),
            'canValidateAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate'),
        ]);
        return $view
            ->setTemplate($this->isGuestActive
                ? 'guest/site/guest/selection-view'
                : 'selection/site/selection/view'
            );
    }

    protected function addDb(User $user)
    {
        // Add a new selection via js.
        // TODO Is it useful to add/edit/delete new selection via a page?

        $user = $this->identity();
        if (!$user) {
            return $this->jsonPermissionDenied();
        }

        $selectionId = (int) $this->params()->fromRoute('id');
        if ($selectionId) {
            return $this->jSend(JSend::FAIL, null, $this->translate('Cannot add a new selection when selection id is specified.')); // @translate
        }

        $label = trim((string) $this->params()->fromPost('o:label'));
        if ($label === '') {
            return $this->jSend(JSend::FAIL, null, $this->translate('No label')); // @translate
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        // Check if the label is unique for the owner.
        $existing = $api->search('selections', [
            'owner_id' => $userId,
            'label' => $label,
        ])->getContent();
        if ($existing) {
            return $this->jSend(JSend::FAIL, null, $this->translate('The label is already used for another selection.')); // @translate
        }

        try {
            $selection = $api->create('selections', [
                'o:owner' => ['o:id' => $userId],
                'o:label' => $label
            ])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        $selectionId = $selection->id();

        // Quick process to include a resource during creation, avoiding a call.
        // TODO Factorize includeDb() and addDb() and addGroupDb().

        $results = [];
        $newResources = [];

        $resourcesData = $this->requestedResources();
        if (!empty($resourcesData['has_result'])) {
            foreach ($resourcesData['resources'] as $resourceId => $resource) {
                $data = $this->normalizeResource($resource, true, $selectionId);
                $data['status'] = 'success';
                try {
                    $api->create('selection_resources', [
                        'o:owner' => ['o:id' => $userId],
                        'o:resource' => ['o:id' => $resourceId],
                        'o:selection' => ['o:id' => $selectionId],
                    ])->getContent();
                } catch (Exception $e) {
                    // Normally no issue?
                }
                $newResources[] = $resourceId;
            }
            $results[$resourceId] = $data;
        }

        $newStructure = $this->addResourcesToStructure($selection->structure(), $newResources);
        if ($newStructure !== null) {
            try {
                $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
            } catch (Exception $e) {
                return $this->jsonInternalError();
            }
        }

        // Always return multiple here, it is only sugar.
        $data = [
            'selection' => $selection->getReference()->jsonSerialize(),
            'selection_resources' => $results,
        ];

        return $this->jSend(JSend::SUCCESS, $data);
    }

    protected function editDb(User $user)
    {
        // Edit a selection via js.

        $user = $this->identity();
        if (!$user) {
            return $this->jsonPermissionDenied();
        }

        $selectionId = (int) $this->params()->fromRoute('id');
        if (!$selectionId) {
            return $this->jSend(JSend::FAIL, null, $this->translate('Cannot edit a selection when selection id is not specified.')); // @translate
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        try {
            /** @var \Selection\Api\Representation\SelectionRepresentation $selection */
            $selection = $api->read('selections', ['id' => $selectionId])->getContent();
        } catch (\Exception $e) {
            return $this->jsonPermissionDenied();
        }

        if (!$selection->userIsAllowed('update')) {
            return $this->jsonPermissionDenied();
        }

        $params = $this->params()->fromPost();

        $data = [];
        if (isset($params['o:label'])) {
            $label = trim($params['o:label']);
            if ($label === '') {
                return $this->jSend(JSend::FAIL, null, $this->translate('No label')); // @translate
            }
            // Check if the label is unique for the owner.
            $existing = $api->search('selections', [
                'owner_id' => $user->getId(),
                'label' => $label,
            ])->getContent();
            if ($existing) {
                return $this->jSend(JSend::FAIL, ['o:label' => $this->translate('The label is already used for another selection.')]); // @translate
            }
            $data['o:label'] = $label;
        }

        if (isset($params['o:is_public'])) {
            $data['o:is_public'] = !empty($params['o:is_public']);
        }

        if (!$data) {
            return $this->jSend(JSend::FAIL, null, $this->translate('No change.')); // @translate
        }

        try {
            $selection = $api->update('selections', ['id' => $selectionId], $data, [], ['isPartial'=> true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        $data = [
            'selection' => $selection->getReference()->jsonSerialize(),
            // TODO List references of resources of selection if needed.
            // 'selection_resources' => [],
        ];

        return $this->jSend(JSend::SUCCESS, $data);
    }

    protected function deleteDb(User $user)
    {
        // Delete a selection via js.

        $user = $this->identity();
        if (!$user) {
            return $this->jsonPermissionDenied();
        }

        $selectionId = (int) $this->params()->fromRoute('id');
        if (!$selectionId) {
            return $this->jSend(JSend::FAIL, ['o:label' => $this->translate('Cannot delete a selection when the selection id is not specified.')]); // @translate
        }

        try {
            /** @var \Selection\Api\Representation\SelectionRepresentation $selection */
            $selection = $this->api()->read('selections', ['id' => $selectionId])->getContent();
        } catch (\Exception $e) {
            return $this->jsonPermissionDenied();
        }

        if (!$selection->userIsAllowed('delete')) {
            return $this->jsonPermissionDenied();
        }

        try {
            $this->api()->delete('selections', ['id' => $selectionId]);
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        $data = [
            'selection' => null,
        ];

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Select resource(s) to add to a selection.
     */
    protected function includeDb(array $resources, bool $isMultiple, User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        // TODO Factorize includeDb() and addDb() and addGroupDb().

        $results = [];

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        $selectedResourceIds = $api->search('selection_resources', [
            'owner_id' => $userId,
            'selection_id' => $selectionId,
        ], ['returnScalar' => 'resource'])->getContent();

        // Check selection limit before adding.
        $currentCount = count($selectedResourceIds);
        $newResourceCount = count(array_diff(array_keys($resources), $selectedResourceIds));
        if ($newResourceCount > 0 && !$this->checkSelectionLimit($currentCount, $newResourceCount)) {
            return $this->jsonSelectionLimitExceeded();
        }

        // A check is needed for resources at root that may not be in structure.
        $inStructure = $this->resourceIdsFromStructure($selection->structure());

        $newResources = [];
        foreach ($resources as $resourceId => $resource) {
            $data = $this->normalizeResource($resource, true, $selectionId);
            if (in_array($resourceId, $selectedResourceIds)) {
                if ($inStructure) {
                    $data['status'] = 'fail';
                    $data['data'] = [
                        'message' => $this->translate('Already in'), // @translate
                    ];
                } else {
                    $data['status'] = 'success';
                    $newResources[] = $resourceId;
                }
            } else {
                $data['status'] = 'success';
                try {
                    $api->create('selection_resources', [
                        'o:owner' => ['o:id' => $userId],
                        'o:resource' => ['o:id' => $resourceId],
                        'o:selection' => ['o:id' => $selectionId],
                    ])->getContent();
                } catch (Exception $e) {
                    // Normally no issue?
                }
                $newResources[] = $resourceId;
            }
            $results[$resourceId] = $data;
        }

        $newStructure = $this->addResourcesToStructure($selection->structure(), $newResources);
        if ($newStructure !== null) {
            try {
                $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
            } catch (Exception $e) {
                return $this->jsonInternalError();
            }
        }

        if ($isMultiple) {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resources' => $results,
            ];
        } else {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resource' => reset($results),
            ];
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Delete resource(s) from a selection or from all selections.
     */
    protected function excludeDb(array $resources, bool $isMultiple, User $user)
    {
        // If the route is without id, remove from all selections of the user.
        $matchedRouteName = $this->status()->getRouteMatch()->getMatchedRouteName();
        $isMultipleSelections = $matchedRouteName === 'site/selection' || $matchedRouteName === 'top/selection';
        if ($isMultipleSelections) {
            return $this->excludeDbAll($resources, $user);
        }

        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        $results = [];

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        $selectedResourceIds = $api->search('selection_resources', [
            'owner_id' => $userId,
            'selection_id' => $selectionId,
        ], ['returnScalar' => 'resource'])->getContent();

        foreach ($resources as $resourceId => $resource) {
            $data = $this->normalizeResource($resource, false, $selectionId);
            if (!in_array($resourceId, $selectedResourceIds)) {
                // Skip silently.
                $data['status'] = 'success';
                $results[$resourceId] = $data;
                continue;
            }
            $data['status'] = 'success';
            try {
                $api->delete('selection_resources', [
                    'owner' => $userId,
                    'resource' => $resourceId,
                    'selection' => $selectionId,
                ]);
            } catch (Exception $e) {
            }
            $results[$resourceId] = $data;
        }

        $newStructure = $this->removeResourcesFromStructure($selection->structure(), array_keys($results));
        if ($newStructure) {
            try {
                $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
            } catch (Exception $e) {
                return $this->jsonInternalError();
            }
        }

        if ($isMultiple) {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resources' => $results,
            ];
        } else {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resource' => reset($results),
            ];
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, $data);
    }

    protected function excludeDbAll(array $resources, User $user)
    {
        /**
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         */
        $api = $this->api();
        $userId = $user->getId();

        $selections = [];

        // Avoid an api exception when deleting a non-selected resource, so
        // filter them first.
        if ($resources) {
            $selectionIdsBySr = $api->search('selection_resources', [
                'owner_id' => $userId,
                'resource_id' => array_keys($resources),
            ], ['returnScalar' => 'selection'])->getContent();
            $selections = [];
            if ($selectionIdsBySr) {
                try {
                    $api->batchDelete('selection_resources', array_keys($selectionIdsBySr));
                } catch (Exception $e) {
                }
                $selectionIds = array_unique($selectionIdsBySr);
                foreach ($selectionIds as $selectionId) {
                    try {
                        $selection = $api->read('selections', $selectionId)->getContent();
                    } catch (Exception $e) {
                        continue;
                    }
                    $selections[$selectionId] = $selection;
                    $newStructure = $this->removeResourcesFromStructure($selection->structure(), array_keys($resources));
                    if ($newStructure) {
                        try {
                            $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
                        } catch (Exception $e) {
                            // This issue is always checked later.
                            continue;
                        }
                    }
                }
            }
        }

        $data = [
            'selections' => array_map(fn ($v) => $v->getReference()->jsonSerialize(), $selections),
            'selection_resources' => [],
        ];

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Delete all resources from a selection.
     */
    protected function resetDb(User $user)
    {
        $mode = $this->settings()->get('selection_mode', 'single');

        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection && $mode !== 'single') {
            return $this->jsonErrorNotFound();
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        $ids = $api->search('selection_resources', ['selection_id' => $selection->id()], ['returnScalar' => 'id'])->getContent();
        if ($ids) {
            try {
                $api->batchDelete('selection_resources', $ids);
            } catch (Exception $e) {
                // Nothing to do.
            }
        }

        // Update structure of the selection.
        try {
            $api->update('selections', $selection->id(), ['o:structure' => []], [], ['isPartial' => true]);
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        $data = [
            'selection' => null,
            'selection_resources' => [],
        ];

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Toggle select/unselect resource(s) for a selection.
     */
    protected function toggleDb(array $resources, bool $isMultiple, User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        $results = [];

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        $selectedResourceIds = $api->search('selection_resources', [
            'owner_id' => $userId,
            'selection_id' => $selectionId,
        ], ['returnScalar' => 'resource'])->getContent();

        $add = [];
        $delete = [];
        foreach ($resources as $resourceId => $resource) {
            if (in_array($resourceId, $selectedResourceIds)) {
                $delete[$resourceId] = $resource;
            } else {
                $add[$resourceId] = $resource;
            }
        }

        // Check selection limit before adding.
        if (count($add) > 0) {
            $currentCount = count($selectedResourceIds);
            $netAddCount = count($add) - count($delete);
            if ($netAddCount > 0 && !$this->checkSelectionLimit($currentCount, $netAddCount)) {
                return $this->jsonSelectionLimitExceeded();
            }
        }

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $add */
        foreach ($add as $resourceId => $resource) {
            try {
                $api->create('selection_resources', [
                    'o:owner' => ['o:id' => $userId],
                    'o:resource' => ['o:id' => $resourceId],
                    'o:selection' => ['o:id' => $selectionId],
                ])->getContent();
            } catch (Exception $e) {
            }
            $data = $this->normalizeResource($resource, true, $selectionId);
            $data['status'] = 'success';
            $results[$resourceId] = $data;
        }

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $delete */
        foreach ($delete as $resourceId => $resource) {
            try {
                $api->delete('selection_resources', [
                    'owner' => $userId,
                    'resource' => $resourceId,
                    'selection' => $selectionId,
                ]);
            } catch (Exception $e) {
            }
            $data = $this->normalizeResource($resource, false, $selectionId);
            $data['status'] = 'success';
            $results[$resourceId] = $data;
        }

        $oldStructure = $selection->structure();
        $newStructure = $this->addResourcesToStructure($oldStructure, array_keys($add));
        $newStructure = $this->removeResourcesFromStructure($newStructure ?? $oldStructure, array_keys($delete));
        if ($newStructure) {
            try {
                $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
            } catch (Exception $e) {
                return $this->jsonInternalError();
            }
        }

        if ($isMultiple) {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resources' => $results,
            ];
        } else {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resource' => reset($results),
            ];
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Update one or more resources for one or multiple selections or groups.
     *
     * Manage multiple selections or groups.
     * So like moveDb, but with destination group only.
     * And unlike add, the resource may be in any path.
     */
    protected function updateDb(array $resources, bool $isMultiple, array $selecteds, User $user)
    {
        // If the route is without id, update specified selections of the user.
        $matchedRouteName = $this->status()->getRouteMatch()->getMatchedRouteName();
        $isMultipleSelections = $matchedRouteName === 'site/selection' || $matchedRouteName === 'top/selection';
        if ($isMultipleSelections) {
            return $this->updateDbAll($resources, $selecteds, $user);
        }

        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        $structure = $selection->structure();

        // Check if group exists.
        // TODO For now, the module does not support to store the same resource in multiple places.
        $groups = array_filter($selecteds);
        $group = reset($groups);
        if (!isset($structure[$group])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The destination group is not in the list of groups.', // @translate
            ))->setTranslator($this->translator()));
        }

        $results = [];

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        $selectedResourceIds = $api->search('selection_resources', [
            'owner_id' => $userId,
            'selection_id' => $selectionId,
        ], ['returnScalar' => 'resource'])->getContent();

        // Check selection limit before adding new resources.
        $newResourceCount = count(array_diff(array_keys($resources), $selectedResourceIds));
        if ($newResourceCount > 0 && !$this->checkSelectionLimit(count($selectedResourceIds), $newResourceCount)) {
            return $this->jsonSelectionLimitExceeded();
        }

        // Remove resources from all groups.
        foreach ($structure as &$leaf) {
            if (!empty($leaf['resources'])) {
                $leaf['resources'] = array_diff($leaf['resources'], array_keys($resources));
            }
        }
        unset($leaf);

        // Add resources to the target group.
        if (!isset($structure[$group]['resources'])) {
            $structure[$group]['resources'] = [];
        }
        $structure[$group]['resources'] = array_values(array_unique(array_merge(
            $structure[$group]['resources'],
            array_keys($resources)
        )));

        // Ensure resources are in selection_resources table.
        foreach ($resources as $resourceId => $resource) {
            $data = $this->normalizeResource($resource, true, $selectionId);
            if (!in_array($resourceId, $selectedResourceIds)) {
                try {
                    $api->create('selection_resources', [
                        'o:owner' => ['o:id' => $userId],
                        'o:resource' => ['o:id' => $resourceId],
                        'o:selection' => ['o:id' => $selectionId],
                    ])->getContent();
                } catch (Exception $e) {
                }
            }
            $data['status'] = 'success';
            $results[$resourceId] = $data;
        }

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true]);
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        if ($isMultiple) {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resources' => $results,
            ];
        } else {
            $data = [
                'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
                'selection_resource' => reset($results),
            ];
        }

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Update one or more resources for one or multiple selections or groups.
     *
     * Manage multiple selections or groups.
     *
     * @todo When there is no resources, reset all selected selections?
     * @todo Managed selections with groups (disposition grouped or hierarchical).
     */
    protected function updateDbAll(array $resources, array $selecteds, User $user)
    {
        if (!count($selecteds)) {
            return $this->excludeDbAll($resources, $user);
        }

        // Normally already checked.
        if (!count($resources)) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'No resources selected.', // @translate
            ))->setTranslator($this->translator()));
        }

        /**
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         */
        $api = $this->api();
        $userId = $user->getId();

        // Avoid issue for empty selecteds or groups, that should not be empty
        // here.
        $selecteds = array_values(array_unique(array_filter(array_map('intval', $selecteds))));
        if (!$selecteds) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'No selections selected.', // @translate
            ))->setTranslator($this->translator()));
        }

        try {
            $selections = $api->search('selections', ['id' => $selecteds])->getContent();
        } catch (Exception $e) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Selection not found.', // @translate
            ))->setTranslator($this->translator()), \Laminas\Http\PhpEnvironment\Response::STATUS_CODE_404);
        }

        $results = [];

        // There are three cases for each resource and each selection:
        // - already in selection (let it)
        // - in selection (include it)
        // - no more in selection (exclude it).

        $selectionIdsBySr = $api->search('selection_resources', [
            'owner_id' => $userId,
            'resource_id' => array_keys($resources),
        ], ['returnScalar' => 'selection'])->getContent();
        $resourceIdsBySr = $api->search('selection_resources', [
            'owner_id' => $userId,
            'resource_id' => array_keys($resources)
        ], ['returnScalar' => 'resource'])->getContent();
        $resourceAndSelections = [];
        foreach ($resourceIdsBySr as $srId => $resourceId) {
            $selectionId = $selectionIdsBySr[$srId];
            $resourceAndSelections["$selectionId:$resourceId"] = ['s' => $selectionId, 'r' => $resourceId, 'srid' => $srId];
        }

        // Add all resources to specified selections they aren't yet.
        foreach ($selections as $selection) {
            $selectionId = $selection->id();
            $structure = $selection->structure();

            // Add resources that are not in the selection to the root group
            // or first group.
            $newResourcesInSelection = [];
            foreach ($resources as $resourceId => $resource) {
                if (!isset($resourceAndSelections["$selectionId:$resourceId"])) {
                    $newResourcesInSelection[] = $resourceId;
                }
            }

            if ($newResourcesInSelection) {
                // Check selection limit before adding new resources.
                $currentSelectionCount = $api->search('selection_resources', [
                    'owner_id' => $userId,
                    'selection_id' => $selectionId,
                ], ['returnScalar' => 'id'])->getTotalResults();
                if (!$this->checkSelectionLimit($currentSelectionCount, count($newResourcesInSelection))) {
                    return $this->jsonSelectionLimitExceeded();
                }
                $group = key($structure);
                $structure[$group]['resources'] = array_values(array_unique(array_merge(
                    $structure[$group]['resources'] ?? [],
                    $newResourcesInSelection
                )));

                try {
                    $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true]);
                } catch (Exception $e) {
                    $this->logger()->err(
                        'Error when updating selection: {exception}', // @translate
                        ['exception' => $e]
                    );
                    return $this->jsonInternalError();
                }

                // Ensure resources are stored.
                foreach ($resources as $resourceId => $resource) {
                    $data = $this->normalizeResource($resource, true, $selectionId);
                    if (!isset($newResourcesInSelection["$selectionId:$resourceId"])) {
                        try {
                            $api->create('selection_resources', [
                                'o:owner' => ['o:id' => $userId],
                                'o:resource' => ['o:id' => $resourceId],
                                'o:selection' => ['o:id' => $selectionId],
                            ])->getContent();
                        } catch (Exception $e) {
                            $this->logger()->err(
                                'Error when adding resource to selection: {exception}', // @translate
                                ['exception' => $e]
                            );
                            return $this->jsonInternalError();
                        }
                    }
                    $data['status'] = 'success';
                    $results[$resourceId] = $data;
                }
            }
        }

        // Remove all resources from selections they aren't any more.
        $batchDeleteSrIds = [];
        foreach ($resourceIdsBySr as $srId => $resourceId) {
            $selectionId = $selectionIdsBySr[$srId];
            if (!in_array($selectionId, $selecteds)) {
                $batchDeleteSrIds[] = $srId;
            }
        }
        if ($batchDeleteSrIds) {
            try {
                $api->batchDelete('selection_resources', $batchDeleteSrIds, [], [
                    'continueOnError' => true
                ]);
                // Remove the resources from the structures too.
                foreach ($batchDeleteSrIds as $srId) {
                    $selectionId = $selectionIdsBySr[$srId];
                    try {
                        $selection = $api->read('selections', $selectionId)->getContent();
                    } catch (Exception $e) {
                        continue;
                    }
                    $resourceId = $resourceIdsBySr[$srId];
                    $structure = $selection->structure();
                    $newStructure = $this->removeResourcesFromStructure($structure, [$resourceId]);
                    if ($newStructure) {
                        try {
                            $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
                        } catch (Exception $e) {
                            // This issue is always checked later.
                            continue;
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip.
            }
        }

        $data = [
            'selections' => array_map(fn ($v) => $v->getReference()->jsonSerialize(), $selections),
            'selection_resources' => $results,
        ];

        return $this->jSend(JSend::SUCCESS, $data);
    }

    /**
     * Move resource(s) between groups of a selection.
     */
    protected function moveDb(array $resources, bool $isMultiple, User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        $structure = $selection->structure();

        $result = $this->moveCheck($structure);
        if (!is_array($result)) {
            return $result;
        }

        [$source, $destination] = array_values($result);

        if (strlen($source)) {
            $sourceResources = $selection->resourcesForGroup($source);
            $structure[$source]['resources'] = array_keys(array_diff_key($sourceResources, $resources));
        }

        if (strlen($destination)) {
            if (empty($structure[$destination]['resources'])) {
                $structure[$destination]['resources'] = array_keys($resources);
            } else {
                $structure[$destination]['resources'] = array_merge(array_values($structure[$destination]['resources']), array_keys($resources));
            }
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, [
            'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'source' => $structure[$source] ?? null,
            'group' => $structure[$destination] ?? null,
        ]);
    }

    /**
     * Add a group to a selection.
     */
    protected function addGroupDb(User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        // Add the group only if it does not exist.
        $structure = $selection->structure();

        $result = $this->addGroupCheck($structure);
        if (!is_array($result)) {
            return $result;
        }

        [$group, $structure] = array_values($result);

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        $userId = $user->getId();

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        // $this->selectionContainer();

        // Quick process to include a resource during creation, avoiding a call.
        // TODO Factorize includeDb() and addDb() and addGroupDb().

        $results = [];
        $newResources = [];

        $resourcesData = $this->requestedResources();
        if (!empty($resourcesData['has_result'])) {
            $resources = $resourcesData['resources'];

            $selectedResourceIds = $api->search('selection_resources', [
                'owner_id' => $userId,
                'selection_id' => $selectionId,
            ], ['returnScalar' => 'resource'])->getContent();

            // A check is needed for resources at root that may not be in structure.
            $inStructure = $this->resourceIdsFromStructure($selection->structure());

            $newResources = [];
            foreach ($resources as $resourceId => $resource) {
                $data = $this->normalizeResource($resource, true, $selectionId);
                if (in_array($resourceId, $selectedResourceIds)) {
                    if ($inStructure) {
                        $data['status'] = 'fail';
                        $data['data'] = [
                            'message' => $this->translate('Already in'), // @translate
                        ];
                    } else {
                        $data['status'] = 'success';
                        $newResources[] = $resourceId;
                    }
                } else {
                    $data['status'] = 'success';
                    try {
                        $api->create('selection_resources', [
                            'o:owner' => ['o:id' => $userId],
                            'o:resource' => ['o:id' => $resourceId],
                            'o:selection' => ['o:id' => $selectionId],
                        ])->getContent();
                    } catch (Exception $e) {
                        // Normally no issue?
                    }
                    $newResources[] = $resourceId;
                }
                $results[$resourceId] = $data;
            }

            $newStructure = $this->addResourcesToStructure($selection->structure(), $newResources);
            if ($newStructure !== null) {
                try {
                    $api->update('selections', $selectionId, ['o:structure' => $newStructure], [], ['isPartial' => true]);
                } catch (Exception $e) {
                    return $this->jsonInternalError();
                }
            }
        }

        return $this->jSend(JSend::SUCCESS, [
            'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'group' => $group,
            'selection_resources' => $results,
        ]);
    }

    /**
     * Rename a group (last part) in a selection.
     */
    protected function renameGroupDb(User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        // Rename the group only if it does not exist.
        $structure = $selection->structure();

        $result = $this->renameGroupCheck($structure);
        if (!is_array($result)) {
            return $result;
        }

        $structure = $result;

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, [
            'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'structure' => $selection->structure(),
        ]);
    }

    /**
     * Move a group in a selection.
     */
    protected function moveGroupDb(User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        // Move the group only if it does not exist.
        $structure = $selection->structure();

        $result = $this->moveGroupCheck($structure);
        if (!is_array($result)) {
            return $result;
        }

        $structure = $result;

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, [
            'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'structure' => $selection->structure(),
        ]);
    }

    /**
     * Delete a group in a selection.
     */
    protected function deleteGroupDb(User $user)
    {
        $selection = $this->checkSelectionFromRouteOrInit($user);
        if (!$selection) {
            return $this->jsonErrorNotFound();
        }

        $selectionId = $selection->id();

        // Delete the group only if it exists.
        $structure = $selection->structure();

        $result = $this->deleteGroupCheck($structure);
        if (!is_array($result)) {
            return $result;
        }

        [$selecteds, $structure] = array_values($result);

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        if ($selecteds) {
            $selectionResourceIds = [];
            foreach ($selection->selectionResources() as $selectionResource) {
                $linkedResource = $selectionResource->resource();
                if ($linkedResource && in_array($linkedResource->id(), $selecteds)) {
                    $selectionResourceIds[] = $selectionResource->id();
                }
            }
            if ($selectionResourceIds) {
                $api->batchDelete('selection_resources', $selectionResourceIds);
            }
        }

        try {
            $api->update('selections', $selectionId, ['o:structure' => $structure], [], ['isPartial' => true])->getContent();
        } catch (Exception $e) {
            return $this->jsonInternalError();
        }

        // $this->selectionContainer();

        return $this->jSend(JSend::SUCCESS, [
            'selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'structure' => $selection->structure(),
        ]);
    }

    /**
     * Get selected resources from the query and prepare them.
     */
    protected function requestedResources()
    {
        $params = $this->params();
        $id = $params->fromQuery('id');
        if (!$id) {
            return ['has_result' => false];
        }

        $isMultiple = is_array($id);
        $ids = $isMultiple ? $id : [$id];

        $api = $this->api();

        // Check resources.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
        $resources = [];
        foreach ($ids as $id) {
            try {
                // TODO Use a search resources when enabled in omeka.
                $resource = $api->read('resources', ['id' => $id])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return ['has_result' => false];
            }
            $resources[$id] = $resource;
        }

        return [
            'has_result' => (bool) count($resources),
            'is_multiple' => $isMultiple,
            'resources' => $resources,
        ];
    }

    /**
     * Get all resources ids from a structure.
     *
     * May be different from selection resource table, because the resources at
     * root may not be included.
     */
    protected function resourceIdsFromStructure(array $structure): array
    {
        $inStructure = [];
        foreach ($structure as $leaf) {
            $inStructure = array_merge($inStructure, $leaf['resources'] ?? []);
        }
        $inStructure = array_unique($inStructure);
        sort($inStructure);
        return array_combine($inStructure, $inStructure);
    }

    protected function checkSelectionFromRouteOrInit(User $user): ?SelectionRepresentation
    {
        /** @var \Selection\Api\Representation\SelectionRepresentation $selection */
        $selectionId = (int) $this->params()->fromRoute('id');
        $selection = $this->getSelectionStatic($selectionId, $user);
        if ($selectionId && !$selection) {
            return null;
        }
        return $selection
            ?? $this->getSelectionStaticFirst($user)
            ?? $this->createSelectionStatic($user);
    }

    /**
     * Get a selection or create it.
     */
    protected function getSelectionStaticOrFirstStaticOrCreate(?int $selectionId, User $user): SelectionRepresentation
    {
        return $this->getSelectionStatic($selectionId, $user)
            ?? $this->getSelectionStaticFirst($user)
            ?? $this->createSelectionStatic($user);
    }

    /**
     * Get selection from id.
     */
    protected function getSelectionStatic($id, User $user): ?SelectionRepresentation
    {
        if (!$id) {
            return null;
        }
        try {
            return $this->api()->read('selections', [
                    'id' => $id,
                    'owner' => $user->getId(),
                    'isDynamic' => false,
                ])->getContent();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get default selection, the first non-dynamic one.
     */
    protected function getSelectionStaticFirst(User $user): ?SelectionRepresentation
    {
        return $this->api()->searchOne('selections', [
            'owner_id' => $user->getId(),
            'is_dynamic' => false,
        ])->getContent();
    }

    /**
     * Get default selection, the first non-dynamic one or a new one when none.
     */
    protected function createSelectionStatic(User $user): SelectionRepresentation
    {
        return $this->api()->create('selections', [
            'o:owner' => ['o:id' => $user->getId()],
            'o:label' => $this->siteSettings()->get('selection_label', $this->translate('Selection')), // @translate
        ])->getContent();
    }
}
