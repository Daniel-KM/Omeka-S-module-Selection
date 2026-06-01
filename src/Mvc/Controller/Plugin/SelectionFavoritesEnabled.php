<?php declare(strict_types=1);

namespace Selection\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\SiteSettings;

class SelectionFavoritesEnabled extends AbstractPlugin
{
    protected SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * Whether the favorites (single starred basket) feature is enabled for the
     * current user context.
     */
    public function __invoke(?bool $isAuthenticated = null): bool
    {
        if ($isAuthenticated === null) {
            $isAuthenticated = (bool) $this->getController()->identity();
        }
        return (bool) $this->siteSettings->get(
            $isAuthenticated ? 'selection_favorites_user' : 'selection_favorites_visitor',
            $isAuthenticated
        );
    }
}
