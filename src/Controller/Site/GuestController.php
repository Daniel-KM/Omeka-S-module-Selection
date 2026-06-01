<?php declare(strict_types=1);

namespace Selection\Controller\Site;

class GuestController extends AbstractSelectionController
{
    protected $isGuestActive = true;

    /**
     * Unlike selection controller, there is no index for guest.
     *
     * Anonymous visitors are redirected to login since the Guest controller
     * is restricted to authenticated users by the ACL.
     *
     * {@inheritDoc}
     * @see \Selection\Controller\Site\AbstractSelectionController::indexAction()
     */
    public function indexAction()
    {
        if (!$this->identity()) {
            return $this->redirectAnonymousToLogin();
        }
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch('Selection\Controller\Site\Guest', $params);
    }

    public function browseAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->redirectAnonymousToLogin();
        }
        // Toujours afficher la liste des sélections (et non la vue d'une
        // sélection unique imposée par parent::browseAction en mode simple),
        // pour correspondre à la page « compte utilisateur ».
        return $this->browseDb($user);
    }

    private function redirectAnonymousToLogin()
    {
        return $this->redirect()->toRoute(
            'site/guest/anonymous',
            ['site-slug' => $this->params('site-slug'), 'action' => 'login'],
            ['query' => ['redirect' => $this->getRequest()->getRequestUri()]]
        );
    }
}
