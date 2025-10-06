<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2019-2025
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

    public function browseAction()
    {
        $user = $this->identity();
        if (!$user) {
            if ($this->siteSettings()->get('selection_disable_anonymous')) {
                throw new \Omeka\Api\Exception\PermissionDeniedException();
            }
            return $this->browseSession();
        }

        return $this->browseDb($user);
    }

    /**
     * Select resource(s) to add to a selection.
     */
    public function addAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->addDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->addSession($resourcesData['resources'], $resourcesData['is_multiple']);
    }

    /**
     * Delete resource(s) from a selection.
     */
    public function deleteAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
            return $this->jsonPermissionDenied();
        }

        $resourcesData = $this->requestedResources();
        if (empty($resourcesData['has_result'])) {
            return $this->jsonErrorNotFound();
        }

        return $user
            ? $this->deleteDb($resourcesData['resources'], $resourcesData['is_multiple'], $user)
            : $this->deleteSession($resourcesData['resources'], $resourcesData['is_multiple']);
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
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
            return $this->jsonPermissionDenied();
        }

        return $user
            ? $this->resetDb($user)
            : $this->resetSession();
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
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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
     * Move resource(s) between groups of a selection.
     */
    public function moveAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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

        $fullPath = "$path/$groupName";
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
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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
     * Move a group in a selection.
     */
    public function moveGroupAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jsonErrorNotFound();
        }

        $user = $this->identity();
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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
        if (!$user && $this->siteSettings()->get('selection_disable_anonymous')) {
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
        $id = $params->fromQuery('id');
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
     *
     * Adapted in:
     * @see \Selection\View\Helper\SelectionContainer::normalizeResources()
     * @see \Selection\Controller\Site\AbstractSelectionController::normalizeResource()
     */
    protected function normalizeResource(
        AbstractResourceEntityRepresentation $resource,
        bool $isSelected,
        ?int $selectionId = null
    ): array {
        static $siteSlug;
        static $defaultLang;
        static $headingTerm;
        static $bodyTerm;
        static $url;

        if (is_null($siteSlug)) {
            $viewHelpers = $this->viewHelpers();
            $siteSlug = $this->currentSite()->slug();
            $lang = $viewHelpers->get('lang')();
            $siteSettings = $this->siteSettings();
            $filterLocale = (bool) $siteSettings->get('filter_locale_values');
            $headingTerm = $siteSettings->get('browse_heading_property_term');
            $bodyTerm = $siteSettings->get('browse_body_property_term');
            $defaultLang = $filterLocale ? [$lang, ''] : null;
            $url = $this->url();
        }

        $resourceId = $resource->id();
        $title = (string) $resource->displayTitle(null, $defaultLang);
        $description = (string) $resource->displayDescription(null, $defaultLang);
        $heading = $headingTerm ? (string) $resource->value($headingTerm, ['default' => $title]) : $title;
        $body = $bodyTerm ? (string) $resource->value($bodyTerm, ['default' => $description]) : $description;

        return [
            'id' => $resource->id(),
            'type' => $resource->getControllerName(),
            'resource_name' => $resource->resourceName(),
            'selection_id' => (int) $selectionId,
            'url' => $resource->siteUrl($siteSlug, true),
            'url_remove' => $selectionId
                ? $url->fromRoute("site/selection-id", ['site-slug' => $siteSlug, 'action' => 'delete', 'id' => $selectionId], ['query' => ['id' => $resourceId]])
                : $url->fromRoute("site/selection", ['site-slug' => $siteSlug, 'action' => 'delete'], ['query' => ['id' => $resourceId]]),
            // String is required to avoid error in container when the title is
            // a resource.
            'title' => $title,
            'description' => $description,
            'heading' => $heading,
            'body' => $body,
            'value' => $isSelected ? 'selected' : 'unselected',
        ];
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
}
