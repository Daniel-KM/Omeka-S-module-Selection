<?php declare(strict_types=1);

namespace Selection\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class Selection implements LinkInterface
{
    public function getName()
    {
        return 'Selection'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/label';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return isset($data['label']) && trim($data['label']) !== ''
            ? $data['label']
            : 'Selection'; // @translate
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        // Personal area is always under /guest/selection (browse), regardless
        // of module Guest state. Anonymous visitors are served from session.
        return [
            'label' => $data['label'],
            'route' => 'site/guest/selection',
            'class' => 'selection-link',
            'params' => [
                'site-slug' => $site->slug(),
            ],
            'resource' => 'Selection\Controller\Site\Guest',
            'privilege' => 'browse',
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => isset($data['label']) ? trim($data['label']) : '',
        ];
    }
}
