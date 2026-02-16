<?php declare(strict_types=1);

namespace Selection\Db\Filter;

use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Selection\Entity\Selection;

/**
 * Filter selection by visibility.
 *
 * Checks to see if the current user has permission to view a selection.
 * The selection is viewable if the user has the "view-all" permission,
 * if the user own the selection, or if it is public and validated.
 */
class SelectionVisibilityFilter extends SQLFilter
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if ($targetEntity->getName() === Selection::class) {
            $acl = $this->services->get('Omeka\Acl');
            if ($acl->userIsAllowed(\Selection\Entity\Selection::class, 'view-all')) {
                return '';
            }

            $identity = $this->services->get('Omeka\AuthenticationService')->getIdentity();
            if ($identity) {
                // Users can view all public selections and own selections.
                $identityId = (int) $identity->getId();
                $constraint = "(($targetTableAlias.is_public = 1 AND $targetTableAlias.is_validated = 1) OR $targetTableAlias.owner_id = $identityId)";
            } else {
                // Users can view public selections they do not own.
                $constraint = "($targetTableAlias.is_public = 1 AND $targetTableAlias.is_validated = 1)";
            }

            return $constraint;
        }

        return '';
    }

    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        $this->services = $services;
        return $this;
    }
}
