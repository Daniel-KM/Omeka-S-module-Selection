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
     * Show a published selection.
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
        $user = $this->identity();
        $isOwner = $user && $selection->owner() && $selection->owner()->id() === $user->getId();
        $isPublic = $selection->isPublic();
        $canViewAll = $user && $this->userIsAllowed(\Selection\Entity\Selection::class, 'view-all');

        if (!$canViewAll && !$isPublic && !$isOwner) {
            throw new PermissionDeniedException('Selection not accessible.'); // @translate
        }

        // Fill selection from session.
        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $this->selectionContainer();

        $query = $this->params()->fromQuery();
        $disposition = $this->currentDisposition($query);
        $allowIndividualSelect = $this->allowIndividualSelect();

        // TODO Simplify view: only selection is needed here.
        return new ViewModel([
            'site' => $this->currentSite(),
            'user' => $user,
            'selection' => $selection,
            'resource' => $selection,
            'selectionId' => $selection->id(),
            // 'selections' => $selectionContainer->selections,
            // 'records' => $selectionContainer->records,
            'isGuestActive' => $this->isGuestActive,
            'isSession' => false,
            'mode' => $this->settings()->get('selection_mode'),
            'disposition' => $disposition,
            'allowIndividualSelect' => $allowIndividualSelect,
        ]);
    }
}
