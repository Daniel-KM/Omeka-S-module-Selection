<?php declare(strict_types=1);

namespace SelectionTest\Controller\Admin;

use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests for the Selection admin controller.
 *
 * Note: Some tests require full admin context with user settings.
 * Tests that fail due to missing context are marked as skipped.
 */
class SelectionControllerTest extends AbstractHttpControllerTestCase
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
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testTogglePublicMakesPublicSelectionPrivate(): void
    {
        $selection = $this->createSelection(null, 'Public Selection', true, null);

        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-public', 'POST');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);

        $this->assertEquals('success', $response['status']);
        $this->assertFalse($response['data']['selection']['is_public']);
    }

    public function testTogglePublicMakesPrivateSelectionPublic(): void
    {
        $selection = $this->createSelection(null, 'Private Selection', false);

        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-public', 'POST');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);

        $this->assertEquals('success', $response['status']);
        $this->assertTrue($response['data']['selection']['is_public']);
    }

    public function testTogglePublicReturnsValidationStatus(): void
    {
        $selection = $this->createSelection(null, 'Private Selection', false);

        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-public', 'POST');

        $response = json_decode($this->getResponse()->getContent(), true);

        $this->assertArrayHasKey('is_validated', $response['data']['selection']);
        $this->assertArrayHasKey('validatedStatus', $response['data']['selection']);
    }

    public function testToggleValidatedCyclesThroughStates(): void
    {
        // Create public selection with null validation.
        $selection = $this->createSelection(null, 'Public Selection', true, null);

        // First toggle: null -> true (validated).
        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-validated', 'POST');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertTrue($response['data']['selection']['is_validated']);
        $this->assertEquals('validated', $response['data']['selection']['status']);

        // Second toggle: true -> false (rejected).
        $this->reset();
        $this->loginAdmin();
        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-validated', 'POST');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertFalse($response['data']['selection']['is_validated']);
        $this->assertEquals('rejected', $response['data']['selection']['status']);

        // Third toggle: false -> null (undetermined).
        $this->reset();
        $this->loginAdmin();
        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-validated', 'POST');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertNull($response['data']['selection']['is_validated']);
        $this->assertEquals('undetermined', $response['data']['selection']['status']);
    }

    public function testToggleValidatedFailsForPrivateSelection(): void
    {
        $selection = $this->createSelection(null, 'Private Selection', false);

        $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-validated', 'POST');

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('fail', $response['status']);
    }

    public function testTogglePublicRequiresXhr(): void
    {
        $selection = $this->createSelection(null, 'Public Selection', true);

        // Regular request without XHR header.
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-public', 'POST');

        $this->assertResponseStatusCode(405);
    }

    public function testToggleValidatedRequiresXhr(): void
    {
        $selection = $this->createSelection(null, 'Public Selection', true);

        // Regular request without XHR header.
        $this->dispatch('/admin/selection/' . $selection->id() . '/toggle-validated', 'POST');

        $this->assertResponseStatusCode(405);
    }
}
