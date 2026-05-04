<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Settings\SiteSettings;

class SelectionMode extends AbstractHelper
{
    protected SiteSettings $siteSettings;

    public function __construct(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    /**
     * Returns effective selection mode for current user context.
     *
     * One of: 'none', 'simple', 'standard', 'nested'.
     */
    public function __invoke(?bool $isAuthenticated = null): string
    {
        if ($isAuthenticated === null) {
            $isAuthenticated = (bool) $this->getView()->identity();
        }
        return $isAuthenticated
            ? (string) $this->siteSettings->get('selection_mode_user', 'standard')
            : (string) $this->siteSettings->get('selection_mode_visitor', 'none');
    }
}
