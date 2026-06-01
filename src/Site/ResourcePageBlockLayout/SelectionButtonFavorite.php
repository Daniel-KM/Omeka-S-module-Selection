<?php declare(strict_types=1);

namespace Selection\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class SelectionButtonFavorite implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Selection: favorite button'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return [
            'items',
            'media',
            'item_sets',
            'digital_objects',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        return $view->partial('common/resource-page-block-layout/selection-button-favorite', [
            'site' => $view->layout()->site,
            'resource' => $resource,
        ]);
    }
}
