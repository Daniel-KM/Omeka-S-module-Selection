<?php declare(strict_types=1);

namespace Selection;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Assertion\OwnsEntityAssertion;

/**
 * Selection.
 *
 * @copyright Biblibre, 2016
 * @copyright Daniel Berthereau 2019-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    /**
     * @var array|null
     */
    protected $selectionResourceToReindex;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /**
         * @var \Omeka\Permissions\Acl $acl
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Since Omeka 1.4, modules are ordered, so Guest come before Selection.
        // Nevertheless, it is not available during upgrade of Guest, so add it
        // to avoid issue.
        // See \Guest\Module::onBootstrap(). Manage other roles too: contributor, etc.
        if (class_exists('Guest\Module', false) || class_exists('GuestRole\Module', false)) {
            $hasGuest = true;
            if (!$acl->hasRole('guest')) {
                $acl->addRole('guest');
            }
        } else {
            $hasGuest = false;
        }
        if (class_exists('GuestPrivate\Module', false)) {
            if (!$acl->hasRole('guest_private')) {
                $acl->addRole('guest_private');
            }
            if (!$acl->hasRole('guest_private_site')) {
                $acl->addRole('guest_private_site');
            }
        }

        $roles = $acl->getRoles();

        // Acl should be specified, because selection is not a resource.
        $entityManagerFilters = $services->get('Omeka\EntityManager')->getFilters();
        $entityManagerFilters->enable('selection_visibility')->setServiceLocator($services);

        // TODO How to make a distinction between public and admin roles? Which new rule? So guests.

        // The anonymous mode is managed via session. So here rights are not for
        // anonymous users.

        // Public can see public selections.
        // Users can see own selections, even private.
        // Admin can see all selections (not managed currently).

        $adminRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
        ];
        $baseRoles = [
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        if ($hasGuest) {
            $baseRoles[] = 'guest';
        }

        // Rights on visibility public/private are added via db filter.

        $acl
            // Every user can create, read, update and delete own and public.
            // Entity/Adapter.
            ->allow(
                null,
                [
                    Entity\Selection::class,
                    Entity\SelectionResource::class,
                ],
                ['read']
            )
            ->allow(
                null,
                [
                    Api\Adapter\SelectionAdapter::class,
                    Api\Adapter\SelectionResourceAdapter::class,
                ],
                ['search', 'read']
            )
            ->allow(
                $baseRoles,
                [
                    Entity\Selection::class,
                    Entity\SelectionResource::class,
                ],
                ['create', 'read', 'update', 'delete'],
                new OwnsEntityAssertion
            )
            ->allow(
                $adminRoles,
                [
                    Entity\Selection::class,
                    Entity\SelectionResource::class,
                ],
                ['create', 'read', 'update', 'delete', 'view-all'],
            )
            ->allow(
                $baseRoles,
                [
                    Api\Adapter\SelectionAdapter::class,
                    Api\Adapter\SelectionResourceAdapter::class,
                ],
                ['create', 'search', 'read', 'update', 'delete']
            )
            ->allow(
                $adminRoles,
                [
                    Api\Adapter\SelectionAdapter::class,
                    Api\Adapter\SelectionResourceAdapter::class,
                ],
                ['create', 'search', 'read', 'update', 'delete', 'view-all', 'validate'],
            )
            // Controller.
            ->allow(
                null,
                [
                    'Selection\Controller\Site\Selection',
                ],
                [
                    // TODO Clarify acl rule for browse.
                    'index',
                    'browse',
                    'show',
                    'view',
                ]
            )
            ->allow(
                $roles,
                [
                    'Selection\Controller\Site\Selection',
                    'Selection\Controller\Site\Guest',
                ],
                [
                    'index',
                    'browse',
                    'show',
                    'view',
                    'add',
                    'edit',
                    'delete',
                    'include',
                    'exclude',
                    'move',
                    'reset',
                    'toggle',
                    'update',
                    'add-group',
                    'rename-group',
                    'move-group',
                    'delete-group',
                ]
            )
            ->allow(
                $adminRoles,
                [
                    'Selection\Controller\Site\Selection',
                ],
                [
                    'validate',
                ]
            )
        ;
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.73')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.73'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Basket');
        if ($module) {
            $message = new PsrMessage(
                'The module Basket is present. Upgrade from it was removed since version 3.4.7. Install the previous version if you want to upgrade it.', // @translate
            );
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning($message);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $adapters = [
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            // Search selected resources with "selection_id=xxx&selection_path=yyy"
            // or "selection_id_path=xxx:yyy".
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'handleApiSearchQuery']
            );
        }
        $controllers = [
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'handleViewAdvancedSearch']
            );
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'handleSearchFilters']
            );
        }

        // Add events in order to index selections in solr.
        $sharedEventManager->attach(
            \Selection\Api\Adapter\SelectionResourceAdapter::class,
            'api.create.post',
            [$this, 'reindexResourceFromSelectionChange'],
            -100
        );
        // Remember target resource before deletion of selection entry.
        $sharedEventManager->attach(
            \Selection\Api\Adapter\SelectionResourceAdapter::class,
            'api.delete.pre',
            [$this, 'rememberSelectionResourceBeforeDelete'],
            -100
        );
        $sharedEventManager->attach(
            \Selection\Api\Adapter\SelectionResourceAdapter::class,
            'api.delete.post',
            [$this, 'reindexResourceFromSelectionChange'],
            -100
        );

        // Display block in resources pages for old themes.
        // The site is not set yet, so checks are done in method.
        foreach ([
            'selection_placement_button' => 'handleShowSelectionButton',
            'selection_placement_list' => 'handleShowSelectionList',
        ] as $method) {
            $sharedEventManager->attach(
                'Omeka\Controller\Site\Item',
                'view.show.before',
                [$this, $method]
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Site\Media',
                'view.show.before',
                [$this, $method]
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Site\ItemSet',
                'view.show.before',
                [$this, $method]
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Site\Item',
                'view.show.after',
                [$this, $method]
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Site\Media',
                'view.show.after',
                [$this, $method]
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Site\ItemSet',
                'view.show.after',
                [$this, $method]
            );
        }

        // No need to listen user.logout: session is automatically destroyed.

        // Guest integration.
        $sharedEventManager->attach(
            \Guest\Controller\Site\GuestController::class,
            'guest.widgets',
            [$this, 'handleGuestWidgets']
        );

        // Site config.
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

    /**
     * Helper to build search queries.
     */
    public function handleApiSearchQuery(Event $event): void
    {
        /**
         * @var \Doctrine\ORM\QueryBuilder $qb
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter
         * @var \Omeka\Api\Request $request
         * @var array $query
         */
        $request = $event->getParam('request');
        $query = $request->getContent();

        // TODO Manage is public.

        if (isset($query['selection_id_path']) && $query['selection_id_path']) {
            [$selectionId, $selectionPath] = explode(':', $query['selection_id_path'], 2) + [1 => ''];
            $selectionIds = is_numeric($selectionId) ? [(int) $selectionId] : '-';
            $selectionPaths = [$selectionPath];
        } else {
            if (isset($query['selection_id']) && $query['selection_id'] !== '' && $query['selection_id'] !== []) {
                $selectionIds = is_array($query['selection_id'])
                    ? array_values(array_unique(array_map('intval', $query['selection_id'])))
                    : (is_numeric($query['selection_id']) ? [(int) $query['selection_id']] : '-');
            }
            if (isset($query['selection_path']) && $query['selection_path'] !== '' && $query['selection_path'] !== []) {
                $selectionPaths = is_array($query['selection_path'])
                    ? array_values(array_unique(array_map('strval', $query['selection_path'])))
                    : [(string) $query['selection_path']];
            }
        }

        if (empty($selectionIds)) {
            return;
        }

        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $expr = $qb->expr();

        // Don't return any result when the selection is invalid.
        if ($selectionIds === '-') {
            $qb
                ->innerJoin(
                    \Selection\Entity\SelectionResource::class,
                    'selection_resource',
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    "selection_resource.selection = 0"
                );
            return;
        }

        $qb
            ->leftJoin(
                \Selection\Entity\SelectionResource::class,
                'selection_resource',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'selection_resource.resource = omeka_root.id'
            );

        // Resources that are not in any selection.
        if ($selectionIds === [0]) {
            $qb
                ->andWhere($expr->isNull('selection_resource.resource'));
            return;
        }

        $selectionIdsAlias = $adapter->createAlias();
        if (count($selectionIds) === 1) {
            // Resources that are in a specific selection.
            $qb
                ->andWhere('selection_resource.selection = :' . $selectionIdsAlias)
                ->setParameter($selectionIdsAlias, reset($selectionIds), ParameterType::INTEGER);
        } else {
            // Resources that are in multiple selections (with or).
            $qb
                ->andWhere($expr->in('selection_resource.selection', ':' . $selectionIdsAlias))
                ->setParameter($selectionIdsAlias, $selectionIds, Connection::PARAM_INT_ARRAY);
        }

        // Search for paths.
        if (empty($selectionPaths)) {
            return;
        }

        $escapeSqlLike = fn ($string) => strtr((string) $string, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);

        // TODO For now, only one path is managed. Anyway require like for now.
        // The simple like is enough, because a resource can have only one path.
        $selectionPath = reset($selectionPaths);
        $preparedPath = '%' . $escapeSqlLike('"id":' . json_encode($selectionPath)) . '%';

        $selectionPathAlias = $adapter->createAlias();
        $qb
            ->innerJoin(
                \Selection\Entity\Selection::class,
                'selection',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'selection.id = selection_resource.selection'
            )
            ->andWhere($expr->like('selection.structure', ':' . $selectionPathAlias))
            ->setParameter($selectionPathAlias, $preparedPath, ParameterType::STRING);
    }

    public function handleViewAdvancedSearch(Event $event): void
    {
        $partials = $event->getParam('partials');
        $partials[] = 'common/advanced-search/selections';
        $event->setParam('partials', $partials);
    }

    /**
     * Complete the list of search filters for the browse page.
     */
    public function handleSearchFilters(Event $event): void
    {
        $filters = $event->getParam('filters');
        $query = $event->getParam('query', []);

        if (isset($query['selection_id_path']) && $query['selection_id_path']) {
            [$selectionId, $selectionPath] = explode(':', $query['selection_id_path'], 2) + [1 => ''];
            $selectionIds = is_numeric($selectionId) ? [(int) $selectionId] : '-';
            $selectionPaths = [$selectionPath];
        } else {
            if (isset($query['selection_id']) && $query['selection_id'] !== '' && $query['selection_id'] !== []) {
                $selectionIds = is_array($query['selection_id'])
                    ? array_values(array_unique(array_map('intval', $query['selection_id'])))
                    : (is_numeric($query['selection_id']) ? [(int) $query['selection_id']] : '-');
            }
            if (isset($query['selection_path']) && $query['selection_path'] !== '' && $query['selection_path'] !== []) {
                $selectionPaths = is_array($query['selection_path'])
                    ? array_values(array_unique(array_map('strval', $query['selection_path']))) : [
                    (string) $query['selection_path']
                ];
            }
        }

        if (empty($selectionIds)) {
            return;
        }

        // TODO Manage selection paths.

        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $filterLabel = $translator->translate('Selection'); // @translate

        if ($selectionIds === '-') {
            $filters[$filterLabel][] = $translator->translate('Invalid selection'); // @translate
            $event->setParam('filters', $filters);
        } elseif ($selectionIds === [0]) {
            $filters[$filterLabel][] = $translator->translate('No selection'); // @translate
            $event->setParam('filters', $filters);
        } elseif ($selectionIds) {
            $api = $services->get('Omeka\ApiManager');
            $selectionLabels = $api->search('selections', ['id' => $selectionIds], ['returnScalar' => 'label'])->getContent();
            $filters[$filterLabel] = array_replace(array_combine($selectionIds, $selectionIds), $selectionLabels);
            $event->setParam('filters', $filters);
        }
    }

    /**
     * Store the underlying resource info before selection entry removal.
     */
    public function rememberSelectionResourceBeforeDelete(Event $event): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $selectionEntryId = $request ? (int) $request->getId() : 0;
        if (!$selectionEntryId) {
            $this->selectionResourceToReindex = null;
            return;
        }

        try {
            // Read the selection entry to capture its target resource.
            $selectionEntry = $api->read('selection_resources', ['id' => $selectionEntryId])->getContent();
            $resourceType = null;
            $resourceId = null;
            if (method_exists($selectionEntry, 'resourceType')) {
                $resourceType = $selectionEntry->resourceType();
            }
            if (method_exists($selectionEntry, 'resourceId')) {
                $resourceId = $selectionEntry->resourceId();
            }
            // Fallbacks if the representation exposes a linked resource.
            if ((!$resourceType || !$resourceId) && method_exists($selectionEntry, 'resource')) {
                $res = $selectionEntry->resource();
                if (is_object($res)) {
                    if (!$resourceType && method_exists($res, 'resourceName')) {
                        $resourceType = $res->resourceName();
                    }
                    if (!$resourceId && method_exists($res, 'id')) {
                        $resourceId = (int) $res->id();
                    }
                }
            }

            $this->selectionResourceToReindex = ($resourceType && $resourceId)
                ? ['resource_type' => $resourceType, 'resource_id' => (int) $resourceId]
                : null;
        } catch (\Exception $e) {
            $this->selectionResourceToReindex = null;
        }
    }

    /**
     * Trigger a minimal update on the underlying resource to emit api.update.post.
     */
    public function reindexResourceFromSelectionChange(Event $event): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $operation = $request ? $request->getOperation() : null;

        $resourceType = null;
        $resourceId = null;

        // Prefer values from request content for create.
        $content = $request ? ($request->getContent() ?: []) : [];
        if (!empty($content['resource_type'])) {
            $resourceType = (string) $content['resource_type'];
        }
        if (!empty($content['resource_id'])) {
            $resourceId = (int) $content['resource_id'];
        }

        // On delete, use the stored info from pre-delete.
        if ($operation === 'delete' && !$resourceId) {
            if ($this->selectionResourceToReindex) {
                $resourceType = $resourceType ?: $this->selectionResourceToReindex['resource_type'] ?? null;
                $resourceId = $resourceId ?: $this->selectionResourceToReindex['resource_id'] ?? null;
            }
            $this->selectionResourceToReindex = null;
        }

        // Fallback: try to infer from response content.
        if ((!$resourceType || !$resourceId) && $response && method_exists($response, 'getContent')) {
            $selectionEntry = $response->getContent();
            if (is_object($selectionEntry)) {
                if (!$resourceType && method_exists($selectionEntry, 'resourceType')) {
                    $resourceType = $selectionEntry->resourceType();
                }
                if (!$resourceId && method_exists($selectionEntry, 'resourceId')) {
                    $resourceId = (int) $selectionEntry->resourceId();
                }
                if ((!$resourceType || !$resourceId) && method_exists($selectionEntry, 'resource')) {
                    $res = $selectionEntry->resource();
                    if (is_object($res)) {
                        if (!$resourceType && method_exists($res, 'resourceName')) {
                            $resourceType = $res->resourceName();
                        }
                        if (!$resourceId && method_exists($res, 'id')) {
                            $resourceId = (int) $res->id();
                        }
                    }
                }
            }
        }

        if (!$resourceType || !$resourceId) {
            return;
        }

        // Normalize resource type to Omeka API resource names.
        $map = [
            'item' => 'items',
            'items' => 'items',
            'item_set' => 'item_sets',
            'item_sets' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'resources' => 'resources',
        ];
        $resourceType = $map[$resourceType] ?? $resourceType;

        try {
            // Empty partial update triggers api.update.post on the resource adapter.
            $api->update($resourceType, $resourceId, [], [], ['isPartial' => true]);
        } catch (\Exception $e) {
            $logger->err('Selection: unable to trigger reindex for {type} #{id}: {message}', [
                'type' => $resourceType,
                'id' => $resourceId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function handleShowSelectionButton(Event $event): void
    {
        /**
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Omeka\Entity\User $user
         * @var \Laminas\View\Renderer\PhpRenderer $view
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         * @see \Selection\View\Helper\SelectionButton
         */

        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        $disableAnonymous = (bool) $siteSettings->get('selection_disable_anonymous');
        if ($disableAnonymous && !$user) {
            return;
        }

        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();

        $selectables = $siteSettings->get('selection_selectable_resources', ['items']);
        if (!in_array($resourceName, $selectables)) {
            return;
        }

        $placements = $siteSettings->get('selection_placement_button', []);
        if (in_array('before/' . $resourceName, $placements)
            || in_array('after/' . $resourceName, $placements)
        ) {
            echo $view->selectionButton($resource);
        }
    }

    public function handleShowSelectionList(Event $event): void
    {
        /**
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Omeka\Entity\User $user
         * @var \Laminas\View\Renderer\PhpRenderer $view
         * @see \Selection\View\Helper\SelectionList
         */

        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        $disableAnonymous = (bool) $siteSettings->get('selection_disable_anonymous');
        if ($disableAnonymous && !$user) {
            return;
        }

        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();

        $placements = $siteSettings->get('selection_placement_list', []);
        if (in_array('before/' . $resourceName, $placements)
            || in_array('after/' . $resourceName, $placements)
        ) {
            echo $view->selectionList();
        }
    }

    public function handleGuestWidgets(Event $event): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ViewHelperManager');
        $partial = $plugins->get('partial');
        $translate = $plugins->get('translate');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $widget = [];
        $widget['label'] = $siteSettings->get('selection_label', $translate('Selection')); // @translate
        $widget['content'] = $partial('guest/site/guest/widget/selection');

        $widgets = $event->getParam('widgets');
        $widgets['selection'] = $widget;
        $event->setParam('widgets', $widgets);
    }
}
