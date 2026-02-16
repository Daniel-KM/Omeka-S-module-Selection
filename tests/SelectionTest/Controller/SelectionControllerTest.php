<?php declare(strict_types=1);

namespace SelectionTest\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests for the site Selection controller.
 *
 * Note: These tests require the Guest module to be installed for the
 * /s/{site}/guest/selection routes. Tests are marked as skipped if
 * the routes are not available.
 */
class SelectionControllerTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation
     */
    protected $item;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site;

    /**
     * @var \Omeka\Api\Representation\UserRepresentation
     */
    protected $testUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAdmin();
        $this->site = $this->createSite('test', 'Test Site');
        $this->item = $this->createItem('First Item');
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testSelectionApiSearch(): void
    {
        // Basic test that selection API works.
        $selections = $this->api()->search('selections')->getContent();
        $this->assertIsArray($selections);
    }

    public function testSelectionResourceApiSearch(): void
    {
        // Basic test that selection resource API works.
        $selectionResources = $this->api()->search('selection_resources')->getContent();
        $this->assertIsArray($selectionResources);
    }

    public function testCreateAndRetrieveSelection(): void
    {
        $selection = $this->createSelection(null, 'Test Selection');

        $retrieved = $this->api()->read('selections', $selection->id())->getContent();
        $this->assertEquals('Test Selection', $retrieved->label());
    }
}
