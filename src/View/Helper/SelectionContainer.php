<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Exception;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\User;

class SelectionContainer extends AbstractHelper
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Laminas\EventManager\EventManagerInterface
     */
    protected $events;

    public function __construct(ApiManager $api, EventManagerInterface $events)
    {
        $this->api = $api;
        $this->events = $events;
    }

    /**
     * Prepare and check the session container for the current visitor or user.
     *
     * There may be multiple selections for a user and they can be nested.
     * By default, there is a single list of selection. See option disposition.
     */
    public function __invoke(): Container
    {
        static $container;

        if ($container !== null) {
            return $container;
        }

        $view = $this->getView();

        $container = new Container('Selection');

        // Always sync session with the user selected resources.
        $user = $view->identity();
        if ($user) {
            $container->init = true;
            $container->selections = $this->normalizeSelections($user);
            $container->records = $this->normalizeResources($user);
        } else {
            $defaultSelection = [
                'id' => 1,
                'label' => $view->siteSetting('selection_label_favorites', $view->translate('My favorites')), // @translate
                'structure' => [],
            ];
            if (empty($container->init)) {
                $container->init = true;
                $container->selections = [1 => $defaultSelection];
                $container->records = [];
                // The default selection is useless to keep when the visitor
                // selected nothing, so no session is saved for the visitors
                // who only browse the site.
                $this->events->attach(MvcEvent::EVENT_FINISH, [$this, 'removeUnusedSelection'], 200);
            } else {
                $container->selections ??= [];
                $container->records ??= [];
                if (empty($container->selections)) {
                    $container->selections = [1 => $defaultSelection];
                }
            }
        }

        return $container;
    }

    /**
     * Remove the default selection of a visitor when nothing was selected.
     */
    public function removeUnusedSelection(): void
    {
        $container = new Container('Selection');
        $selections = (array) $container->selections;
        $selection = reset($selections);
        if (empty(array_filter((array) $container->records))
            && count($selections) <= 1
            && empty($selection['structure'])
        ) {
            $container->getManager()->getStorage()->clear('Selection');
        }
    }

    /**
     * Simplify selections for views. Ensure the default favorites basket exists
     * when site setting "selection_favorites_user" is enabled, and place it
     * first so simple favorite buttons target it consistently.
     */
    protected function normalizeSelections(User $user): array
    {
        $view = $this->getView();

        try {
            /** @var \Selection\Api\Representation\SelectionRepresentation[] $selections */
            $selections = $this->api->search('selections', ['owner_id' => $user->getId()])->getContent();
        } catch (Exception $e) {
            $selections = [];
        }

        if ($view->siteSetting('selection_favorites_user', true)) {
            $default = null;
            foreach ($selections as $selection) {
                if ($selection->isDefault() && !$selection->isDynamic()) {
                    $default = $selection;
                    break;
                }
            }
            if (!$default) {
                $default = $this->createDefaultSelection($user, $selections);
                if ($default) {
                    $selections[] = $default;
                }
            }
            if ($default) {
                // Place the default favorites basket first.
                $ordered = [$default];
                foreach ($selections as $selection) {
                    if ($selection->id() !== $default->id()) {
                        $ordered[] = $selection;
                    }
                }
                $selections = $ordered;
            }
        }

        $result = [];
        foreach ($selections as $selection) {
            $result[$selection->id()] = [
                'id' => $selection->id(),
                'label' => $selection->label(),
                'structure' => $selection->structure(),
                'is_default' => $selection->isDefault(),
            ];
        }
        return $result;
    }

    /**
     * Create the default favorites basket for the user. If a non-default
     * selection already uses the favorites label, promote it instead of
     * creating a new one to avoid duplicates and label collisions.
     */
    protected function createDefaultSelection(User $user, array $selections): ?\Selection\Api\Representation\SelectionRepresentation
    {
        $view = $this->getView();
        $label = (string) $view->siteSetting('selection_label_favorites', $view->translate('My favorites')); // @translate
        $label = trim($label) !== '' ? $label : (string) $view->translate('My favorites'); // @translate

        foreach ($selections as $selection) {
            if ($selection->label() === $label) {
                try {
                    return $this->api->update('selections', $selection->id(), [
                        'o:is_default' => true,
                    ], [], ['isPartial' => true])->getContent();
                } catch (Exception $e) {
                    return null;
                }
            }
        }

        try {
            return $this->api->create('selections', [
                'o:owner' => ['o:id' => $user->getId()],
                'o:label' => $label,
                'o:is_default' => true,
            ])->getContent();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Format all resources to be stored.
     */
    protected function normalizeResources(User $user): array
    {
        try {
            /** @var \Selection\Api\Representation\SelectionResourceRepresentation[] $selectionResources */
            $selectionResources = $this->api->search('selection_resources', ['owner_id' => $user->getId()])->getContent();
        } catch (Exception $e) {
            return [];
        }
        // Here, the value is never "unselected" because it is the init of the
        // container.
        return $this->getView()->selectionRecords(null, $selectionResources, true);
    }
}
