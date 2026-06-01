<?php declare(strict_types=1);

namespace Selection\Controller\Site;

use Common\Mvc\Controller\Plugin\JSend;
use DateTime;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\PermissionDeniedException;

class SelectionController extends AbstractSelectionController
{
    public function __construct(bool $isGuestActive)
    {
        $this->isGuestActive = $isGuestActive;
    }

    /**
     * Show a published selection with paginated resources.
     */
    public function showAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        if (!$id) {
            throw new NotFoundException('Selection not found.'); // @translate
        }

        // Automatically throw exception if not found.
        /** @var \Selection\Api\Representation\SelectionRepresentation $selection */
        $selection = $this->api()->read('selections', ['id' => $id])->getContent();

        // TODO Manage rights via acl. Allow moderation.
        // Visibility rules:
        // - Admins: can view all.
        // - Anonymous: only public and validated.
        // - Authenticated non-admin: own or public+validated.
        $user = $this->identity();
        $canViewAll = $user && $this->userIsAllowed(\Selection\Entity\Selection::class, 'view-all');
        if (!$canViewAll) {
            $isOwner = $user
                && $selection->owner()
                && $selection->owner()->id() === $user->getId();
            $isPublicAndValidated = $selection->isPublicAndValidated();
            if (!$isOwner && !$isPublicAndValidated) {
                throw new PermissionDeniedException('Selection not accessible.'); // @translate
            }
        }

