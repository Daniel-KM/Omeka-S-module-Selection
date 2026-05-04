<?php declare(strict_types=1);

namespace SelectionTest\View\Helper;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Settings\SiteSettings;
use PHPUnit\Framework\TestCase;
use Selection\View\Helper\SelectionMode;

class SelectionModeTest extends TestCase
{
    private function makeSettings(string $visitor, string $user): SiteSettings
    {
        $settings = $this->createMock(SiteSettings::class);
        $settings->method('get')->willReturnCallback(
            static function (string $key, $default = null) use ($visitor, $user) {
                if ($key === 'selection_mode_visitor') {
                    return $visitor;
                }
                if ($key === 'selection_mode_user') {
                    return $user;
                }
                return $default;
            }
        );
        return $settings;
    }

    private function makeView($identity): PhpRenderer
    {
        $view = $this->getMockBuilder(PhpRenderer::class)
            ->onlyMethods(['plugin'])
            ->getMock();
        $identityHelper = new class($identity) {
            private $identity;
            public function __construct($identity) { $this->identity = $identity; }
            public function __invoke() { return $this->identity; }
        };
        $view->method('plugin')->willReturnCallback(
            static function ($name) use ($identityHelper) {
                return $identityHelper;
            }
        );
        return $view;
    }

    public function testExplicitVisitor(): void
    {
        $helper = new SelectionMode($this->makeSettings('simple', 'standard'));
        $this->assertSame('simple', $helper(false));
    }

    public function testExplicitUser(): void
    {
        $helper = new SelectionMode($this->makeSettings('none', 'nested'));
        $this->assertSame('nested', $helper(true));
    }

    public function testVisitorDisabled(): void
    {
        $helper = new SelectionMode($this->makeSettings('none', 'standard'));
        $this->assertSame('none', $helper(false));
    }

    public function testUsesViewIdentityAnonymous(): void
    {
        $helper = new SelectionMode($this->makeSettings('simple', 'standard'));
        $helper->setView($this->makeView(null));
        $this->assertSame('simple', $helper());
    }

    public function testUsesViewIdentityAuthed(): void
    {
        $helper = new SelectionMode($this->makeSettings('none', 'nested'));
        $helper->setView($this->makeView((object) ['id' => 5]));
        $this->assertSame('nested', $helper());
    }
}
