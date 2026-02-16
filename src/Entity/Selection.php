<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2020-2026
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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;

/**
 * The unique constraint is limited to static/dynamic selection, so it is
 * possible to use the same label for a static selection and a dynamic one.
 * This is a end user requirement (display the dynamic and static selections on
 * two pages), but it avoids to duplicate the module.
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             name="is_public",
 *             columns={"is_public"}
 *         ),
 *         @Index(
 *             name="is_validated",
 *             columns={"is_validated"}
 *          )
 *     },
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             columns={
 *                 "owner_id",
 *                 "label",
 *                 "is_dynamic"
 *             }
 *         )
 *     }
 * )
 */
class Selection extends AbstractEntity
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
     * The visibility is false by default, because it belongs to a user.
     *
     * @var bool
     *
     * @Column(
     *      type="boolean",
     *      nullable=false,
     *      options={
     *          "default":0
     *      }
     * )
     */
    protected $isPublic = false;

    /**
     * @var bool|null
     *
     * @Column(
     *      type="boolean",
     *      nullable=true
     * )
     */
    protected $isValidated;

    /**
     * This flag is automatically generated from the presence of a search query.
     *
     * @var bool
     *
     * @Column(
     *      type="boolean",
     *      nullable=false,
     *      options={
     *          "default":0
     *      }
     * )
     */
    protected $isDynamic = false;

    /**
     * @var string
     *
     * @Column(
     *      nullable=false,
     *      length=190
     * )
     */
    protected $label;

    /**
     * @var string
     *
     * @Column(
     *      type="text",
     *      nullable=true
     * )
     */
    protected $comment;

    /**
     * This is a full search, that is not related to a specific resource type
     * (items, item sets, media), except if the key "resource_type" is set.
     *
     * @var string
     *
     * @Column(
     *      type="text",
     *      nullable=true
     * )
     */
    protected $searchQuery;

    /**
     * When a search query is set, all selection resources are removed, so the
     * list of selections is empty, but the list of resources is dynamic.
     *
     * @var SelectionResource[]
     *
     * @OneToMany(
     *      targetEntity="SelectionResource",
     *      mappedBy="selection",
     *      orphanRemoval=true,
     *      cascade={"persist", "remove", "detach"},
     *      indexBy="id"
     * )
     */
    protected $selectionResources;

    // TODO Manage children here. Add position. See thesaurus.

    /**
     * @var array
     *
     * @Column(
     *     type="json",
     *     nullable=true
     * )
     */
    protected $structure;

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

    /**
     * @var DateTime
     *
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $modified;

    public function __construct()
    {
        $this->selectionResources = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setIsPublic($isPublic): self
    {
        $this->isPublic = (bool) $isPublic;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setisValidated(?bool $isValidated): self
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->isValidated;
    }

    public function setIsDynamic(bool $isDynamic): self
    {
        $this->isDynamic = (bool) $isDynamic;
        return $this;
    }

    public function isDynamic(): bool
    {
        return $this->isDynamic;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setSearchQuery(?string $searchQuery): self
    {
        $this->searchQuery = trim((string) $searchQuery) ?: null;
        $this->isDynamic = !empty($this->searchQuery);
        return $this;
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    public function getSelectionResources(): Collection
    {
        return $this->selectionResources;
    }

    /**
     * Returns the selected resource from the selection resources only if there
     * is no search query.
     */
    public function getResources(): ?Collection
    {
        if ($this->isDynamic()) {
            return null;
        }
        return $this->getSelectionResources()->map(fn ($selectionResource) => $selectionResource->getResource());
    }

    public function setStructure(?array $structure): self
    {
        $this->structure = $structure;
        return $this;
    }

    public function getStructure(): ?array
    {
        return $this->structure;
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

    public function setModified(DateTime $dateTime): self
    {
        $this->modified = $dateTime;
        return $this;
    }

    public function getModified(): ?DateTime
    {
        return $this->modified;
    }
}
