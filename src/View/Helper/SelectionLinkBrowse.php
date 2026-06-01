<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SelectionLinkBrowse extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-link-browse';

    /**
     * @var bool
     */
    protected $isGuestActive;

    public function __construct(bool $isGuestActive)
    {
        $this->isGuestActive = $isGuestActive;
    }

    /**
     * Get the link to the user selection, with or without guest.
     *
     * @param array $options Options for the partial.
     */
    public function __invoke(array $options = []): string
    {
        $view = $this->getView();

        $user = $view->identity();
        $urlBrowse = $view->url('site/guest/selection', ['action' => 'browse'], true);

        $template = $options['template'] ?? self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'user' => $user,
            'urlBrowse' => $urlBrowse,
            'isGuestActive' => $this->isGuestActive,
        ] + $options;

        return $view->partial($template, $vars);
    }
}
