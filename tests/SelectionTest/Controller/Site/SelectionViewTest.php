<?php declare(strict_types=1);

namespace SelectionTest\Controller\Site;

use CommonTest\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests of the personal view (/guest/selection/{id}) and of the public show.
 */
class SelectionViewTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site;

    /**
     * @var \Omeka\Api\Representation\UserRepresentation
     */
    protected $owner;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->site = $this->createSite('view-test', 'View test');
        $this->owner = $this->createUser('viewer@test.fr', 'toto', 'researcher');
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('selection_favorites_visitor', true);
        $siteSettings->set('selection_selections_user', true);
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/s/view-test';
    }

    public function tearDown(): void
    {
        // The dispatch keeps entities of its own application in the entity
        // manager, so clean up from a new application.
        $this->logout();
        $this->reset();
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function createOwnedSelection(string $label, bool $isPublic = false)
    {
        $selection = $this->createSelection($this->owner->id(), $label, $isPublic, $isPublic ?: null);
        $this->createSelectionResource($selection->id(), $this->createItem('Item')->id());
        return $selection;
    }

    public function testOwnerViewsSelectionById(): void
    {
        $this->createOwnedSelection('First selection');
        $selection = $this->createOwnedSelection('Second selection');

        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/guest/selection/' . $selection->id());

        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('h2', 'Second selection');
    }

    public function testOwnerViewsSelectionWithViewAction(): void
    {
        $selection = $this->createOwnedSelection('Viewed selection');

        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/guest/selection/' . $selection->id() . '/view');

        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('h2', 'Viewed selection');
    }

    public function dispositionProvider(): array
    {
        return [
            'grouped' => ['grouped'],
            'hierarchy' => ['hierarchy'],
        ];
    }

    /**
     * @dataProvider dispositionProvider
     */
    public function testOwnerViewsSelectionWithGroups(string $disposition): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('selection_disposition', $disposition);
        $selection = $this->createOwnedSelection('Grouped selection');
        $this->api()->update('selections', $selection->id(), [
            'o:structure' => [
                '/Folder A' => ['id' => 'Folder A', 'path' => '/', 'resources' => []],
                '/Folder A/Sub' => ['id' => 'Sub', 'path' => '/Folder A'],
            ],
        ], [], ['isPartial' => true]);

        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/guest/selection/' . $selection->id());

        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('h2', 'Grouped selection');
        $this->assertStringContainsString('Folder A', $this->getResponse()->getContent());
    }

    public function testUnknownSelectionIsNotFound(): void
    {
        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/guest/selection/999999');

        $this->assertResponseStatusCode(404);
    }

    public function testSelectionOfAnotherUserIsNotFound(): void
    {
        $other = $this->createUser('other-viewer@test.fr', 'toto', 'researcher');
        $selection = $this->createSelection($other->id(), 'Other selection');

        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/guest/selection/' . $selection->id());

        $this->assertResponseStatusCode(404);
        $this->assertStringNotContainsString('Other selection', $this->getResponse()->getContent());
    }

    public function testVisitorViewsDefaultSessionSelection(): void
    {
        $this->logout();
        $this->dispatchUnauthenticated('/s/view-test/guest/selection/1');

        $this->assertResponseStatusCode(200);
    }

    public function testVisitorUnknownSessionSelectionIsNotFound(): void
    {
        $this->logout();
        $this->dispatchUnauthenticated('/s/view-test/guest/selection/5');

        $this->assertResponseStatusCode(404);
    }

    public function testOwnerViewsOwnPublicShowWithActions(): void
    {
        $selection = $this->createOwnedSelection('Owned public selection', true);

        $this->loginAs($this->owner->id());
        $this->dispatch('/s/view-test/selection/' . $selection->id());

        $this->assertResponseStatusCode(200);
        $this->assertQuery('.selection-delete');
    }

    public function testPublicShowSharesSelectionLabelUnescaped(): void
    {
        if (!$this->getServiceLocator()->get('ViewHelperManager')->has('sharing')) {
            $this->markTestSkipped('Requires Sharing module.');
        }
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('sharing_methods', ['email']);
        $siteSettings->set('sharing_display_as_button', false);
        $selection = $this->createOwnedSelection('Maps & charts', true);

        $this->dispatchUnauthenticated('/s/view-test/selection/' . $selection->id());

        $this->assertResponseStatusCode(200);
        $html = html_entity_decode($this->getResponse()->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertSame(1, preg_match('~href="mailto:\?subject=([^"&]+)&body=([^"&]+)"~', $html, $matches));
        $this->assertStringStartsWith('Maps%20%26%20charts%20', $matches[1]);
        $this->assertStringNotContainsString('amp', $matches[1]);
        $this->assertStringEndsWith(rawurlencode('/s/view-test/selection/' . $selection->id()), $matches[2]);
    }
}
