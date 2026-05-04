<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Selection\Api\Representation\SelectionRepresentation;

class SelectionReset extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/selection-reset';

    /**
     * @var bool
     */
    protected $isGuestActive;

    public function __construct(bool $isGuestActive)
    {
        $this->isGuestActive = $isGuestActive;
    }

    /**
     * Create a button to reset user selection, with or without guest.
     *
     * @param array $options Options for the partial.
     */
    public function __invoke(?SelectionRepresentation $selection, array $options = []): string
    {
        $view = $this->getView();

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');

        $user = $view->identity();

        if ($selection) {
            $urlReset = $this->isGuestActive && $user
                ? $url('site/guest/selection-id', ['id' => $selection->id(), 'action' => 'reset'], true)
                : $url('site/selection-id', ['id' => $selection->id(), 'action' => 'reset'], true);
        } elseif ($view->selectionMode() === 'simple') {
            $urlReset = $this->isGuestActive && $user
                ? $url('site/guest/selection', ['action' => 'reset'], true)
                : $url('site/selection', ['action' => 'reset'], true);
        } else {
            // Do not remove all selections.
            return '';
        }

        $template = $options['template'] ?? self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'user' => $user,
            'isSession' => !$user,
            'isGuestActive' => $this->isGuestActive,
            'urlReset' => $urlReset,
        ] + $options;

        return $view->partial($template, $vars);
    }
}
