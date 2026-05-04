<?php declare(strict_types=1);

namespace Selection\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\SiteSettings;

class SelectionLabel extends AbstractPlugin
{
    protected SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * Returns the configured selection label for the current user context.
     */
    public function __invoke(?bool $isAuthenticated = null): string
    {
        if ($isAuthenticated === null) {
            $isAuthenticated = (bool) $this->getController()->identity();
        }
        return $isAuthenticated
            ? (string) $this->siteSettings->get('selection_label_user', 'Selection')
            : (string) $this->siteSettings->get('selection_label_visitor', 'Favorites');
    }
}
