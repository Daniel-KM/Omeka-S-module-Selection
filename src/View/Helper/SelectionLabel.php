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
     * Returns the configured label for the favorites or selections page.
     *
     * @param string $type 'favorites' or 'selections'
     */
    public function __invoke(string $type = 'favorites'): string
    {
        $key = $type === 'selections' ? 'selection_label_selections' : 'selection_label_favorites';
        $default = $type === 'selections' ? 'Selections' : 'My favorites';
        $label = (string) $this->siteSettings->get($key, '');
        return $label !== '' ? $label : $default;
    }
}
