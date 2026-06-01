<?php declare(strict_types=1);

namespace SelectionTest\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\AbstractController;
use Omeka\Settings\SiteSettings;
use PHPUnit\Framework\TestCase;
use Selection\Mvc\Controller\Plugin\SelectionFavoritesEnabled;

class SelectionFavoritesEnabledTest extends TestCase
{
    private function makeSettings(bool $visitor, bool $user): SiteSettings
    {
        $settings = $this->createMock(SiteSettings::class);
        $settings->method('get')->willReturnCallback(
            static function (string $key, $default = null) use ($visitor, $user) {
                if ($key === 'selection_favorites_visitor') {
                    return $visitor;
                }
                if ($key === 'selection_favorites_user') {
                    return $user;
                }
                return $default;
            }
        );
        return $settings;
    }

    public function testVisitorExplicit(): void
    {
        $plugin = new SelectionFavoritesEnabled($this->makeSettings(true, false));
        $this->assertTrue($plugin(false));
    }

    public function testUserExplicit(): void
    {
        $plugin = new SelectionFavoritesEnabled($this->makeSettings(false, true));
        $this->assertTrue($plugin(true));
    }

    public function testVisitorDisabled(): void
    {
        $plugin = new SelectionFavoritesEnabled($this->makeSettings(false, true));
        $this->assertFalse($plugin(false));
    }

    public function testNullArgumentUsesControllerIdentityAnonymous(): void
    {
        $plugin = new SelectionFavoritesEnabled($this->makeSettings(true, false));

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

        $this->assertTrue($plugin());
    }

    public function testNullArgumentUsesControllerIdentityAuthed(): void
    {
        $plugin = new SelectionFavoritesEnabled($this->makeSettings(false, true));

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

        $this->assertTrue($plugin());
    }
}
