<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2019-2026
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

namespace Selection\Controller\Site;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Manage selections via db for authenticated user with or without module Guest.
 *
 * @todo Include the selection by default in all actions.
 * @todo Factorize.
 */
abstract class AbstractSelectionController extends AbstractActionController
{
    use TraitDbController;
    use TraitSessionController;

    /**
     * @var bool
     */
    protected $isGuestActive = false;

    public function indexAction()
    {
        $site = $this->currentSite();
        $user = $this->identity();

        $this->browse()->setDefaults('selections');

        $query = $this->params()->fromQuery();

        // The selection are not related to site for now.
        $query['site_id'] = $site->id();

        // Public list: show only public selections, even for authenticated
        // users. Their own selections are listed under /guest/selection.
        $query['is_public'] = true;

        $response = $this->api()->search('selections', $query);
        $this->paginator($response->getTotalResults());
        $selections = $response->getContent();

        return new ViewModel([
            'site' => $site,
            'user' => $user,
            'selections' => $selections,
            'resources' => $selections,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => false,
        ]);
    }

    /**
     * Browse means to browse own selections.
     * Use "index" and "show" to browse all selections.
     */
    public function browseAction()
    {
        $user = $this->identity();
        $mode = $this->selectionMode();
        if ($mode === 'none') {
            throw new \Omeka\Api\Exception\PermissionDeniedException();
        }

        if ($mode === 'simple') {
            $params = $this->params()->fromRoute();
            $params['action'] = 'view';
            // TODO For now, there is always a user.
            return $user
                ? $this->forward()->dispatch('Selection\Controller\Site\Selection', $params)
                : $this->forward()->dispatch('Selection\Controller\Site\Guest', $params);
        }

        return $user
            ? $this->browseDb($user)
            : $this->browseSession();
    }

