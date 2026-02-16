<?php declare(strict_types=1);

namespace SelectionTest\Entity;

use DateTime;
use PHPUnit\Framework\TestCase;
use Selection\Entity\Selection;

/**
 * Unit tests for the Selection entity.
 */
class SelectionTest extends TestCase
{
    /**
     * @var Selection
     */
    protected $selection;

    public function setUp(): void
    {
        $this->selection = new Selection();
    }

    public function testConstructorInitializesEmptySelectionResources(): void
    {
        $this->assertCount(0, $this->selection->getSelectionResources());
    }

    public function testSetAndGetLabel(): void
    {
        $this->selection->setLabel('My Selection');

        $this->assertEquals('My Selection', $this->selection->getLabel());
    }

    public function testSetAndGetComment(): void
    {
        $this->selection->setComment('This is a comment');

        $this->assertEquals('This is a comment', $this->selection->getComment());
    }

    public function testSetNullComment(): void
    {
        $this->selection->setComment('Comment');
        $this->selection->setComment(null);

        $this->assertNull($this->selection->getComment());
    }

    public function testDefaultIsPublicIsFalse(): void
    {
        $this->assertFalse($this->selection->isPublic());
    }

    public function testSetIsPublic(): void
    {
        $this->selection->setIsPublic(true);

        $this->assertTrue($this->selection->isPublic());
    }

    public function testSetIsPublicCastsToBool(): void
    {
        $this->selection->setIsPublic(1);
        $this->assertTrue($this->selection->isPublic());

        $this->selection->setIsPublic(0);
        $this->assertFalse($this->selection->isPublic());
    }

    public function testDefaultIsValidatedIsNull(): void
    {
        $this->assertNull($this->selection->isValidated());
    }

    public function testSetIsValidatedTrue(): void
    {
        $this->selection->setisValidated(true);

        $this->assertTrue($this->selection->isValidated());
    }

    public function testSetIsValidatedFalse(): void
    {
        $this->selection->setisValidated(false);

        $this->assertFalse($this->selection->isValidated());
    }

    public function testSetIsValidatedNull(): void
    {
        $this->selection->setisValidated(true);
        $this->selection->setisValidated(null);

        $this->assertNull($this->selection->isValidated());
    }

    public function testDefaultIsDynamicIsFalse(): void
    {
        $this->assertFalse($this->selection->isDynamic());
    }

    public function testSetIsDynamic(): void
    {
        $this->selection->setIsDynamic(true);

        $this->assertTrue($this->selection->isDynamic());
    }

    public function testSetSearchQueryMakesSelectionDynamic(): void
    {
        $this->assertFalse($this->selection->isDynamic());

        $this->selection->setSearchQuery('fulltext_search=test');

        $this->assertTrue($this->selection->isDynamic());
        $this->assertEquals('fulltext_search=test', $this->selection->getSearchQuery());
    }

    public function testSetEmptySearchQueryMakesSelectionStatic(): void
    {
        $this->selection->setSearchQuery('fulltext_search=test');
        $this->assertTrue($this->selection->isDynamic());

        $this->selection->setSearchQuery('');

        $this->assertFalse($this->selection->isDynamic());
        $this->assertNull($this->selection->getSearchQuery());
    }

    public function testSetSearchQueryTrimsWhitespace(): void
    {
        $this->selection->setSearchQuery('  fulltext_search=test  ');

        $this->assertEquals('fulltext_search=test', $this->selection->getSearchQuery());
    }

    public function testSetWhitespaceOnlySearchQueryIsNull(): void
    {
        $this->selection->setSearchQuery('   ');

        $this->assertNull($this->selection->getSearchQuery());
        $this->assertFalse($this->selection->isDynamic());
    }

    public function testSetAndGetStructure(): void
    {
        $structure = ['key' => 'value', 'nested' => ['a' => 1]];
        $this->selection->setStructure($structure);

        $this->assertEquals($structure, $this->selection->getStructure());
    }

    public function testSetNullStructure(): void
    {
        $this->selection->setStructure(['key' => 'value']);
        $this->selection->setStructure(null);

        $this->assertNull($this->selection->getStructure());
    }

    public function testSetAndGetCreated(): void
    {
        $now = new DateTime();
        $this->selection->setCreated($now);

        $this->assertSame($now, $this->selection->getCreated());
    }

    public function testSetAndGetModified(): void
    {
        $now = new DateTime();
        $this->selection->setModified($now);

        $this->assertSame($now, $this->selection->getModified());
    }

    public function testGetResourcesReturnsNullForDynamicSelection(): void
    {
        $this->selection->setSearchQuery('fulltext_search=test');

        $this->assertNull($this->selection->getResources());
    }

    public function testGetResourcesReturnsCollectionForStaticSelection(): void
    {
        $this->assertFalse($this->selection->isDynamic());

        $resources = $this->selection->getResources();

        $this->assertNotNull($resources);
        $this->assertCount(0, $resources);
    }
}
