<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Template;

use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * The module renders through ModuleTemplate, which hands a template name to Fluid and
 * lets Fluid resolve it to a file. Which file names Fluid accepts depends on the TYPO3
 * version the module runs on:
 *
 * * TYPO3 v13 ships Fluid 4, whose resolver only tries "<name>.<format>" and
 *   "<name>". Every template of a v13 installation is named that way, there is not a
 *   single ".fluid.html" file in it. "Index.fluid.html" is not found.
 * * TYPO3 v14 ships Fluid 5, whose resolver tries "<name>.fluid.<format>" first and
 *   then falls back to "<name>.<format>".
 *
 * So "Index.html" is the only name both versions resolve, while "Index.fluid.html"
 * works on v14 only and makes every action die with an InvalidTemplateResourceException
 * on v13. This test resolves everything the module renders with the view the module
 * renders with, which is what the actions do.
 */
final class TemplateResolutionTest extends AbstractFunctionalTestCase
{
    /**
     * The extension itself, relative to this file. The data providers are evaluated
     * before the test instance is built, so they read the source tree instead of
     * asking the instance for a path.
     */
    private const EXTENSION_PATH = __DIR__ . '/../../..';

    protected function setUp(): void
    {
        parent::setUp();
        // the fixture provides the backend user, the module template factory reads the
        // language from the global
        $this->importFixture('page_permissions');
        $backendUser = $this->setUpBackendUser(2);
        $backendUser->fetchGroupData();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);
    }

    /**
     * The template names the controller hands to renderResponse(), for example
     * "NewsBunny/Index".
     *
     * @return \Generator<string, array{string}>
     */
    public static function actionTemplates(): \Generator
    {
        $controller = (string)file_get_contents(self::EXTENSION_PATH . '/Classes/Controller/NewsBunnyController.php');
        preg_match_all("/->renderResponse\('([^']+)'\)/", $controller, $matches);
        self::assertNotEmpty($matches[1], 'the controller renders at least one template');

        foreach ($matches[1] as $templateName) {
            yield $templateName => [$templateName];
        }
    }

    /**
     * Every partial the shipped templates and partials render, as named in
     * <f:render partial="..."/>. Collecting them from the sources keeps the test
     * complete when a template renders another partial.
     *
     * @return \Generator<string, array{string}>
     */
    public static function referencedPartials(): \Generator
    {
        $partialNames = [];
        foreach (self::shippedTemplateFiles() as $file) {
            preg_match_all('/partial="([^"]+)"/', (string)file_get_contents($file), $matches);
            $partialNames = [...$partialNames, ...$matches[1]];
        }
        self::assertNotEmpty($partialNames, 'the shipped templates render at least one partial');

        foreach (array_unique($partialNames) as $partialName) {
            yield $partialName => [$partialName];
        }
    }

    /**
     * Every layout the shipped templates render, as named in <f:layout name="..."/>.
     *
     * @return \Generator<string, array{string}>
     */
    public static function usedLayouts(): \Generator
    {
        $layoutNames = [];
        foreach (self::shippedTemplateFiles() as $file) {
            preg_match_all('/<f:layout\s+name="([^"]+)"/', (string)file_get_contents($file), $matches);
            $layoutNames = [...$layoutNames, ...$matches[1]];
        }
        self::assertNotEmpty($layoutNames, 'the shipped templates use at least one layout');

        foreach (array_unique($layoutNames) as $layoutName) {
            yield $layoutName => [$layoutName];
        }
    }

    #[DataProvider('actionTemplates')]
    public function testTheTemplateOfAnActionIsResolved(string $templateName): void
    {
        // the controller name of a module view is "Default", Fluid then falls back to
        // the template name alone, which is where the templates of this module live
        $resolved = $this->templatePaths()->resolveTemplateFileForControllerAndActionAndFormat('Default', $templateName);

        self::assertIsString($resolved, sprintf('the template "%s" is resolved to a file', $templateName));
        self::assertFileExists($resolved);
        self::assertStringStartsWith(
            $this->extensionPath('Resources/Private/Templates/'),
            $resolved,
            sprintf('the template "%s" is a template of this extension', $templateName)
        );
    }

    #[DataProvider('referencedPartials')]
    public function testAPartialIsResolved(string $partialName): void
    {
        $resolved = $this->templatePaths()->getPartialPathAndFilename($partialName);

        self::assertFileExists($resolved);
        self::assertStringStartsWith(
            $this->extensionPath('Resources/Private/Partials/'),
            $resolved,
            sprintf('the partial "%s" is a partial of this extension', $partialName)
        );
    }

    #[DataProvider('usedLayouts')]
    public function testALayoutIsResolved(string $layoutName): void
    {
        $resolved = $this->templatePaths()->getLayoutPathAndFilename($layoutName);

        self::assertFileExists(
            $resolved,
            sprintf('the layout "%s" is provided by the backend, which always resolves first', $layoutName)
        );
    }

    /**
     * The file name of a shipped template is a contract between this extension and two
     * Fluid versions, so it is asserted in its own right: TYPO3 v13 does not know the
     * ".fluid" file extension and would not find the file at all.
     */
    public function testNoShippedTemplateCarriesTheFluidFileExtension(): void
    {
        foreach (self::shippedTemplateFiles() as $file) {
            self::assertStringNotContainsString(
                '.fluid.',
                basename($file),
                sprintf(
                    'TYPO3 v13 resolves "%s.html" but not "%s.fluid.html", so the file extension of'
                    . ' the template may not contain the Fluid infix',
                    basename($file, '.fluid.html'),
                    basename($file, '.html')
                )
            );
        }
    }

    /**
     * The template paths of the view the module renders with. The backend builds the
     * view of a module template from the package of the current route, so the module
     * finds its own templates behind "typo3/cms-backend".
     */
    private function templatePaths(): TemplatePaths
    {
        $request = new ServerRequest('https://example.com/typo3/main', 'GET');
        $request = $request
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            // the module template factory reads the package of the route to find the
            // templates of the extension behind the module
            ->withAttribute('route', new Route('/typo3/main', ['packageName' => 'news_bunny']))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($request);
        // the view is an implementation detail of the module template, but the module
        // does render with it, which is the view under test here
        $view = (new \ReflectionProperty(ModuleTemplate::class, 'view'))->getValue($moduleTemplate);

        self::assertTrue(
            method_exists($view, 'getRenderingContext'),
            'the module renders with a Fluid view, which exposes its rendering context'
        );

        $templatePaths = $view->getRenderingContext()->getTemplatePaths();
        self::assertInstanceOf(TemplatePaths::class, $templatePaths);

        return $templatePaths;
    }

    private function extensionPath(string $suffix = ''): string
    {
        return ExtensionManagementUtility::extPath('news_bunny', $suffix);
    }

    /**
     * Every template and partial this extension ships.
     *
     * @return string[]
     */
    private static function shippedTemplateFiles(): array
    {
        $files = [];
        foreach (['Templates', 'Partials'] as $directory) {
            $path = self::EXTENSION_PATH . '/Resources/Private/' . $directory;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }
}
