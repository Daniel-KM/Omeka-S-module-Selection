<?php declare(strict_types=1);

namespace SelectionTest\View;

use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests of the view helper selectionButtonToSelect.
 */
class SelectionButtonToSelectTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

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
        $this->site = $this->createSite('select-test', 'Select test');
        $this->testUser = $this->createUser('select@test.fr', 'toto', 'researcher');

        $services = $this->getServiceLocator();
        $services->get('ViewHelperManager')->get('currentSite')->setSite($this->site);
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('selection_selections_user', true);
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/s/select-test/item';
    }

    public function tearDown(): void
    {
        // The admin login is skipped while the test user is logged in.
        $this->getServiceLocator()->get('Omeka\AuthenticationService')->clearIdentity();
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
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
    public function testGroupsAreRenderedAsOptgroups(string $disposition): void
    {
        $this->getServiceLocator()->get('Omeka\Settings\Site')->set('selection_disposition', $disposition);
        $item = $this->createItem('Item');
        $selection = $this->createSelection($this->testUser->id(), 'My folders');
        $this->api()->update('selections', $selection->id(), [
            'o:structure' => [
                '/Folder A' => ['id' => 'Folder A', 'path' => '/', 'resources' => [$item->id()]],
                '/Folder A/Sub' => ['id' => 'Sub', 'path' => '/Folder A'],
            ],
        ], [], ['isPartial' => true]);

        $this->login('select@test.fr', 'toto');
        $html = html_entity_decode(
            $this->getServiceLocator()->get('ViewHelperManager')->get('selectionButtonToSelect')($item),
            ENT_QUOTES | ENT_HTML5
        );

        $this->assertStringContainsString('<optgroup label="My folders">', $html);
        $this->assertStringContainsString('value="' . $selection->id() . ':/Folder A"', $html);
        $this->assertStringContainsString('value="' . $selection->id() . ':/Folder A/Sub"', $html);
    }
}
