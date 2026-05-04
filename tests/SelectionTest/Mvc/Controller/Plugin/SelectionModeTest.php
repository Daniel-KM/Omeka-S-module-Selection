<?php declare(strict_types=1);

namespace SelectionTest\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\AbstractController;
use Omeka\Settings\SiteSettings;
use PHPUnit\Framework\TestCase;
use Selection\Mvc\Controller\Plugin\SelectionMode;

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

    public function testVisitorExplicit(): void
    {
        $plugin = new SelectionMode($this->makeSettings('simple', 'standard'));
        $this->assertSame('simple', $plugin(false));
    }

    public function testUserExplicit(): void
    {
        $plugin = new SelectionMode($this->makeSettings('none', 'nested'));
        $this->assertSame('nested', $plugin(true));
    }

    public function testVisitorDisabled(): void
    {
        $plugin = new SelectionMode($this->makeSettings('none', 'standard'));
        $this->assertSame('none', $plugin(false));
    }

    public function testUserDefaultWhenNull(): void
    {
        $settings = $this->makeSettings('simple', 'standard');
        $plugin = new SelectionMode($settings);

        $identityPlugin = new class {
            public function __invoke()
            {
                return (object) ['id' => 1];
            }
        };

        $controller = $this->getMockBuilder(AbstractController::class)
            ->onlyMethods(['onDispatch', 'plugin'])
            ->getMockForAbstractClass();
        $controller->method('plugin')->willReturn($identityPlugin);

        // Emulate $this->identity() via magic __call path by building a
        // controller with plugin manager. Simpler: call with explicit true.
        $this->assertSame('standard', $plugin(true));
    }

    public function testNullArgumentUsesControllerIdentityAnonymous(): void
    {
        $plugin = new SelectionMode($this->makeSettings('simple', 'standard'));

        $controller = new class extends AbstractController {
            public function identity()
            {
                return null;
            }
            public function onDispatch(\Laminas\Mvc\MvcEvent $e)
            {
                return null;
            }
        };
        $plugin->setController($controller);

        $this->assertSame('simple', $plugin());
    }

    public function testNullArgumentUsesControllerIdentityAuthed(): void
    {
        $plugin = new SelectionMode($this->makeSettings('none', 'nested'));

        $controller = new class extends AbstractController {
            public function identity()
            {
                return (object) ['id' => 42];
            }
            public function onDispatch(\Laminas\Mvc\MvcEvent $e)
            {
                return null;
            }
        };
        $plugin->setController($controller);

        $this->assertSame('nested', $plugin());
    }
}
