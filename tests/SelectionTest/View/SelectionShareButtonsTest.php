<?php declare(strict_types=1);

namespace SelectionTest\View;

use Omeka\Test\AbstractHttpControllerTestCase;
use Selection\Api\Representation\SelectionRepresentation;
use SelectionTest\SelectionTestTrait;

/**
 * Tests of the partial common/selection-share-buttons.
 */
class SelectionShareButtonsTest extends AbstractHttpControllerTestCase
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
        $this->site = $this->createSite('share-test', 'Share test');
        $this->testUser = $this->createUser('share@test.fr', 'toto', 'researcher');

        $services = $this->getServiceLocator();
        $services->get('ViewHelperManager')->get('currentSite')->setSite($this->site);
        $services->get('Omeka\Settings\Site')->setTargetId($this->site->id());
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/s/share-test/selection';
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function hasSharing(): bool
    {
        return $this->getServiceLocator()->get('ViewHelperManager')->has('sharing');
    }

    protected function requireSharing(): void
    {
        if (!$this->hasSharing()) {
            $this->markTestSkipped('Requires Sharing module.');
        }
    }

    protected function setSharing(array $methods, bool $displayAsButton = false): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->set('sharing_methods', $methods);
        $siteSettings->set('sharing_display_as_button', $displayAsButton);
    }

    protected function render(SelectionRepresentation $selection, ?bool $displayAsButton = null): string
    {
        $vars = ['selection' => $selection, 'site' => $this->site];
        if ($displayAsButton !== null) {
            $vars['displayAsButton'] = $displayAsButton;
        }
        $html = $this->getServiceLocator()->get('ViewRenderer')
            ->partial('common/selection-share-buttons', $vars);
        // Attributes are escaped ("%" as "&#x25;"), so decode them.
        return trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
    }

    protected function selectionUrl(SelectionRepresentation $selection): string
    {
        return '/s/share-test/selection/' . $selection->id();
    }

    public function testRendersNothingWithoutSharingModule(): void
    {
        if ($this->hasSharing()) {
            $this->markTestSkipped('Sharing module is installed.');
        }
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $this->assertSame('', $this->render($selection));
    }

    public function testRendersNothingWithoutEnabledMethod(): void
    {
        $this->requireSharing();
        $this->setSharing([]);
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $this->assertSame('', $this->render($selection));
    }

    public function testPublicSelectionSharesSelectionUrl(): void
    {
        $this->requireSharing();
        $this->setSharing(['twitter', 'linkedin', 'embed']);
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $html = $this->render($selection);

        $url = $this->selectionUrl($selection);
        $this->assertStringContainsString('id="sharing-twitter"', $html);
        $this->assertStringContainsString('id="sharing-linkedin"', $html);
        $this->assertStringContainsString('id="sharing-embed"', $html);
        $this->assertStringContainsString(rawurlencode($url), $html);
        $this->assertStringContainsString('data-embed-url="http://example.org' . $url . '"', $html);
    }

    public function testOnlyEnabledMethodsAreRendered(): void
    {
        $this->requireSharing();
        $this->setSharing(['email']);
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $html = $this->render($selection);

        $this->assertStringContainsString('id="sharing-email"', $html);
        $this->assertStringNotContainsString('id="sharing-fb"', $html);
        $this->assertStringNotContainsString('id="sharing-twitter"', $html);
        $this->assertStringNotContainsString('<details', $html);
    }

    public function testEmailSubjectAndBodyAreEncoded(): void
    {
        $this->requireSharing();
        $this->setSharing(['email']);
        $selection = $this->createSelection($this->testUser->id(), 'Mes objets & cartes', true);
        $html = $this->render($selection);

        $this->assertMatchesRegularExpression('~href="mailto:\?subject=([^"&]+)&body=([^"&]+)"~', $html);
        preg_match('~href="mailto:\?subject=([^"&]+)&body=([^"&]+)"~', $html, $matches);
        $this->assertStringStartsWith('Mes%20objets%20%26%20cartes%20', $matches[1]);
        $this->assertStringNotContainsString('+', $matches[1]);
        $this->assertSame(rawurlencode('http://example.org' . $this->selectionUrl($selection)), $matches[2]);
    }

    public function testDisplayAsButtonWrapsInDetails(): void
    {
        $this->requireSharing();
        $this->setSharing(['email'], true);
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $html = $this->render($selection);

        $this->assertStringContainsString('<details class="button-details sharing-details">', $html);
        $this->assertStringContainsString('id="sharing-email"', $html);
    }

    public function testDisplayAsButtonVariableOverridesSetting(): void
    {
        $this->requireSharing();
        $this->setSharing(['email'], true);
        $selection = $this->createSelection($this->testUser->id(), 'Public', true);
        $html = $this->render($selection, false);

        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('id="sharing-email"', $html);
    }

    public function testPrivateSelectionIsNotShared(): void
    {
        $this->requireSharing();
        $this->setSharing(['email', 'twitter']);
        $selection = $this->createSelection($this->testUser->id(), 'Private', false);

        $this->assertSame('', $this->render($selection));
    }

    public function testPrivateSelectionAsButtonShowsDisabledInfo(): void
    {
        $this->requireSharing();
        $this->setSharing(['email', 'twitter'], true);
        $selection = $this->createSelection($this->testUser->id(), 'Private', false);
        $html = $this->render($selection);

        $this->assertStringContainsString('sharing-disabled', $html);
        $this->assertStringContainsString('class="sharing-info"', $html);
        $this->assertStringNotContainsString('id="sharing-email"', $html);
        $this->assertStringNotContainsString($this->selectionUrl($selection), $html);
    }
}
