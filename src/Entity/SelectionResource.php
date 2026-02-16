<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020-2025
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

namespace Selection\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\User;

/**
 * Unique constraint options are not managed by mysql: the resource should be
 * unique for the owner when there is no selection. So this is managed only
 * during hydration: @UniqueConstraint(columns={"resource_id","owner_id"},options={"where": "selection_id IS NULL"}).
 *
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *          @UniqueConstraint(
 *              columns={
 *                  "resource_id",
 *                  "selection_id"
 *              }
 *          )
 *    }
 * )
 */
class SelectionResource extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *      type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var User
     *
     * Denormalized from Selection to allow to select resources without selection.
     * @todo Remove field "user" from SelectionResource.
     *
     * @ManyToOne(
     *      targetEntity="\Omeka\Entity\User"
     * )
     * @JoinColumn(
     *      nullable=false,
     *      onDelete="CASCADE"
     * )
     */
    protected $owner;

    /**
     * @var \Omeka\Entity\Resource
     *
     * @ManyToOne(
     *      targetEntity="\Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *      nullable=false,
     *      onDelete="CASCADE"
     * )
     */
    protected $resource;

    /**
     * @var Selection
     *
     * TODO For now, nullable when there is no selection on mode single selection. Remove nullable.
     *
     * @ManyToOne(
     *      targetEntity="Selection",
     *      inversedBy="selectionResources"
     * )
     * @JoinColumn(
     *      nullable=true,
     *      onDelete="CASCADE"
     * )
     */
    protected $selection;

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=false,
     *     options={
     *         "default": "CURRENT_TIMESTAMP"
     *     }
     * )
     */
    protected $created;

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * The output may be null, because the adapter check it before filling it.
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::hydrateOwner()
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setResource(Resource $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * The output may be null, because there may be a check for visibility.
     */
    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setSelection(?Selection $selection): self
    {
        $this->selection = $selection;
        return $this;
    }

    public function getSelection(): ?Selection
    {
        return $this->selection;
    }

    public function setCreated(DateTime $dateTime): self
    {
        $this->created = $dateTime;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }
}
