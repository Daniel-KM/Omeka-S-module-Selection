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

        // Fill selection from session.
        /** @var \Laminas\Session\Container $selectionContainer */
        $selectionContainer = $this->selectionContainer();

        $query = $this->params()->fromQuery();
        $disposition = $this->currentDisposition($query);
        $allowIndividualSelect = $this->allowIndividualSelect();
        $selectionShare = $this->siteSettings()->get('selection_share');

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
            'selectionShare' => $selectionShare,
            'canPublishAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'publish'),
            'canValidateAll' => $user && $this->userIsAllowed(\Selection\Api\Adapter\SelectionAdapter::class, 'validate'),
        ]);
    }
}
