<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class SelectionButton extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-button';

    /**
     * Create a button to add or remove a resource to/from the selection.
     *
     * @param array $options Options for the partial. Managed keys:
     * - selectionId (int)
     * - action: "include" or "exclude". If not specified, the action is "toggle".
     * - template (string)
     *
     * Adapted:
     * @see \Selection\View\Helper\SelectionButton
     * @see \Selection\View\Helper\SelectionButtonToGroup
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        static $first = true;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $siteSetting = $plugins->get('siteSetting');

        $user = $view->identity();

        if ($first) {
            $assetUrl = $plugins->get('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/common-dialog.css', 'Common'))
                ->appendStylesheet($assetUrl('css/selection.css', 'Selection'));
            $view->headScript()
                ->appendFile($assetUrl('js/common-dialog.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('js/selection.js', 'Selection'), 'text/javascript', ['defer' => 'defer']);
            $first = false;
        }

        // TODO Query in session is used only for pagination, not implemented yet.
        $query = $view->params()->fromQuery();

        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $plugins->get('selectionContainer')();

        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
        $selectionId = $selection['id'];

        $site = $plugins->get('currentSite')();
        $url = $plugins->get('url');
        $siteSlug = $site->slug();

        $resourceId = $resource->id();

        $defaultOptions = [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'disposition' => $siteSetting('selection_disposition', 'flat') ?: 'flat',
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
            'value' => isset($selectionContainer->records[$selectionId][$resourceId]) ? 'selected' : 'unselected',
            'action' => $options['action'] ?? 'toggle',
            'urlButton' => $selectionId
                ? $url('site/selection-id', ['site-slug' => $siteSlug, 'action' => $options['action'] ?? 'toggle', 'id' => $selectionId], ['query' => ['id' => $resourceId]])
                : $url('site/selection', ['site-slug' => $siteSlug, 'action' => $options['action'] ?? 'toggle'], ['query' => ['id' => $resourceId]]),
            'template' => self::PARTIAL_NAME,
        ];
        $options += $defaultOptions;

        $template = $options['template'];
        unset($options['template']);

        return $view->partial($template, $options);
    }
}
