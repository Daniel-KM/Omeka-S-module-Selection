<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SelectionBrowse extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-browse';

    /**
     * Display the selections, that may be filtered.
     *
     * Adapted from block browse-preview.
     * @see \Omeka\Site\BlockLayout\BrowsePreview
     *
     * @param array|null $query Passing null will use the url arguments.
     * @param array $options Options for the partial. Managed keys:
     * - heading (string|null): Default when null is "User selections".
     * - linkText (string)
     * - components (array): thumbnail/resource-heading/resource-body/resource-owner
     * - linkToSearch (bool): Whether to link to the search page. Default false.
     * - searchConfig (SearchConfig): a searchConfig of module AdvancedSearch,
     *   else the default one.
     * - template (string)
     * Other keys are passed to the template.
     */
    public function __invoke(?array $query = [], array $options = []): string
    {
        $view = $this->getView();

        $plugins = $view->getHelperPluginManager();
        $api = $plugins->get('api');
        $translate = $plugins->get('translate');

        $site = $view->layout()->site;
        $user = $view->identity();

        $query ??= $view->params()->fromQuery();
        if (!$user) {
            $query['is_public'] = true;
        }

        $selections = $api->search('selections', $query)->getContent();

        $heading = $options['heading'] ?? $translate('User selections'); // @translate

        $vars = [
            'site' => $site,
            'selections' => $selections,
            'query' => $query,
            'heading' => $heading,
            'components' => empty($options['components']) ? [
                'thumbnail',
                'resource-heading',
                'resource-body',
                'resource-owner',
            ] : $options['components'],
            'linkText' => $options['linkText'] ?? null,
            'user' => $user,
            'linkToSearch' => !empty($options['linkToSearch']),
            'searchConfig' => $options['searchConfig'] ?? null,
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
        ] + $options;


        if ($vars['linkToSearch'] && empty($vars['searchConfig']) && $plugins->has('getSearchConfig')) {
            $vars['searchConfig'] = $plugins->get('getSearchConfig')();
        }

        return $view->partial($options['template'] ?? self::PARTIAL_NAME, $vars);
    }
}
