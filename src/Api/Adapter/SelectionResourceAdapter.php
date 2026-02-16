<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2019-2025
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Selection\Api\Adapter;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;
use Selection\Entity\Selection;

class SelectionResourceAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'resource_id' => 'resource',
        'created' => 'created',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'owner' => 'owner',
        'resource' => 'resource',
        'selection' => 'selection',
        'created' => 'created',
    ];

    public function getResourceName()
    {
        return 'selection_resources';
    }

    public function getRepresentationClass()
    {
        return \Selection\Api\Representation\SelectionResourceRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Selection\Entity\SelectionResource::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        // TODO Created/modified.

        $expr = $qb->expr();

        if (isset($query['owner_id']) && $query['owner_id'] !== '' && $query['owner_id'] !== []) {
            if (!is_array($query['owner_id'])) {
                $query['owner_id'] = [$query['owner_id']];
            }
            $ids = array_values(array_unique(array_map('intval', $query['owner_id'])));
            if ($ids) {
                $userAlias = $this->createAlias();
                $alias = ':owner_ids_' . ++$this->index;
                $qb
                    // The join allows to force the check rights.
                    ->innerJoin('omeka_root.owner', $userAlias)
                    ->andWhere($expr->in('omeka_root.owner', $alias))
                    ->setParameter($alias, $ids, Connection::PARAM_INT_ARRAY)
                ;
            } else {
                $qb->andWhere($expr->isNull('omeka_root.owner'));
            }
        }

        if (isset($query['resource_id']) && $query['resource_id'] !== '' && $query['resource_id'] !== []) {
            if (!is_array($query['resource_id'])) {
                $query['resource_id'] = [$query['resource_id']];
            }
            $ids = array_values(array_unique(array_map('intval', $query['resource_id'])));
            if ($ids) {
                $resourceAlias = $this->createAlias();
                $alias = ':resource_ids_' . ++$this->index;
                $qb
                    // The join allows to force the check of visibility.
                    ->innerJoin('omeka_root.resource', $resourceAlias)
                    ->andWhere($expr->in('omeka_root.resource', $alias))
                    ->setParameter($alias, $ids, Connection::PARAM_INT_ARRAY)
                ;
            } else {
                $qb->andWhere($expr->isNull('omeka_root.resource'));
            }
        }

        if (isset($query['selection_id']) && $query['selection_id'] !== '' && $query['selection_id'] !== []) {
            // Allow to get the selection resources that are not in a specific
            // selection, i.e. in the unlabelised default selection.
            // Note: as soon as a structure is created, all resources without a
            // selection are attached to it.
            if (!is_array($query['selection_id'])) {
                $query['selection_id'] = [$query['selection_id']];
            }
            $ids = array_values(array_unique(array_map('intval', $query['selection_id'])));
            if ($ids) {
                $alias = ':selection_ids_' . ++$this->index;
                $qb
                    ->andWhere($expr->in('omeka_root.selection', $alias))
                    ->setParameter($alias, $ids, Connection::PARAM_INT_ARRAY)
                ;
            } else {
                $qb->andWhere($expr->isNull('omeka_root.selection'));
            }
        }

        if (isset($query['selection_label']) && trim($query['selection_label'])) {
            $selectionAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    'omeka_root.selection',
                    $selectionAlias,
                )
                ->andWhere($expr->eq(
                    "$selectionAlias.label",
                    $this->createNamedParameter($qb, trim($query['selection_label']))
                ))
                ->andWhere($expr->eq(
                    "$selectionAlias.isDynamic",
                    $this->createNamedParameter($qb, 0)
                ));
        }
    }

    public function sortQuery(QueryBuilder $qb, array $query): void
    {
        if (!empty($query['sort_by'])) {
            $property = $this->getPropertyByTerm($query['sort_by']);
            if ($property) {
                $resourceAlias = $this->createAlias();
                $qb->leftJoin('omeka_root.resource', $resourceAlias);
                $valuesAlias = $this->createAlias();
                $qb->leftJoin(
                    "$resourceAlias.values", $valuesAlias,
                    'WITH', $qb->expr()->eq("$valuesAlias.property", $property->getId())
                );
                $qb->addOrderBy(
                    "GROUP_CONCAT($valuesAlias.value ORDER BY $valuesAlias.id ASC)",
                    $query['sort_order']
                );
            } else {
                parent::sortQuery($qb, $query);
            }
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @todo Replace this adapter by an hydrator like values for resource. */

        /** @var \Selection\Entity\SelectionResource $entity */

        // The selected resource is not updatable, except for the selection
        // itself when previous one was empty. In other cases, there should be
        // no selected resources
        $setSelection = $request->getOperation() === Request::CREATE
            || ($request->getOperation() === Request::UPDATE && !$entity->getSelection());

        if ($request->getOperation() === Request::CREATE) {
            $this->hydrateOwner($request, $entity);
            if ($this->shouldHydrate($request, 'o:resource')) {
                $resource = $request->getValue('o:resource');
                $resourceEntity = null;
                if (is_array($resource) && !empty($resource['o:id']) && is_numeric($resource['o:id'])) {
                    $resourceEntity = $this->getAdapter('resources')->findEntity((int) $resource['o:id']);
                }
                if ($resourceEntity && $resourceEntity instanceof Resource) {
                    $entity->setResource($resourceEntity);
                }
            }
            $entity->setCreated(new DateTime('now'));
        }

        if ($setSelection
            && $this->shouldHydrate($request, 'o:selection')
        ) {
            $selection = $request->getValue('o:selection');
            $selectionEntity = null;
            if ($selection === null) {
                // Nothing to do. No update.
            } elseif (is_array($selection)) {
                if (!empty($selection['o:id']) && is_numeric($selection['o:id'])) {
                    $selectionEntity = $this->getAdapter('selections')->findEntity((int) $selection['o:id']);
                } elseif (isset($selection['o:label'])) {
                    $label = trim((string) $selection['o:label']);
                    if (strlen($label)) {
                        // To simplify client requests to api, the selection
                        // is automatically created if a label is set.
                        try {
                            $selectionEntity = $this->getAdapter('selections')->findEntity([
                                'owner' => $entity->getOwner(),
                                'label' => $label,
                                'isDynamic' => false,
                            ]);
                        } catch (\Omeka\Api\Exception\NotFoundException $e) {
                            $selectionEntity = null;
                        }
                        if (!$selectionEntity) {
                            $comment = trim($selection['o:comment'] ?? '') ?: null;
                            $selectionEntity = new Selection();
                            $selectionEntity
                                ->setOwner($entity->getOwner())
                                ->setLabel($label)
                                ->setComment($comment);
                            $this->getEntityManager()->persist($selectionEntity);
                        }
                    }
                }
                if ($selectionEntity) {
                    $entity->setSelection($selectionEntity);
                }
            }
        }
    }

    public function validateEntity(
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $owner = $entity->getOwner();
        if (!$owner) {
            $errorStore->addError('o:owner', 'A selection resource must have an owner.'); // @translate
        }
        $resource = $entity->getResource();
        if (!$resource) {
            $errorStore->addError('o:resource', 'A selection resource must have a resource.'); // @translate
        }
        $selection = $entity->getSelection();
        if ($owner && $selection
            && $owner->getId() !== $selection->getOwner()->getId()
        ) {
            $errorStore->addError('o:owner', 'A selection resource must have the same owner than the selection.'); // @translate
        }
        // The toggle is not automatic here.
        if ($selection) {
            if ($selection->getId()
                && !$this->isUnique($entity, ['resource' => $resource, 'selection' => $selection])
            ) {
                $errorStore->addError('o:selection', 'A resource must be unique inside a selection.'); // @translate
            }
        } elseif (!$this->isUniqueWithNull($entity, ['resource' => $resource, 'owner' => $owner, 'selection' => null])) {
            $errorStore->addError('o:resource', 'A selection resource must be unique for the owner when there is no selection.'); // @translate
        }
        parent::validateEntity($entity, $errorStore);
    }

    public function preprocessBatchUpdate(array $data, Request $request)
    {
        $rawData = $request->getContent();
        $data = parent::preprocessBatchUpdate($data, $request);

        if (array_key_exists('o:selection', $rawData)) {
            $data['o:selection'] = $rawData['o:selection'];
        }

        return $data;
    }

    /**
     * Check for uniqueness by a set of criteria, included null.
     *
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::isUnique()
     *
     * @param EntityInterface $entity
     * @param array $criteria Keys are fields to check, values are strings or
     * null to check against. An entity may be passed as a value.
     * @return bool
     */
    public function isUniqueWithNull(EntityInterface $entity, array $criteria)
    {
        $this->index = 0;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('e.id')
            ->from($this->getEntityClass(), 'e');

        // Exclude the passed entity from the query if it has an persistent
        // identifier.
        $expr = $qb->expr();
        if ($entity->getId()) {
            $qb->andWhere($expr->neq(
                'e.id',
                $this->createNamedParameter($qb, $entity->getId())
            ));
        }

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $qb->andWhere($expr->isNull("e.$field"));
            } else {
                $qb->andWhere($expr->eq(
                    "e.$field",
                    $this->createNamedParameter($qb, $value)
                ));
            }
        }

        return null === $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Get a property entity by JSON-LD term.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::getPropertyByTerm()
     *
     * @param string $term
     * @return EntityInterface
     */
    protected function getPropertyByTerm($term)
    {
        if (!$this->isTerm($term)) {
            return null;
        }
        [$prefix, $localName] = explode(':', $term);
        $dql = 'SELECT p FROM Omeka\Entity\Property p
        JOIN p.vocabulary v WHERE p.localName = :localName
        AND v.prefix = :prefix';
        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult();
    }
}
