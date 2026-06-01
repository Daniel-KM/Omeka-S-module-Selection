<?php declare(strict_types=1);

namespace Selection\Controller\Site;

/**
 * Personal area for the visitor or user: browse own selections, view a
 * single selection, list favorites, reset. Routed under /guest/* so the
 * URLs remain stable across module Guest installations or removals; the
 * controller does not require module Guest to be installed.
 *
 * Anonymous visitors are served from session storage; authenticated users
 * from the database.
 */
class GuestController extends AbstractSelectionController
{
    protected $isGuestActive = true;

    /**
     * No public index here: redirect to /guest/selection (own browse).
     */
    public function indexAction()
    {
        return $this->redirect()->toRoute('site/guest/selection', [
            'site-slug' => $this->params('site-slug'),
        ]);
    }
}
