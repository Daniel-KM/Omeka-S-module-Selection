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
        /**
         * @var \Omeka\Entity\User $user
         * @var \Omeka\Module\Manager $moduleManager
         */
        $services = $site->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Guest');
        $isGuestActive = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        if ($user && $isGuestActive) {
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
        return [
            'label' => $data['label'],
            'route' => 'site/selection',
            'class' => 'selection-link',
            'params' => [
                'site-slug' => $site->slug(),
            ],
            'resource' => 'Selection\Controller\Site\Selection',
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
