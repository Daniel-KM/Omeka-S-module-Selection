<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Settings\SiteSettings;

class SelectionEffectiveMode extends AbstractHelper
{
    protected SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * Compute a legacy-style mode string from the two-axis configuration so
     * that views relying on $mode ('none'/'simple'/'standard'/'nested') keep
     * working.
     */
    public function __invoke(?bool $isAuthenticated = null): string
    {
        if ($isAuthenticated === null) {
            $isAuthenticated = (bool) $this->getView()->identity();
        }
        $favorites = (bool) $this->siteSettings->get(
            $isAuthenticated ? 'selection_favorites_user' : 'selection_favorites_visitor',
            $isAuthenticated
        );
        $selections = (bool) $this->siteSettings->get(
            $isAuthenticated ? 'selection_selections_user' : 'selection_selections_visitor',
            $isAuthenticated
        );
        if (!$favorites && !$selections) {
            return 'none';
        }
        if ($favorites && !$selections) {
            return 'simple';
        }
        return $this->siteSettings->get('selection_disposition', 'flat') === 'hierarchy'
            ? 'nested'
            : 'standard';
    }
}
