<?php declare(strict_types=1);

namespace SelectionTest\Entity;

use DateTime;
use PHPUnit\Framework\TestCase;
use Selection\Entity\Selection;
use Selection\Entity\SelectionResource;

/**
 * Unit tests for the SelectionResource entity.
 */
class SelectionResourceTest extends TestCase
{
    /**
     * @var SelectionResource
     */
    protected $selectionResource;

    public function setUp(): void
    {
        $this->selectionResource = new SelectionResource();
    }

    public function testSetAndGetSelection(): void
    {
        $selection = new Selection();
        $selection->setLabel('Test Selection');

        $this->selectionResource->setSelection($selection);

        $this->assertSame($selection, $this->selectionResource->getSelection());
    }

    public function testSetNullSelection(): void
    {
        $selection = new Selection();
        $this->selectionResource->setSelection($selection);
        $this->selectionResource->setSelection(null);

        $this->assertNull($this->selectionResource->getSelection());
    }

    public function testSetAndGetCreated(): void
    {
        $now = new DateTime();
        $this->selectionResource->setCreated($now);

        $this->assertSame($now, $this->selectionResource->getCreated());
    }

    public function testSetAndGetCreatedWithDifferentTimes(): void
    {
        $time1 = new DateTime('2024-01-01 10:00:00');
        $time2 = new DateTime('2024-06-15 15:30:00');

        $this->selectionResource->setCreated($time1);
        $this->assertEquals($time1, $this->selectionResource->getCreated());

        $this->selectionResource->setCreated($time2);
        $this->assertEquals($time2, $this->selectionResource->getCreated());
    }

    public function testNewSelectionResourceHasNoId(): void
    {
        $this->assertNull($this->selectionResource->getId());
    }

    public function testNewSelectionResourceHasNoSelection(): void
    {
        $this->assertNull($this->selectionResource->getSelection());
    }

    public function testNewSelectionResourceHasNoResource(): void
    {
        $this->assertNull($this->selectionResource->getResource());
    }

    public function testNewSelectionResourceHasNoOwner(): void
    {
        $this->assertNull($this->selectionResource->getOwner());
    }
}
