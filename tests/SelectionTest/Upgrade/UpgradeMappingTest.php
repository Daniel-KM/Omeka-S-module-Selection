<?php declare(strict_types=1);

namespace SelectionTest\Upgrade;

use Omeka\Settings\SiteSettings;
use PHPUnit\Framework\TestCase;

/**
 * Tests the mapping blocks from data/scripts/upgrade.php.
 *
 * 3.4.18: legacy single-mode → mode_visitor / mode_user.
 * 3.4.19: mode_* + my_favorites → favorites_* + selections_* (two-axis).
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

    private function runMapping3418(SiteSettings $siteSettings): void
    {
        $disable = (bool) $siteSettings->get('selection_disable_anonymous', false);
        $mode = $siteSettings->get('selection_mode', 'single');
        $siteSettings->set('selection_mode_visitor', $disable ? 'none' : 'simple');
        $siteSettings->set('selection_mode_user', $mode === 'multiple' ? 'standard' : 'simple');
        $siteSettings->delete('selection_disable_anonymous');
        $siteSettings->delete('selection_mode');
    }

    private function runMapping3419(SiteSettings $siteSettings): void
    {
        $modeVisitor = $siteSettings->get('selection_mode_visitor', 'none');
        $modeUser = $siteSettings->get('selection_mode_user', 'standard');
        $myFavorites = (bool) $siteSettings->get('selection_my_favorites', true);
        $disposition = $siteSettings->get('selection_disposition', 'flat');
        $labelVisitor = $siteSettings->get('selection_label_visitor', '');
        $labelUser = $siteSettings->get('selection_label_user', '');
        $labelFavorites = $siteSettings->get('selection_label_favorites', '');

        $favoritesVisitor = $modeVisitor === 'simple'
            || ($modeVisitor !== 'none' && $myFavorites);
        $selectionsVisitor = in_array($modeVisitor, ['standard', 'nested'], true);
        $siteSettings->set('selection_favorites_visitor', $favoritesVisitor);
        $siteSettings->set('selection_selections_visitor', $selectionsVisitor);

        $favoritesUser = $modeUser === 'simple' || $myFavorites;
        $selectionsUser = in_array($modeUser, ['standard', 'nested'], true);
        $siteSettings->set('selection_favorites_user', $favoritesUser);
        $siteSettings->set('selection_selections_user', $selectionsUser);

        if (($modeVisitor === 'nested' || $modeUser === 'nested') && $disposition === 'flat') {
            $siteSettings->set('selection_disposition', 'hierarchy');
        }

        if ($labelFavorites === '' || $labelFavorites === null) {
            $siteSettings->set('selection_label_favorites', $labelVisitor ?: $labelUser ?: 'My favorites');
        }
        $siteSettings->set('selection_label_selections', $labelUser ?: 'Selections');

        $siteSettings->delete('selection_mode_visitor');
        $siteSettings->delete('selection_mode_user');
        $siteSettings->delete('selection_my_favorites');
        $siteSettings->delete('selection_label_visitor');
        $siteSettings->delete('selection_label_user');
    }

    public function testDisabledAnonymousMapsVisitorToNone(): void
    {
        $this->store = ['selection_disable_anonymous' => true, 'selection_mode' => 'single'];
        $this->runMapping3418($this->makeSettings());
        $this->assertSame('none', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }

    public function testAllowedAnonymousMapsVisitorToSimple(): void
    {
        $this->store = ['selection_disable_anonymous' => false, 'selection_mode' => 'single'];
        $this->runMapping3418($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }

    public function testMultipleModeMapsUserToStandard(): void
    {
        $this->store = ['selection_disable_anonymous' => false, 'selection_mode' => 'multiple'];
        $this->runMapping3418($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('standard', $this->store['selection_mode_user']);
    }

    public function testLegacyKeysDeleted(): void
    {
        $this->store = ['selection_disable_anonymous' => true, 'selection_mode' => 'multiple'];
        $this->runMapping3418($this->makeSettings());
        $this->assertArrayNotHasKey('selection_disable_anonymous', $this->store);
        $this->assertArrayNotHasKey('selection_mode', $this->store);
    }

    public function testDefaultsWhenMissing(): void
    {
        $this->store = [];
        $this->runMapping3418($this->makeSettings());
        $this->assertSame('simple', $this->store['selection_mode_visitor']);
        $this->assertSame('simple', $this->store['selection_mode_user']);
    }

    public function testTwoAxisNoneVisitorMapsToBothFalse(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'none',
            'selection_mode_user' => 'simple',
            'selection_my_favorites' => false,
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertFalse($this->store['selection_favorites_visitor']);
        $this->assertFalse($this->store['selection_selections_visitor']);
        $this->assertTrue($this->store['selection_favorites_user']);
        $this->assertFalse($this->store['selection_selections_user']);
    }

    public function testTwoAxisSimpleMapsToFavoritesOnly(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'simple',
            'selection_mode_user' => 'simple',
            'selection_my_favorites' => false,
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertTrue($this->store['selection_favorites_visitor']);
        $this->assertFalse($this->store['selection_selections_visitor']);
        $this->assertTrue($this->store['selection_favorites_user']);
        $this->assertFalse($this->store['selection_selections_user']);
    }

    public function testTwoAxisStandardMapsToSelectionsOnlyWhenMyFavoritesOff(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'standard',
            'selection_mode_user' => 'standard',
            'selection_my_favorites' => false,
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertFalse($this->store['selection_favorites_visitor']);
        $this->assertTrue($this->store['selection_selections_visitor']);
        $this->assertFalse($this->store['selection_favorites_user']);
        $this->assertTrue($this->store['selection_selections_user']);
    }

    public function testTwoAxisStandardMapsToBothWhenMyFavoritesOn(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'standard',
            'selection_mode_user' => 'standard',
            'selection_my_favorites' => true,
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertTrue($this->store['selection_favorites_visitor']);
        $this->assertTrue($this->store['selection_selections_visitor']);
        $this->assertTrue($this->store['selection_favorites_user']);
        $this->assertTrue($this->store['selection_selections_user']);
    }

    public function testTwoAxisNestedBumpsDispositionToHierarchy(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'simple',
            'selection_mode_user' => 'nested',
            'selection_disposition' => 'flat',
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertSame('hierarchy', $this->store['selection_disposition']);
    }

    public function testTwoAxisLabelsMigrated(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'simple',
            'selection_mode_user' => 'standard',
            'selection_label_visitor' => 'Mes coups de coeur',
            'selection_label_user' => 'Mes listes',
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertSame('Mes coups de coeur', $this->store['selection_label_favorites']);
        $this->assertSame('Mes listes', $this->store['selection_label_selections']);
    }

    public function testTwoAxisLegacyKeysDeleted(): void
    {
        $this->store = [
            'selection_mode_visitor' => 'simple',
            'selection_mode_user' => 'standard',
            'selection_my_favorites' => true,
            'selection_label_visitor' => 'X',
            'selection_label_user' => 'Y',
        ];
        $this->runMapping3419($this->makeSettings());
        $this->assertArrayNotHasKey('selection_mode_visitor', $this->store);
        $this->assertArrayNotHasKey('selection_mode_user', $this->store);
        $this->assertArrayNotHasKey('selection_my_favorites', $this->store);
        $this->assertArrayNotHasKey('selection_label_visitor', $this->store);
        $this->assertArrayNotHasKey('selection_label_user', $this->store);
    }
}
