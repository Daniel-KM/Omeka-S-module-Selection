<?php declare(strict_types=1);

namespace Selection\Controller\Site;

class SelectionController extends AbstractSelectionController
{
    public function __construct(bool $isGuestActive)
    {
        $this->isGuestActive = $isGuestActive;
    }
}
