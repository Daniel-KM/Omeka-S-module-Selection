<?php declare(strict_types=1);

namespace SelectionTest\Upgrade;

use Omeka\Settings\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Tests the mapping block from data/scripts/upgrade.php (v3.4.18).
 *
 * The mapping logic is replicated here against a mock SiteSettings. It mirrors
 * lines 556-566 of upgrade.php verbatim.
 */
class UpgradeMappingTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $store = [];

    private function makeSettings(): SiteSettings
    {
        $settings = $this->createMock(SiteSettings::class);
        $settings->method('get')->willReturnCallback(
            function (string $key, $default = null) {
                return $this->store[$key] ?? $default;
            }
        );
        $settings->method('set')->willReturnCallback(
            function (string $key, $value): void {
                $this->store[$key] = $value;
            }
        );
        $settings->method('delete')->willReturnCallback(
            function (string $key): void {
                unset($this->store[$key]);
            }
        );
        return $settings;
    }

    private function runMapping(SiteSettings $siteSettings): void
    {
        $disable = (bool) $siteSettings->get('selection_disable_anonymous', false);
        $mode = $siteSettings->get('selection_mode', 'single');
        $siteSettings->set('selection_mode_visitor', $disable ? 'none' : 'simple');
        $siteSettings->set('selection_mode_user', $mode === 'multiple' ? 'standard' : 'simple');
        $siteSettings->delete('selection_disable_anonymous');
        $siteSettings->delete('selection_mode');
    }

    public function testDisabledAnonymousMapsVisitorToNone(): void
    {
        $this->store = ['selection_disable_anonymous' => true, 'selection_mode' => 'single'];
        $this->runMapping($this->makeSettings());
        $this->assertSame('none', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }

    public function testAllowedAnonymousMapsVisitorToSimple(): void
    {
        $this->store = ['selection_disable_anonymous' => false, 'selection_mode' => 'single'];
        $this->runMapping($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }

    public function testMultipleModeMapsUserToStandard(): void
    {
        $this->store = ['selection_disable_anonymous' => false, 'selection_mode' => 'multiple'];
        $this->runMapping($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('standard', $this->store['selection_mode_user']);
    }

    public function testLegacyKeysDeleted(): void
    {
        $this->store = ['selection_disable_anonymous' => true, 'selection_mode' => 'multiple'];
        $this->runMapping($this->makeSettings());
        $this->assertArrayNotHasKey('selection_disable_anonymous', $this->store);
        $this->assertArrayNotHasKey('selection_mode', $this->store);
    }

    public function testDefaultsWhenMissing(): void
    {
        $this->store = [];
        $this->runMapping($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }
}
