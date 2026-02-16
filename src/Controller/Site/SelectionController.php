<?php declare(strict_types=1);

namespace Selection\Controller\Site;

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
            'mode' => $this->settings()->get('selection_mode'),
            'disposition' => $disposition,
            'allowIndividualSelect' => $allowIndividualSelect,
            'selectionShare' => $selectionShare,
            'canPublishAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'publish'),
            'canValidateAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate'),
        ]);
    }
}
