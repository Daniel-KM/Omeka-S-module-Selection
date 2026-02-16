<?php declare(strict_types=1);

namespace SelectionTest\Api\Adapter;

use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests for the SelectionResource API adapter.
 */
class SelectionResourceAdapterTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation
     */
    protected $item;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->item = $this->createItem('Test Item');
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testCreateSelectionResource(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        // Create selection resource using API directly with proper owner.
        $response = $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);
        $selectionResource = $response->getContent();

        $this->assertNotNull($selectionResource);
        $this->assertEquals($this->item->id(), $selectionResource->resource()->id());
    }

    public function testReadSelectionResource(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        $response = $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);
        $created = $response->getContent();

        $selectionResource = $this->api()->read('selection_resources', $created->id())->getContent();

        $this->assertEquals($created->id(), $selectionResource->id());
    }

    public function testDeleteSelectionResource(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        $response = $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);
        $selectionResource = $response->getContent();
        $id = $selectionResource->id();

        $this->api()->delete('selection_resources', $id);

        $this->expectException(\Omeka\Api\Exception\NotFoundException::class);
        $this->api()->read('selection_resources', $id);
    }

    public function testSearchSelectionResourcesBySelection(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);

        $selectionResources = $this->api()->search('selection_resources', [
            'selection_id' => $selection->id(),
        ])->getContent();

        $this->assertCount(1, $selectionResources);
    }

    public function testSearchSelectionResourcesByResource(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);

        $selectionResources = $this->api()->search('selection_resources', [
            'resource_id' => $this->item->id(),
        ])->getContent();

        $this->assertCount(1, $selectionResources);
    }

    public function testDuplicateResourceInSameSelectionIsRejected(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        // Add resource first time.
        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);

        // Try to add the same resource again.
        $this->expectException(\Omeka\Api\Exception\ValidationException::class);
        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);
    }

    public function testSameResourceCanBeInDifferentSelections(): void
    {
        $selection1 = $this->createSelection(null, 'Selection 1');
        $selection2 = $this->createSelection(null, 'Selection 2');

        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection1->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);
        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selection2->id()],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);

        $selectionResources = $this->api()->search('selection_resources', [
            'resource_id' => $this->item->id(),
        ])->getContent();

        $this->assertCount(2, $selectionResources);
    }

    public function testDeletingSelectionDeletesSelectionResources(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');
        $selectionId = $selection->id();

        $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selectionId],
            'o:resource' => ['o:id' => $this->item->id()],
        ]);

        $this->api()->delete('selections', $selectionId);
        // Remove from cleanup list since already deleted.
        $this->createdSelections = array_diff($this->createdSelections, [$selectionId]);

        $selectionResources = $this->api()->search('selection_resources', [
            'selection_id' => $selectionId,
        ])->getContent();

        $this->assertEmpty($selectionResources);
    }
}
