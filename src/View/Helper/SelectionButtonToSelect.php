<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;

class SelectionButtonToSelect extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-button-to-select';

    /**
     * Get a button with a selector to include a resource in user selections.
     *
     * @param array $options Options for the partial. Managed keys:
     * - template (string)
     * See below for more details.
     *
     * Adapted:
     * @see \Selection\View\Helper\SelectionButton
     * @see \Selection\View\Helper\SelectionButtonToGroup
     * @see \Selection\View\Helper\SelectionButtonToSelect
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

        $mode = $view->selectionEffectiveMode();
        $disposition = $siteSetting('selection_disposition', 'flat') ?: 'flat';

        if ($user) {
            $selectionId = null;
            [$selecteds, $valueOptions] = array_values($this->prepareValueOptions($resource, $user, $mode, $disposition));
        } else {
            // TODO Make selection container manage multiple selections for anonymous.
            // TODO Query in session is used only for pagination, not implemented yet.
            $query = $view->params()->fromQuery();
            /** @var \Laminas\Session\Container $selectionContainer */
            $selectionContainer = $plugins->get('selectionContainer')();
            $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
            $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
            $selectionId = $selection['id'];
            [$selecteds, $valueOptions] = array_values($this->prepareValueOptionsFromContainer($resource, $selectionContainer, $mode, $disposition));
        }

        $defaultOptions = [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
            'valueOptions' => $valueOptions,
            'selecteds' => $selecteds,
            'mode' => $mode,
            'disposition' => $disposition,
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
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

    protected function prepareValueOptions(AbstractResourceEntityRepresentation $resource, ?User $user, string $mode, string $disposition)
    {
        $result = [
            'selecteds' => [],
            'value_options' => [],
        ];

        if (!$user) {
            return $result;
        }

        /**
         * @var \Omeka\View\Helper\Api $api
         * @var \Selection\Api\Representation\SelectionRepresentation $selection
         */
        $api = $this->getView()->api();
        $resourceId = $resource->id();

        if ($disposition === 'hierarchy' || $disposition === 'grouped') {
            $selections = $api->search('selections', ['owner_id' => $user->getId()])->getContent();
            if ($mode === 'simple' && count($selections) > 1) {
                $selections = [reset($selections)];
            }
            foreach ($selections as $selection) {
                $selectionId = $selection->id();
                $groupLabel = $selection->label();
                $structure = $selection->structure();
                $groupOptions = [];
                foreach ($structure as $element) {
                    $path = empty($element['path']) || $element['path'] === '/' ? '/' : $element['path'] . '/';
                    $value = $selectionId . ':' . $path . $element['id'];
                    $labelIndent = str_repeat('– ', max(substr_count($path, '/') - 1, 0));
                    $elementLabel = $element['label'] ?? $element['id'];
                    $groupOptions[$value] = $labelIndent . $elementLabel;
                    if (!empty($element['selecteds']) && in_array($resourceId, $element['selecteds'], true)) {
                        $result['selecteds'][$value] = $value;
                    }
                }
                $result['value_options'][$selectionId] = [
                    'label' => $groupLabel,
                    'options' => $groupOptions,
                ];
            }
        } else {
            $selections = $api->search(
                'selections',
                ['owner_id' => $user->getId(), 'return_scalar' => 'label']
            )->getContent();
            $result['selecteds'] = $api->search(
                'selection_resources',
                ['resource_id' => $resourceId, 'selection_id' => array_keys($selections), 'return_scalar' => 'selection']
            )->getContent();
            $result['selecteds'] = array_combine($result['selecteds'], $result['selecteds']);
            $result['value_options'] = $mode === 'simple' && count($selections) > 1
                ? array_slice($selections, 0, 1, true)
                : $selections;
        }

        return $result;
    }

    protected function prepareValueOptionsFromContainer(AbstractResourceEntityRepresentation $resource, $selectionContainer, string $mode, string $disposition)
    {
        $result = [
            'selecteds' => [],
            'value_options' => [],
        ];

        $selections = $selectionContainer->selections ?? [];
        $resourceId = $resource->id();

        if ($disposition === 'hierarchy' || $disposition === 'grouped') {
            if ($mode === 'simple' && count($selections) > 1) {
                $selections = [reset($selections)];
            }
            foreach ($selections as $selection) {
                $selectionId = $selection['id'];
                $groupLabel = $selection['label'] ?? ('Selection ' . $selectionId);
                $structure = $selection['structure'] ?? [];
                $groupOptions = [];
                foreach ($structure as $element) {
                    $path = empty($element['path']) || $element['path'] === '/' ? '/' : $element['path'] . '/';
                    $value = $selectionId . ':' . $path . $element['id'];
                    $labelIndent = str_repeat('– ', max(substr_count($path, '/') - 1, 0));
                    $elementLabel = $element['label'] ?? $element['id'];
                    $groupOptions[$value] = $labelIndent . $elementLabel;
                    if (!empty($element['selecteds']) && in_array($resourceId, $element['selecteds'], true)) {
                        $result['selecteds'][$value] = $value;
                    }
                }
                $result['value_options'][$selectionId] = [
                    'label' => $groupLabel,
                    'options' => $groupOptions,
                ];
            }
        } else {
            $labels = [];
            foreach ($selections as $selection) {
                $labels[$selection['id']] = $selection['label'] ?? ('Selection ' . $selection['id']);
                if (!empty($selection['selecteds']) && in_array($resourceId, $selection['selecteds'], true)) {
                    $result['selecteds'][$selection['id']] = $selection['id'];
                }
            }
            $result['value_options'] = ($mode === 'simple' && count($labels) > 1)
                ? array_slice($labels, 0, 1, true)
                : $labels;
        }

        return $result;
    }
}
