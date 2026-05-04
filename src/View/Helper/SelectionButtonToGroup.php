<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class SelectionButtonToGroup extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-button-to-group';

    /**
     * Get a button with a selector to update named groups or a single resource.
     *
     * @param array $options Options for the partial. Managed keys:
     * - selectionId (int)
     * - template (string)
     * See below for more details.
     *
     * Adapted:
     * @see \Selection\View\Helper\SelectionButton
     * @see \Selection\View\Helper\SelectionButtonToGroup
     * @see \Selection\View\Helper\SelectionButtonToSelect
     *
     * @todo Deprecate in favor of selectionButtonToSelect when container will be managed.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        static $first = true;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $translate = $plugins->get('translate');
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

        $site = $plugins->get('currentSite')();
        $url = $plugins->get('url');
        $siteSlug = $site->slug();

        $resourceId = $resource->id();

        // TODO Manage mode in selection button to group?
        // This helper cannot be used in modes standard or nested.
        $mode = $view->selectionMode();
        if ($mode !== 'simple') {
            return '';
        }

        $disposition = $siteSetting('selection_disposition', 'flat') ?: 'flat';

        // TODO Query in session is used only for pagination, not implemented yet.
        $query = $view->params()->fromQuery();

        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $plugins->get('selectionContainer')();

        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
        $selectionId = $selection['id'];

        $defaultOptions = [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'mode' => $mode,
            'disposition' => $disposition,
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
            'value' => isset($selectionContainer->records[$selectionId][$resourceId]) ? 'selected' : 'unselected',
            'action' => 'update',
            'urlButton' => $selectionId
                ? $url("site/selection-id", ['site-slug' => $siteSlug, 'action' => 'update', 'id' => $selectionId], ['query' => ['id' => $resourceId]])
                : $url("site/selection", ['site-slug' => $siteSlug, 'action' => 'update'], ['query' => ['id' => $resourceId]]),
            'urlSelections' => $url('site/selection', ['site-slug' => $siteSlug, 'action' => null]),
            'urlSelection' => $url('site/selection-id', ['site-slug' => $siteSlug, 'action' => null, 'id' => $selectionId ?: '0']),
            'messageSuccess' => $translate('The selection has been updated.'), // @translate
            'messageError' => $translate('The selection could not be updated.'), // @translate
            'placeholder' => $translate('Choose a selection'), // @translate
            'allowStoreNoGroup' => !empty($options['allowStoreNoGroup']),
            'template' => self::PARTIAL_NAME,
        ];
        $options += $defaultOptions;

        $template = $options['template'];
        unset($options['template']);

        return $view->partial($template, $options);
    }
}
