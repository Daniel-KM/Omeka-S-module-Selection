<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2023-2024
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

namespace Selection\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class SelectionResourceRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Selection\Entity\SelectionResource
     */
    protected $resource;

    public function getJsonLdType()
    {
        return 'o:SelectionResource';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        $linkedResource = $this->resource();
        $selection = $this->selection();

        return [
            'o:selection' => $selection ? $selection->getReference()->jsonSerialize() : null,
            'o:resource' => $linkedResource ? $linkedResource->getReference()->jsonSerialize() : null,
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:created' => [
                '@value' => $this->getDateTime($this->created()),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ],
        ];
    }

    public function selection(): ?SelectionRepresentation
    {
        $selection = $this->resource->getSelection();
        return $selection
            ? $this->getAdapter('selections')->getRepresentation($selection)
            : null;
    }

    /**
     * Get the resource associated to the selection.
     *
     * May return null to fix issues with missing or private resources.
     */
    public function resource(): ?AbstractResourceEntityRepresentation
    {
        $adapter = $this->getAdapter('resources');
        $linkedResource = $this->resource->getResource();
        return $linkedResource
            ? $adapter->getRepresentation($linkedResource)
            : null;
    }

    /**
     * Get the owner associated to the selection, if allowed.
     */
    public function owner(): ?UserRepresentation
    {
        $adapter = $this->getAdapter('users');
        $owner = $this->resource->getOwner();
        return $owner
            ? $adapter->getRepresentation($owner)
            : null;
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function primaryMedia(): ?MediaRepresentation
    {
        $linkedResource = $this->resource();
        return $linkedResource
            ? $linkedResource->primaryMedia()
            : null;
    }
}
