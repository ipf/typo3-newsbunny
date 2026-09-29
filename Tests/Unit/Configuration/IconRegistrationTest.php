<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Guards the registration of the icons of the module.
 *
 * The two Core versions draw the icons in two different languages, so the registration
 * picks one of two sets of files. Nothing else in the extension knows about that, which
 * means a wrong file or a wrong condition would only be visible in the module menu of an
 * editor. These tests keep the choice and both sets checkable.
 */
final class IconRegistrationTest extends TestCase
{
    private const EXTENSION_ROOT = __DIR__ . '/../../..';

    /**
     * @var array<string, array{provider: string, source: string}>
     */
    private array $icons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->icons = (array)require self::EXTENSION_ROOT . '/Configuration/Icons.php';
    }

    public function testTheIconsAreRegisteredForTheIconSvgProvider(): void
    {
        foreach ($this->icons as $identifier => $icon) {
            self::assertSame(SvgIconProvider::class, $icon['provider'], $identifier . ' uses another provider');
        }
    }

    public function testTheModuleAndItsStoragePageIconAreRegistered(): void
    {
        self::assertSame(
            ['module-newsbunny', 'module-newsbunny-storage-pages'],
            array_keys($this->icons)
        );
    }

    public function testTheModuleMenuIsRegisteredWithTheIconOfTheModule(): void
    {
        $modules = (array)require self::EXTENSION_ROOT . '/Configuration/Backend/Modules.php';

        self::assertArrayHasKey('web_newsBunny', $modules);
        self::assertSame(
            'module-newsbunny',
            $modules['web_newsBunny']['iconIdentifier'],
            'the module menu draws the icon under another identifier than the registered one'
        );
    }

    #[DataProvider('iconFileProvider')]
    public function testTheRegisteredFileExists(string $identifier): void
    {
        self::assertFileExists($this->absoluteIconPath($identifier));
    }

    /**
     * TYPO3 13 and 14 use a different icon language, so the registration has to follow
     * the version that runs, and only that version may be behind the condition.
     */
    #[DataProvider('iconFileProvider')]
    public function testTheFileOfTheRunningCoreVersionIsRegistered(string $identifier): void
    {
        $expectedDirectory = (new Typo3Version())->getMajorVersion() < 14 ? 'Icons/v13' : 'Icons';

        self::assertStringContainsString(
            '/Resources/Public/' . $expectedDirectory . '/',
            $this->icons[$identifier]['source'],
            $identifier . ' is not the drawing of the icon language of this Core version'
        );
    }

    /**
     * The v14 files are line art for the theme: the silhouette follows the text color and
     * the accent comes from the CSS token, so the backend surface stays visible and the
     * icon works in the light and in the dark scheme.
     */
    #[DataProvider('v14IconProvider')]
    public function testTheV14IconIsLineArtOfTheTheme(string $file): void
    {
        $svg = $this->readIcon($file);

        self::assertStringContainsString('viewBox="0 0 64 64"', $svg, $file . ' has the wrong viewBox for a module icon');
        self::assertStringContainsString('currentColor', $svg, $file . ' does not follow the text color of the theme');
        self::assertStringContainsString('var(--icon-color-accent', $svg, $file . ' has no accent from the theme token');
        self::assertDoesNotMatchRegularExpression(
            '#<rect[^>]+width="64"[^>]+height="64"#',
            $svg,
            $file . ' draws a background box, which covers the color scheme of the theme'
        );
    }

    /**
     * The file for 13 is the module icon of the module menu, and there the Core draws a
     * white symbol on a full-bleed box of a fixed color.
     */
    public function testTheV13ModuleIconIsAWhiteSymbolOnAFullBleedBox(): void
    {
        $svg = $this->readIcon('v13/module-newsbunny.svg');

        self::assertStringContainsString('viewBox="0 0 64 64"', $svg);
        self::assertMatchesRegularExpression('#<rect[^>]+width="64"[^>]+height="64"[^>]+fill="#', $svg);
        self::assertStringContainsString('fill="#FFF"', $svg);
    }

    /**
     * The other file for 13 is not drawn for the module menu but for the button of the
     * doc header, which is 16px, so it follows the small inline icons of 13: monochrome in
     * currentColor, no box.
     */
    public function testTheV13StoragePageIconIsAMonochromeSmallIcon(): void
    {
        $svg = $this->readIcon('v13/module-newsbunny-storage-pages.svg');

        self::assertStringContainsString('viewBox="0 0 16 16"', $svg);
        self::assertStringContainsString('currentColor', $svg);
        self::assertDoesNotMatchRegularExpression('#<rect#', $svg, 'a box would be a colored square in a 16px button');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function iconFileProvider(): array
    {
        return [
            'module' => ['module-newsbunny'],
            'storage pages' => ['module-newsbunny-storage-pages'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function v14IconProvider(): array
    {
        return [
            'module' => ['module-newsbunny.svg'],
            'storage pages' => ['module-newsbunny-storage-pages.svg'],
        ];
    }

    private function absoluteIconPath(string $identifier): string
    {
        return str_replace(
            'EXT:news_bunny/',
            self::EXTENSION_ROOT . '/',
            $this->icons[$identifier]['source']
        );
    }

    private function readIcon(string $relativePath): string
    {
        $path = self::EXTENSION_ROOT . '/Resources/Public/Icons/' . $relativePath;
        self::assertFileExists($path);

        return (string)file_get_contents($path);
    }
}