    /**
     * Show a published selection.
     * Use view to show own selections.
     * A published selection means user, so an anonymous cannot publish one.
     * This method is overridden in SelectionController to publish a selection.
     */
    public function showAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'view';
        $user = $this->identity();
        return $user
            ? $this->forward()->dispatch('Selection\Controller\Site\Selection', $params)
            : $this->forward()->dispatch('Selection\Controller\Site\Guest', $params);
    }

    public function viewAction()
    {
        $user = $this->identity();
        if (!$user) {
            if ($this->selectionMode() === 'none') {
                throw new \Omeka\Api\Exception\PermissionDeniedException();
            }
            return $this->viewSession();
        }

        return $this->viewDb($user);
    }

    /**
     * Add a new selection.
     */
    public function addAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->addDb($user)
            : $this->addSession();
    }

    /**
     * Edit a selection (publish, rename, clear).
     */
    public function editAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->editDb($user)
            : $this->editSession();
    }

    /**
     * Delete a selection.
     */
    public function deleteAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->deleteDb($user)
            : $this->deleteSession();
    }

    /**
     * Select resource(s) to add to a selection.
     */
    public function includeAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->includeDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->includeSession($resourcesData['resources'], $resourcesData['is_multiple']);
    }

    /**
     * Delete resource(s) from a selection.
     */
    public function excludeAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->excludeDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->excludeSession($resourcesData['resources'], $resourcesData['is_multiple']);
    }

    /**
     * Delete all resources from selection.
     */
    public function resetAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->resetDb($user)
            : $this->resetSession();
    }

    /**
     * Display the user favorites (default selection) or the visitor session
     * favorites as a single flat list. The page is private and not shareable.
     * Available only when the favorites feature is enabled (mode "simple" or
     * site setting "selection_my_favorites").
     */
    public function favoritesAction()
    {
        $siteSettings = $this->siteSettings();
        $mode = $this->selectionMode();
        $myFavorites = (bool) $siteSettings->get('selection_my_favorites', true);
        if ($mode !== 'simple' && !$myFavorites) {
            throw new \Omeka\Api\Exception\PermissionDeniedException();
        }

        $site = $this->currentSite();
        $user = $this->identity();

        if ($user) {
            $selection = null;
            try {
                $selections = $this->api()
                    ->search('selections', [
                        'owner_id' => $user->getId(),
                        'is_default' => true,
                        'limit' => 1,
                    ])
                    ->getContent();
                $selection = reset($selections) ?: null;
            } catch (\Exception $e) {
                $selection = null;
            }
            $selectionId = $selection ? $selection->id() : 0;
            $sessionSelections = [];
            $records = [];
            $isSession = false;
        } else {
            /** @var \Laminas\Session\Container $container */
            $container = $this->selectionContainer();
            $sessionSelections = $container->selections ?? [];
            $selection = reset($sessionSelections) ?: null;
            $selectionId = $selection ? (int) $selection['id'] : 0;
            $records = $container->records ?? [];
            $isSession = true;
        }

        return new ViewModel([
            'site' => $site,
            'user' => $user,
            'selection' => $selection,
            'resource' => is_object($selection) ? $selection : null,
            'selectionId' => $selectionId,
            'selections' => $sessionSelections,
            'records' => $records,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => $isSession,
            'mode' => $mode,
            'disposition' => 'flat',
            'allowIndividualSelect' => false,
        ]);
    }

    /**
     * Toggle select/unselect resource(s) for a selection.
     */
    public function toggleAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->toggleDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->toggleSession($resourcesData['resources'], $resourcesData['is_multiple']);
    }

    /**
     * Update one or more resources for one or multiple selections or groups.
     *
     * Same as addAction, but with groups.
     */
    public function updateAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        // TODO For now, the module does not support to store the same resource in multiple places.
        $selecteds = $this->params()->fromPost('selecteds', '');
        if (is_array($selecteds)) {
            $selecteds = array_filter($selecteds);
        } else {
            $selecteds = [$selecteds];
        }
        $selecteds = array_filter($selecteds, fn ($v) => strlen((string) $v));
        if (!count($selecteds)) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The selection is not set.', // @translate
            ))->setTranslator($this->translator()));
        }

        return $user
            ? $this->updateDb($resourcesData['resources'], $resourcesData['is_multiple'], $selecteds, $user)
            : $this->updateSession($resourcesData['resources'], $resourcesData['is_multiple'], $selecteds);
    }

    /**
     * Move resource(s) between groups of a selection.
     */
    public function moveAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->moveDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->moveSession($resourcesData['resources'], $resourcesData['is_multiple']);
    }

    /**
     * @return array|\Laminas\View\Model\JsonModel
     */
    protected function moveCheck(array $structure)
    {
        $source = trim((string) $this->params()->fromQuery('group'));
        $destination = trim((string) $this->params()->fromQuery('name'));

        if ($source === $destination) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group name is unchanged.') // @translate
            );
        }

        if (strlen($source) && !isset($structure[$source])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The group "{name}" does not exist.', // @translate
                ['name' => strtr($source, ['/' => ' / '])]
            ))->setTranslator($this->translator()));
        }

        if (strlen($destination) && !isset($structure[$destination])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The destination group "{name}" does not exist.', // @translate
                ['name' => strtr($destination, ['/' => ' / '])]
            ))->setTranslator($this->translator()));
        }

        if (strlen($destination) && !isset($structure[$destination])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The destination group "{name}" does not exist.', // @translate
                ['name' => strtr($destination, ['/' => ' / '])]
            ))->setTranslator($this->translator()));
        }

        return [
            'source' => $source,
            'destination' => $destination,
        ];
    }

    /**
     * Add a group to a selection.
     */
    public function addGroupAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->addGroupDb($user)
            : $this->addGroupSession();
    }

    /**
     * @return array|\Laminas\View\Model\JsonModel
     */
    protected function addGroupCheck(array $structure)
    {
        $path = trim((string) $this->params()->fromQuery('group'));
        $groupName = trim((string) $this->params()->fromQuery('name'));
        if (!strlen($groupName)) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('No group set.') // @translate
            );
        }

        $invalidCharacters = '/\\?<>*%|"`&';
        $invalidCharactersRegex = '~/|\\|\?|<|>|\*|\%|\||"|`|&~';
        if ($groupName === '/'
            || $groupName === '\\'
            || $groupName === '.'
            || $groupName === '..'
            || preg_match($invalidCharactersRegex, $groupName)
        ) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The group name contains invalid characters ({characters}).', // @translate
                ['characters' => $invalidCharacters]
            ))->setTranslator($this->translator()));
        }

        // Check the parent for security.
        $hasParent = strlen($path);
        if ($hasParent && !isset($structure[$path])) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The parent group does not exist.') // @translate
            );
        }

        $parentPath = dirname($path) === '/' ? '' : dirname($path);

        $fullPath = "$parentPath/$groupName";
        if (isset($structure[$fullPath])) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group exists already.') // @translate
            );
        }

        // Insert the group inside the parent path.
        if ($hasParent) {
            $group = [
                // path + id = full path.
                'id' => $groupName,
                'path' => $path,
            ];
            $s = [];
            foreach ($structure as $sFullPath => $sGroup) {
                $s[$sFullPath] = $sGroup;
                if ($sFullPath === $path) {
                    $s[$fullPath] = $group;
                }
            }
            $structure = $s;
        } else {
            $group = [
                'id' => $groupName,
                'path' => '/',
            ];
            $structure[$fullPath] = $group;
        }

        return [
            'group' => $group,
            'structure' => $structure,
        ];
    }

    /**
     * Rename a group (last part) in a selection.
     */
    public function renameGroupAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->renameGroupDb($user)
            : $this->renameGroupSession();
    }

    /**
     * @return array|\Laminas\View\Model\JsonModel
     */
    protected function renameGroupCheck(array $structure)
    {
        $path = trim((string) $this->params()->fromQuery('group'));
        $currentGroupName = basename($path);
        if (!strlen($currentGroupName)) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('No group set.') // @translate
            );
        }

        $groupName = trim((string) $this->params()->fromQuery('name'));
        if (!strlen($groupName)) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('No group set.') // @translate
            );
        }

        $invalidCharacters = '/\\?<>*%|"`&';
        $invalidCharactersRegex = '~/|\\|\?|<|>|\*|\%|\||"|`|&~';
        if ($groupName === '/'
            || $groupName === '\\'
            || $groupName === '.'
            || $groupName === '..'
            || preg_match($invalidCharactersRegex, $groupName)
        ) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The group name contains invalid characters ({characters}).', // @translate
                ['characters' => $invalidCharacters]
            ))->setTranslator($this->translator()));
        }

        if ($currentGroupName === $groupName) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group name is unchanged.') // @translate
            );
        }

        // Check the parent for security.
        $hasParent = strlen($path);
        if ($hasParent && !isset($structure[$path])) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The parent group does not exist.') // @translate
            );
        }

        $currentFullPath = $path;
        $parentPath = dirname($path) === '/' ? '' : dirname($path);

        $fullPath = "$parentPath/$groupName";
        if (isset($structure[$fullPath])) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group exists already.') // @translate
            );
        }

        // Rename the group and all children: even if each group is managed as a
        // full path, this is a structure.
        $s = [];
        foreach ($structure as $sFullPath => $sGroup) {
            if ($sFullPath === $currentFullPath) {
                $sGroup['id'] = $groupName;
                $s[$fullPath] = $sGroup;
            } elseif (mb_strpos($sFullPath, $currentFullPath . '/') === 0) {
                $sGroup['path'] = $fullPath;
                $s[$fullPath . '/' . $sGroup['id']] = $sGroup;
            } else {
                $s[$sFullPath] = $sGroup;
            }
        }
        $structure = $s;

        return $structure;
    }

    /**
     * Reorder resources within a selection group (default group: root "/").
     *
     * Expected POST body: selection_id, group (optional, default "/"),
     * ids[] ordered list of resource ids.
     */
    public function reorderAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        $params = $this->params();
        $selectionId = (int) $params->fromPost('selection_id', 0);
        $group = (string) $params->fromPost('group', '/');
        $ids = $params->fromPost('ids', []);
        if (!is_array($ids)) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('Missing ordered ids.') // @translate
            );
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids);

        $user = $this->identity();
        return $user
            ? $this->reorderDb($selectionId, $group, $ids, $user)
            : $this->reorderSession($selectionId, $group, $ids);
    }

    /**
     * Move a group in a selection.
     */
    public function moveGroupAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->moveGroupDb($user)
            : $this->moveGroupSession();
    }

    /**
     * @return array|\Laminas\View\Model\JsonModel
     */
    protected function moveGroupCheck(array $structure)
    {
        $source = trim((string) $this->params()->fromQuery('group'));
        $groupName = basename($source);
        if (!strlen($source) || !strlen($groupName)) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('No group set.') // @translate
            );
        }

        $parentDestination = trim((string) $this->params()->fromQuery('name'));
        if (!strlen($parentDestination)) {
            $parentDestination = '/';
        }

        $destination = ($parentDestination === '/' ? '' : $parentDestination) . '/' . $groupName;
        if ($source === $destination) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group name is unchanged.') // @translate
            );
        }

        if (mb_strpos($destination . '/', $source . '/') === 0) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group cannot be moved inside itself.') // @translate
            );
        }

        if (!isset($structure[$source])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The group "{name}" does not exist.', // @translate
                ['name' => strtr($source, ['/' => ' / '])]
            ))->setTranslator($this->translator()));
        }

        if ($parentDestination !== '/' && !isset($structure[$parentDestination])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                $this->translate('The group "{name}" does not exist.'), // @translate
                ['name' => strtr($parentDestination, ['/' => ' / '])]
            ))->setTranslator($this->translator()));
        }

        $sourceParentPath = dirname($source);
        $sourceParentPathLength = strlen($sourceParentPath);

        // The group and all sub-groups should be moved at the right place, so
        // prepare them all.
        // Note: normally, the tree of groups is logical: a branch is always
        // after its parent branch.
        $sourceGroups = [];
        if ($parentDestination === '/') {
            foreach ($structure as $sFullPath => $sGroup) {
                if (strpos($sFullPath . '/', $source . '/') === 0) {
                    $newPath = mb_substr($sGroup['path'], $sourceParentPathLength);
                    $sGroup['path'] = $newPath === '' ? '/' : $newPath;
                    $sourceGroups[$newPath . '/' . $sGroup['id']] = $sGroup;
                    unset($structure[$sFullPath]);
                }
            }
        } else {
            foreach ($structure as $sFullPath => $sGroup) {
                if (strpos($sFullPath . '/', $source . '/') === 0) {
                    $newPath = $parentDestination
                        . ($sGroup['path'] === '/' ? '' : mb_substr($sGroup['path'], $sourceParentPathLength));
                    $sGroup['path'] = $newPath;
                    $sourceGroups[$newPath . '/' . $sGroup['id']] = $sGroup;
                    unset($structure[$sFullPath]);
                }
            }
        }

        $s = [];
        // Append.
        if (!isset($structure[$destination]) && $parentDestination === '/') {
            $s = $structure + $sourceGroups;
        }
        // No merge.
        elseif (!isset($structure[$destination])) {
            foreach ($structure as $sFullPath => $sGroup) {
                $s[$sFullPath] = $sGroup;
                if ($sFullPath === $parentDestination) {
                    $s += $sourceGroups;
                }
            }
        }
        // Merge.
        else {
            foreach ($structure as $sFullPath => $sGroup) {
                if (isset($s[$sFullPath])) {
                    continue;
                }
                $s[$sFullPath] = $sGroup;
                if ($sFullPath === $parentDestination) {
                    foreach ($sourceGroups as $sourceFullPath => $sourceGroup) {
                        if (isset($structure[$sourceFullPath])) {
                            $s[$sourceFullPath] = $structure[$sourceFullPath];
                            if (empty($s[$sourceFullPath]['resources'])) {
                                $s[$sourceFullPath]['resources'] = $sourceGroup['resources'] ?? [];
                            } elseif (!empty($sourceGroup['resources'])) {
                                $s[$sourceFullPath]['resources'] = array_merge(array_values($s[$sourceFullPath]['resources']), array_values($sourceGroup['resources']));
                            }
                        } else {
                            $s[$sourceFullPath] = $sourceGroup;
                        }
                    }
                }
            }
        }
        $structure = $s;

        return $structure;
    }

    /**
     * Delete a group in a selection.
     */
    public function deleteGroupAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if ($this->selectionMode() === 'none') {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->deleteGroupDb($user)
            : $this->deleteGroupSession();
    }

    /**
     * @return array|\Laminas\View\Model\JsonModel
     */
    protected function deleteGroupCheck(array $structure)
    {
        $path = trim((string) $this->params()->fromQuery('group'));
        if (!strlen($path) || $path === '/') {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('No group set.') // @translate
            );
        }

        if (!isset($structure[$path])) {
            return $this->jSend(JSend::FAIL, null,
                $this->translate('The group does not exist.') // @translate
            );
        }

        // Get selected resources in all children groups.
        $selecteds = [];
        foreach ($structure as $sFullPath => $sGroup) {
            if (strpos($sFullPath . '/', $path . '/') === 0) {
                if (!empty($sGroup['resources'])) {
                    $selecteds = array_merge($selecteds, array_values($sGroup['resources']));
                }
                unset($structure[$sFullPath]);
            }
        }

        return [
            'selecteds' => $selecteds,
            'structure' => $structure,
        ];
    }

    /**
     * Get selected resources from the query and prepare them.
     */
    protected function requestedResources(): array
    {
        $params = $this->params();
        $id = $params->fromQuery('id') ?: $params->fromPost('id');
        if (!$id) {
            return [
                'has_result' => false,
            ];
        }

        $isMultiple = is_array($id);
        $ids = $isMultiple ? $id : [$id];

        $api = $this->api();

        // Check resources.
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
        $resources = [];
        foreach ($ids as $id) {
            try {
                $resource = $api->read('resources', ['id' => $id])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                continue;
            }
            $resources[$id] = $resource;
        }

        if (!count($resources)) {
            return [
                'has_result' => false,
            ];
        }

        return [
            'has_result' => (bool) count($resources),
            'is_multiple' => $isMultiple,
            'resources' => $resources,
        ];
    }

    /**
     * Merge a desired order with the current set of resource ids: items in the
     * desired order come first (only if they exist in current), then any
     * remaining current items preserve their previous order at the end.
     */
    protected function mergeReorder(array $current, array $desired): array
    {
        $currentSet = array_flip(array_map('intval', $current));
        $ordered = [];
        foreach ($desired as $id) {
            $id = (int) $id;
            if (isset($currentSet[$id])) {
                $ordered[] = $id;
                unset($currentSet[$id]);
            }
        }
        foreach ($current as $id) {
            $id = (int) $id;
            if (isset($currentSet[$id])) {
                $ordered[] = $id;
                unset($currentSet[$id]);
            }
        }
        return $ordered;
    }

    /**
     * Store the new resources at the root of the structure of the selection.
     *
     * @return array|null Null if structure is unchanged.
     */
    protected function addResourcesToStructure(array $structure, array $newResources): ?array
    {
        if (!$newResources) {
            return null;
        }

        $oldStructure = $structure;

        $structureResources = $structure['/']['resources'] ?? [];
        $structureResources = array_unique(array_merge($structureResources, $newResources));
        $structure['/'] = [
            'id' => '',
            'path' => '/',
            'resources' => $structureResources,
        ];

        return $structure !== $oldStructure
            ? $structure
            : null;
    }

    /**
     * Remove resources from the structure of the selection.
     *
     * @return array|null Null if structure is unchanged.
     */
    protected function removeResourcesFromStructure(array $structure, array $resourcesToRemove): ?array
    {
        if (!$resourcesToRemove) {
            return null;
        }

        $oldStructure = $structure;

        // Remove old resources from the structure of the selection.
        foreach ($structure as $path => $group) {
            $structure[$path]['resources'] = array_diff($group['resources'] ?? [], $resourcesToRemove);
        }

        return $structure !== $oldStructure
            ? $structure
            : null;
    }

    /**
     * Format a resource to be stored.
     */
    protected function normalizeResource(
        AbstractResourceEntityRepresentation $resource,
        bool $isSelected,
        ?int $selectionId = null
    ): array {
        try {
            $selection = $selectionId
                ? $this->api()->read('selections', ['id' => $selectionId])->getContent()
                : null;
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $selection = null;
        }

        // TODO Use a fake selection resource for now. Or check if the api selectionResource can be found.
        $selectionResource = (object) [
            'resource' => $resource,
            'selection' => $selection,
        ];

        $selectionRecords = $this->viewHelpers()->get('selectionRecords');
        return $selectionRecords(null, [$selectionResource], $isSelected);
    }

    protected function currentDisposition(array $query = []): string
    {
        $allowedDispositions = ['flat', 'grouped', 'hierarchy'];
        if (isset($query['disposition']) && in_array($query['disposition'], $allowedDispositions)) {
            return $query['disposition'];
        } else {
            $siteSettings = $this->siteSettings();
            $disposition = $siteSettings->get('selection_disposition');
            return in_array($disposition, $allowedDispositions) ? $disposition : 'flat';
        }
    }

    protected function allowIndividualSelect(): bool
    {
        $siteSettings = $this->siteSettings();
        $viewHelpers = $this->viewHelpers();
        $allowIndividualSelect = $siteSettings->get('selection_individual_select', 'auto');
        return ($allowIndividualSelect !== 'no' && $allowIndividualSelect !== 'yes')
            ? $viewHelpers->has('bulkExport') || $viewHelpers->has('contactUs')
            : $allowIndividualSelect === 'yes';
    }

    protected function jsonErrorNotFound(): JsonModel
    {
        return $this->jSend(
            JSend::FAIL,
            null,
            $this->translate('Not found'),
            HttpResponse::STATUS_CODE_404
        );
    }

    protected function jsonInternalError(): JsonModel
    {
        return $this->jSend(
            JSend::ERROR,
            null,
            $this->translate('An internal error occurred.') // @translate
        );
    }

    protected function jsonPermissionDenied(): JsonModel
    {
        return $this->jSend(
            JSend::FAIL,
            null,
            $this->translate('Permission denied'), // @translate
            HttpResponse::STATUS_CODE_403
        );
    }

    /**
     * Check if adding resources would exceed the maximum limit.
     *
     * @param int $currentCount Current number of resources in the selection.
     * @param int $addCount Number of resources to add.
     * @return bool True if within limit, false if would exceed.
     */
    protected function checkSelectionLimit(int $currentCount, int $addCount = 1): bool
    {
        $maxResources = (int) $this->siteSettings()->get('selection_max_resources', 50);
        // 0 means unlimited.
        if ($maxResources === 0) {
            return true;
        }
        return ($currentCount + $addCount) <= $maxResources;
    }

    /**
     * Return a json error response for selection limit exceeded.
     */
    protected function jsonSelectionLimitExceeded(): JsonModel
    {
        $maxResources = (int) $this->siteSettings()->get('selection_max_resources', 50);
        return $this->jSend(
            JSend::FAIL,
            ['max_resources' => $maxResources],
            (new PsrMessage(
                'Selection limit reached. Maximum {max} resources allowed.', // @translate
                ['max' => $maxResources]
            ))->setTranslator($this->translator()),
            HttpResponse::STATUS_CODE_400
        );
    }
}
