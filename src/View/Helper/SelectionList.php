<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SelectionList extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-list';

    /**
     * Display a simple list of selected resources.
     *
     * @param array $options Options for the partial. Managed keys:
     * - template (string)
     * Other keys are passed to the template.
     */
    public function __invoke(array $options = []): string
    {
        $view = $this->getView();

        $plugins = $view->getHelperPluginManager();
        $siteSetting = $plugins->get('siteSetting');

        $user = $view->identity();

        // TODO Query in session is used only for pagination, not implemented yet.
        $query = $view->params()->fromQuery();

        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $plugins->get('selectionContainer')();

        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
        $selectionId = $selection['id'];

        /* // Useless here, or add a specific option.
        $allowIndividualSelect = $siteSetting('selection_individual_select', 'auto');
        $allowIndividualSelect = ($allowIndividualSelect !== 'no' && $allowIndividualSelect !== 'yes')
            ? $plugins->has('bulkExport') || $plugins->has('contactUs')
            : $allowIndividualSelect === 'yes';
         */
        $allowIndividualSelect = false;

        $vars = $options + [
            'site' => $view->layout()->site,
            'resource' => null,
            'user' => $user,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'disposition' => $siteSetting('selection_disposition', 'flat') ?: 'flat',
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
            'allowIndividualSelect' => $allowIndividualSelect,
        ];

        return $view->partial($options['template'] ?? self::PARTIAL_NAME, $vars);
    }
}