        // Pagination similar to omeka resource Controller::browseAction().
        $query = $this->params()->fromQuery();
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['per_page'] ?? (int) $this->siteSettings()->get('pagination_per_page', 25));

        // Search selection resources for this selection with pagination.
        $searchQuery = [
            'selection_id' => $selection->id(),
            'page' => $page,
            'per_page' => $perPage,
        ];
        $response = $this->api()->search('selection_resources', $searchQuery);
        /** @var \Selection\Api\Representation\SelectionResourceRepresentation[] $selectionResources */
        $selectionResources = $response->getContent();
        $totalResults = $response->getTotalResults();

        // Configure paginator for the view.
        $this->paginator($totalResults, $page, $perPage);

        $disposition = $this->currentDisposition($query);
        $allowIndividualSelect = $this->allowIndividualSelect();
        $selectionShare = $this->siteSettings()->get('selection_share');

        return new ViewModel([
            'site' => $this->currentSite(),
            'user' => $user,
            'selection' => $selection,
            'resource' => $selection,
            // Show multiple resources by page.
            'resources' => $selectionResources,
            'totalResults' => $totalResults,
            'page' => $page,
            'perPage' => $perPage,
            // Parameters for compatibility with other views.
            // TODO Remove variables that are useless here.
            'selectionId' => $selection->id(),
            'isGuestActive' => $this->isGuestActive,
            'isSession' => false,
            'mode' => $this->effectiveMode(),
            'disposition' => $disposition,
            'allowIndividualSelect' => $allowIndividualSelect,
            'selectionShare' => $selectionShare,
            'canPublishAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'publish'),
            'canValidateAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate'),
            'maxResources' => (int) $this->siteSettings()->get('selection_max_resources'),
        ]);
    }

    /**
     * Return the HTML modal offering to convert the anonymous favorites into
     * user selections after login. Empty/unauthenticated cases return JSend
     * payloads the client-side code handles gracefully.
     */
    public function convertDialogAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->jSend(
                JSend::FAIL,
                null,
                $this->translate('Authentication required') // @translate
            );
        }

        $container = new Container('Selection');
        $count = $this->countSessionRecords($container);
        if ($count === 0) {
            return $this->jSend(JSend::SUCCESS, ['empty' => true]);
        }

        $favorites = $this->selectionFavoritesEnabled(true);
        $selectionsEnabled = $this->selectionSelectionsEnabled(true);
        if (!$favorites && !$selectionsEnabled) {
            return $this->jSend(
                JSend::FAIL,
                null,
                'Selection disabled for this user' // @translate
            );
        }

        $mode = $this->effectiveMode(true);
        $selections = [];
        if ($selectionsEnabled) {
            $selections = $this->api()->search('selections', [
                'owner_id' => $user->getId(),
            ])->getContent();
        }

        $defaultName = sprintf(
            $this->translate('My favorites of %s'), // @translate
            (new DateTime())->format('Y-m-d')
        );

        $view = new ViewModel([
            'mode' => $mode,
            'selections' => $selections,
            'count' => $count,
            'defaultName' => $defaultName,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('selection/site/selection/convert-dialog');
    }

    /**
     * Process the user's choice from the convert dialog: new, merge, discard or
     * dismiss. On success, the session favorites are cleared and the pending
     * flag is unset so the prompt is not shown again on the next page load.
     */
    public function convertAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->jSend(
                JSend::FAIL,
                null,
                $this->translate('Authentication required') // @translate
            );
        }
        if (!$this->getRequest()->isPost()) {
            return $this->jSend(
                JSend::FAIL,
                null,
                $this->translate('POST required') // @translate
            );
        }

        $data = $this->params()->fromPost();
        $action = (string) ($data['action'] ?? '');
        $container = new Container('Selection');
        $flag = new Container('SelectionConvert');

        if ($action === 'discard') {
            $container->exchangeArray([]);
            $flag->pending = false;
            unset($flag->dismissed);
            return $this->jSend(JSend::SUCCESS, ['discarded' => true]);
        }

        if ($action === 'dismiss') {
            $flag->dismissed = true;
            $flag->pending = false;
            return $this->jSend(JSend::SUCCESS, ['dismissed' => true]);
        }

        $resourceIds = $this->collectSessionResourceIds($container);
        if (!$resourceIds) {
            $container->exchangeArray([]);
            $flag->pending = false;
            unset($flag->dismissed);
            return $this->jSend(JSend::SUCCESS, ['empty' => true]);
        }

        $favorites = $this->selectionFavoritesEnabled(true);
        $selectionsEnabled = $this->selectionSelectionsEnabled(true);
        if (!$favorites && !$selectionsEnabled) {
            return $this->jSend(
                JSend::FAIL,
                null,
                $this->translate('Selection disabled for this user') // @translate
            );
        }

        $api = $this->api();

        // No multi-selections: merge into the user's default favorites basket.
        if ($favorites && !$selectionsEnabled) {
            $selection = $this->getSelectionStaticFirst($user)
                ?: $this->createSelectionStatic($user);
        } elseif ($action === 'new') {
            $label = trim((string) ($data['name'] ?? ''));
            if (!strlen($label)) {
                $label = sprintf(
                    $this->translate('My favorites of %s'), // @translate
                    (new DateTime())->format('Y-m-d')
                );
            }
            $selection = $api->create('selections', [
                'o:owner' => ['o:id' => $user->getId()],
                'o:label' => $label,
            ])->getContent();
        } elseif ($action === 'merge' && !empty($data['selection_id'])) {
            try {
                $selection = $api->read('selections', (int) $data['selection_id'])->getContent();
            } catch (NotFoundException $e) {
                return $this->jSend(
                    JSend::FAIL,
                    null,
                    $this->translate('Selection not found') // @translate
                );
            }
            if ($selection->owner()->id() !== $user->getId()) {
                return $this->jSend(
                    JSend::FAIL,
                    null,
                    $this->translate('Not owner') // @translate
                );
            }
        } else {
            return $this->jSend(
                JSend::FAIL,
                null,
                $this->translate('Invalid action') // @translate
            );
        }

        $added = $this->addResourcesToSelection($selection, $resourceIds);

        $container->exchangeArray([]);
        $flag->pending = false;
        unset($flag->dismissed);

        return $this->jSend(JSend::SUCCESS, [
            'selection_id' => $selection->id(),
            'added' => $added,
        ]);
    }

    protected function countSessionRecords(Container $container): int
    {
        $total = 0;
        foreach ($container->records ?? [] as $items) {
            $total += count($items);
        }
        return $total;
    }

    /**
     * Flatten the session records (indexed by selection id then resource id)
     * into a unique list of resource ids.
     */
    protected function collectSessionResourceIds(Container $container): array
    {
        $ids = [];
        foreach ($container->records ?? [] as $items) {
            foreach ($items as $rid => $_) {
                $ids[(int) $rid] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Append the given resource ids to the selection, skipping those already
     * present. Returns the number of resources actually added.
     */
    protected function addResourcesToSelection(
        \Selection\Api\Representation\SelectionRepresentation $selection,
        array $resourceIds
    ): int {
        $api = $this->api();
        $existingIds = $selection->resources();
        $added = 0;
        foreach ($resourceIds as $rid) {
            $rid = (int) $rid;
            if (!$rid || isset($existingIds[$rid])) {
                continue;
            }
            try {
                $api->create('selection_resources', [
                    'o:selection' => ['o:id' => $selection->id()],
                    'o:resource' => ['o:id' => $rid],
                ]);
                $added++;
            } catch (
                \Omeka\Api\Exception\NotFoundException
                | \Omeka\Api\Exception\PermissionDeniedException
                | \Omeka\Api\Exception\ValidationException $e
            ) {
                // Resource may have been deleted or become private between
                // anonymous browsing and login; skip it silently.
            }
        }
        return $added;
    }
}
