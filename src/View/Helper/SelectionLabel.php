<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Settings\SiteSettings;

class SelectionLabel extends AbstractHelper
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
            $isAuthenticated = (bool) $this->getView()->identity();
        }
        return $isAuthenticated
            ? (string) $this->siteSettings->get('selection_label_user', 'Selection')
            : (string) $this->siteSettings->get('selection_label_visitor', 'Favorites');
    }
}
