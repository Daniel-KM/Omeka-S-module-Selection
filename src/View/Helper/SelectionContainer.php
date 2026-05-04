<?php declare(strict_types=1);

namespace Selection\View\Helper;

use Exception;
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

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
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
                'label' => $view->siteSetting('selection_label_visitor', $view->translate('Favorites')), // @translate
                'structure' => [],
            ];
            if (empty($container->init)) {
                $container->init = true;
                $container->selections = [1 => $defaultSelection];
                $container->records = [];
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
     * Simplify selections for views. Create a new one if none.
     */
    protected function normalizeSelections(User $user): array
    {
        $view = $this->getView();

        try {
            $selections = $this->api->search('selections', ['owner_id' => $user->getId()])->getContent();
        } catch (Exception $e) {
            $selections = [];
        }

        if (!$selections) {
            $selection = $this->api->create('selections', [
                'o:owner' => ['o:id' => $user->getId()],
                'o:label' => $view->siteSetting('selection_label_user', $view->translate('Selection')), // @translate
            ])->getContent();
            $selections[] = $selection;
        }

        $result = [];
        foreach ($selections as $selection) {
            $result[$selection->id()] = [
                'id' => $selection->id(),
                'label' => $selection->label(),
                'structure' => $selection->structure(),
            ];
        }
        return $result;
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
