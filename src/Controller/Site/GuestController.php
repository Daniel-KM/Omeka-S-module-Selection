<?php declare(strict_types=1);

namespace Selection\Controller\Site;

class GuestController extends AbstractSelectionController
{
    protected $isGuestActive = true;

    /**
     * Unlike selection controller, there is no index for guest.
     *
     * {@inheritDoc}
     * @see \Selection\Controller\Site\AbstractSelectionController::indexAction()
     */
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch('Selection\Controller\Site\Guest', $params);
    }
}
