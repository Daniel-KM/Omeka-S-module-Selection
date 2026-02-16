<?php declare(strict_types=1);

namespace Selection\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;

class Selection extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/selection';

    public function getLabel()
    {
        return 'Selection'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['selection']['block_settings']['selection'];
        $blockFieldset = \Selection\Form\SelectionFieldset::class;

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $plugins = $view->getHelperPluginManager();
        $setting = $plugins->get('setting');

        $user = $view->identity();

        // TODO Query in session is used only for pagination, not implemented yet.
        $query = $view->params()->fromQuery();

        // Read selection from session. There is always at least one selection.
        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $view->selectionContainer();

        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
        $selectionId = $selection['id'];

        if (isset($query['disposition']) && in_array($query['disposition'], ['flat', 'grouped', 'hierarchy'])) {
            $disposition = $query['disposition'];
        } else {
            $disposition = $block->dataValue('disposition');
            $disposition = in_array($disposition, ['flat', 'grouped', 'hierarchy']) ? $disposition : 'flat';
        }

        $allowIndividualSelect = $block->dataValue('individual_select', 'auto');
        $allowIndividualSelect = ($allowIndividualSelect !== 'no' && $allowIndividualSelect !== 'yes')
            ? $plugins->has('bulkExport') || $plugins->has('contactUs')
            : $allowIndividualSelect === 'yes';

        $vars = [
            'block' => $block,
            'site' => $block->page()->site(),
            'user' => $user,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
            'mode' => $setting('selection_mode'),
            'disposition' => $disposition,
            'allowIndividualSelect' => $allowIndividualSelect,
        ];

        return $view->partial($templateViewScript, $vars);
    }
}
