<?php declare(strict_types=1);

namespace SelectionTest\Api\Adapter;

use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests for the Selection API adapter.
 */
class SelectionAdapterTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

    /**
     * @var \Omeka\Api\Representation\UserRepresentation
     */
    protected $testUser;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->testUser = $this->createUser('test@test.fr', 'toto', 'researcher');
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testCreateSelection(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'My Selection');

        $this->assertNotNull($selection);
        $this->assertEquals('My Selection', $selection->label());
        $this->assertEquals($this->testUser->id(), $selection->owner()->id());
    }

    public function testCreatePublicSelection(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Public Selection', true);

        $this->assertTrue($selection->isPublic());
    }

    public function testCreatePrivateSelection(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Private Selection', false);

        $this->assertFalse($selection->isPublic());
    }

    public function testReadSelection(): void
    {
        $created = $this->createSelection($this->testUser->id(), 'Test Selection');

        $selection = $this->api()->read('selections', $created->id())->getContent();

        $this->assertEquals($created->id(), $selection->id());
        $this->assertEquals('Test Selection', $selection->label());
    }

    public function testUpdateSelection(): void
    {
        $created = $this->createSelection($this->testUser->id(), 'Original Label');

        $this->api()->update('selections', $created->id(), [
            'o:label' => 'Updated Label',
        ], [], ['isPartial' => true]);

        $updated = $this->api()->read('selections', $created->id())->getContent();
        $this->assertEquals('Updated Label', $updated->label());
    }

    public function testDeleteSelection(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'To Delete');
        $selectionId = $selection->id();

        $this->api()->delete('selections', $selectionId);
        // Remove from cleanup list since already deleted.
        $this->createdSelections = array_diff($this->createdSelections, [$selectionId]);

        $this->expectException(\Omeka\Api\Exception\NotFoundException::class);
        $this->api()->read('selections', $selectionId);
    }

    public function testSearchSelectionsByOwner(): void
    {
        $this->createSelection($this->testUser->id(), 'Selection 1');
        $this->createSelection($this->testUser->id(), 'Selection 2');

        $selections = $this->api()->search('selections', [
            'owner_id' => $this->testUser->id(),
        ])->getContent();

        $this->assertCount(2, $selections);
    }

    public function testSearchSelectionsByLabel(): void
    {
        $this->createSelection($this->testUser->id(), 'Unique Label');
        $this->createSelection($this->testUser->id(), 'Other Label');

        $selections = $this->api()->search('selections', [
            'label' => 'Unique Label',
        ])->getContent();

        $this->assertCount(1, $selections);
        $this->assertEquals('Unique Label', $selections[0]->label());
    }

    public function testPrivateSelectionValidationIsNull(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Private', false);

        // Private selections should have null validation status.
        $this->assertNull($selection->isValidated());
    }

    public function testPublicSelectionCanBeValidated(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Public', true, true);

        $this->assertTrue($selection->isValidated());
    }

    public function testPublicSelectionCanBeRejected(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Public', true, false);

        $this->assertFalse($selection->isValidated());
    }

    public function testMakingSelectionPrivateResetsValidation(): void
    {
        // Create validated public selection.
        $selection = $this->createSelection($this->testUser->id(), 'Public', true, true);
        $this->assertTrue($selection->isValidated());

        // Make it private.
        $this->api()->update('selections', $selection->id(), [
            'o:is_public' => false,
        ], [], ['isPartial' => true]);

        $updated = $this->api()->read('selections', $selection->id())->getContent();
        $this->assertFalse($updated->isPublic());
        $this->assertNull($updated->isValidated());
    }

    public function testSelectionSortByCreated(): void
    {
        $selection1 = $this->createSelection($this->testUser->id(), 'First');
        sleep(1);
        $selection2 = $this->createSelection($this->testUser->id(), 'Second');

        $selections = $this->api()->search('selections', [
            'owner_id' => $this->testUser->id(),
            'sort_by' => 'created',
            'sort_order' => 'asc',
        ])->getContent();

        $this->assertEquals($selection1->id(), $selections[0]->id());
        $this->assertEquals($selection2->id(), $selections[1]->id());
    }
}
