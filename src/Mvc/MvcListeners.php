<?php declare(strict_types=1);

namespace Selection\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectSelection'],
            // More prioritary than Block Plus.
            -10
        );
    }

    /**
     * Redirect selection/show to search page with selection set as url query,
     * when wanted.
     *
     * Adapted:
     * @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSet()
     * @see \BlockPlus\Mvc\MvcListeners::handleItemSetShow()
     * @see \Selection\Mvc\MvcListeners::redirectSelection()
     */
    public function redirectSelection(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Settings\SiteSettings $siteSettings
         */

        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();

        if ($matchedRouteName !== 'site/selection-id'
            || $routeMatch->getParam('action') !== 'show'
        ) {
            return;
        }

        $services = $event->getApplication()->getServiceManager();
        $api = $services->get('Omeka\ApiManager');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $redirectSelection = $siteSettings->get('selection_share_redirect');
        if (!$redirectSelection) {
            return;
        }

        // Show the selection.

        $siteSlug = $routeMatch->getParam('site-slug');
        $selectionId = $routeMatch->getParam('id');

        // The check of the rights should be done earlier.
        try {
            $selection = $api->read('selections', ['id' => $selectionId])->getContent();
        } catch (\Exception $e) {
            $selection = null;
            $selectionId = 0;
        }

        if ($redirectSelection === 'items_browse') {
            $params = [
                '__NAMESPACE__' => 'Omeka\Controller\Site',
                '__CONTROLLER__' => 'Item',
                '__SITE__' => true,
                'controller' => 'Omeka\Controller\Site\Item',
                'action' => 'browse',
                'site-slug' => $siteSlug,
                'redirected' => 'selection',
                'selection' => $selection,
                'selection-id' => $selectionId,
            ];

            $routeMatch = new RouteMatch($params);
            $routeMatch->setMatchedRouteName('site/resource');
            $event->setRouteMatch($routeMatch);

            /** @var \Laminas\Stdlib\Parameters $query */
            $query = $event->getRequest()->getQuery();
            $query
                ->set('selection_id', $selectionId);
            return;
        }

        // Browse items for a a single selection via a search, because it can't
        // be managed inside search.

        // The search config may have been removed, so check and get the slug.
        // It should be cached by doctrine.
        // Or use setting "'advancedsearch_all_configs".
        if ($redirectSelection === 'advanced_search_site') {
            $searchConfigId = $siteSettings->get('advancedsearch_main_config');
            if (empty($searchConfigId)) {
                return;
            }
        } else {
            $searchConfigId = $redirectSelection;
        }

        try {
            $searchConfig = $api->read('search_configs', ['id' => $searchConfigId], [], ['responseContent' => 'resource'])->getContent();
            $searchConfigSlug = $searchConfig->getSlug();
        } catch (\Exception $e) {
            return;
        }

        $params = [
            '__NAMESPACE__' => 'AdvancedSearch\Controller',
            '__SITE__' => true,
            'controller' => \AdvancedSearch\Controller\SearchController::class,
            'action' => 'search',
            'site-slug' => $siteSlug,
            'id' => $searchConfigId,
            'page-slug' => $searchConfigSlug,
            'search-slug' => $searchConfigSlug,
            'redirected' => 'selection',
            'selection' => $selection,
            'selection-id' => $selectionId,
        ];
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName('search-page-' . $searchConfigSlug);
        $event->setRouteMatch($routeMatch);

        /** @var \Laminas\Stdlib\Parameters $query */
        $query = $event->getRequest()->getQuery();
        $query
            ->set('selection_id', $selectionId);
    }
}
