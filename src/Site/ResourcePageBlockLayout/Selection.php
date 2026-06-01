<?php declare(strict_types=1);

namespace Selection\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class Selection implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Selection'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
            'item_sets',
            'digital_objects',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $plugins = $view->getHelperPluginManager();

        $user = $view->identity();

        // TODO Query in session is used only for pagination, not implemented yet.
        $query = $view->params()->fromQuery();

        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $plugins->get('selectionContainer')();

        $selectionId = empty($query['selection_id']) ? 0 : (int) $query['selection_id'];
        $selection = $selectionContainer->selections[$selectionId] ?? reset($selectionContainer->selections);
        $selectionId = $selection['id'];

        return $view->partial('common/resource-page-block-layout/selection', [
            'site' => $view->layout()->site,
            'resource' => $resource,
            'user' => $user,
            'selectionId' => $selectionId,
            'selections' => $selectionContainer->selections,
            'records' => $selectionContainer->records,
            'isGuestActive' => $plugins->has('guestWidget'),
            'isSession' => !$user,
        ]);
    }
}
