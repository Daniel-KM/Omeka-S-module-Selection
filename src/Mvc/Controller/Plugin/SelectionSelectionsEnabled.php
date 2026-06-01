<?php declare(strict_types=1);

namespace Selection\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\SiteSettings;

class SelectionSelectionsEnabled extends AbstractPlugin
{
    protected SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * Whether multi-list selections are enabled for the current user context.
     */
    public function __invoke(?bool $isAuthenticated = null): bool
    {
        if ($isAuthenticated === null) {
            $isAuthenticated = (bool) $this->getController()->identity();
        }
        return (bool) $this->siteSettings->get(
            $isAuthenticated ? 'selection_selections_user' : 'selection_selections_visitor',
            $isAuthenticated
        );
    }
}
