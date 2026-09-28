<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Template;

use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the page tree with the view the module renders with, from the branch array
 * the controller builds. The template resolution test only proves that a partial is
 * found on disk, not that Fluid accepts it, and the number of news records is
 * rendered through a partial of its own.
 */
final class PageTreeRenderTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixture('page_permissions');
        $backendUser = $this->setUpBackendUser(2);
        $backendUser->fetchGroupData();
        // the module template factory reads the language from the global
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);
    }

    public function testThePageTreeRendersTheNumberOfNewsRecords(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 5, 5, [
                2 => $this->branch(2, 'News', '/module?id=2', 1, 3, 3),
            ]),
        ]);

        self::assertStringContainsString('News', $html);
        self::assertStringContainsString('>3<', $html);
        self::assertStringContainsString('>5<', $html);
    }

    public function testAPageWithRecordsOnlyBelowShowsTheNumberOfItsSubpages(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 0, 12, [
                2 => $this->branch(2, 'News', '/module?id=2', 1, 12, 12),
            ]),
        ]);

        // a zero next to the records below it says nothing, so such a page shows the
        // number of its subpages alone
        self::assertStringNotContainsString('>0<', $html);
        self::assertStringContainsString('>12<', $html);
        self::assertStringContainsString('12 news records on this page and its subpages', $html);
    }

    public function testAPageWithoutNewsRecordsIsRenderedWithAZero(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 0, 0, [
                2 => $this->branch(2, 'News', '/module?id=2', 1, 0, 0),
            ]),
        ]);

        self::assertStringContainsString('>0<', $html);
        self::assertStringContainsString('No news records', $html);
    }

    public function testASingleRecordIsNotPlural(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 1, 1),
        ]);

        self::assertStringContainsString('1 news record', $html);
        self::assertStringNotContainsString('1 news records', $html);
    }

    public function testTheNumberIsAnnouncedToAScreenReader(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 0, 0, [
                2 => $this->branch(2, 'News', '/module?id=2', 1, 4, 4),
            ]),
        ]);

        // the visible number is hidden from the screen reader, the text below is for it
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertStringContainsString('visually-hidden', $html);
        self::assertStringContainsString('4 news records', $html);
    }

    public function testAPageWithTheSameNumberOnItAndBelowShowsTheNumberOnlyOnce(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 7, 7),
        ]);

        self::assertSame(1, substr_count($html, '>7<'), 'the total adds nothing to the count of the page');
    }

    public function testOnlyTheClassesOfTheBackendAreUsed(): void
    {
        $html = $this->render([
            1 => $this->branch(1, 'Root', '/module?id=1', 0, 4, 9),
        ]);

        // "text-bg-light" is a Bootstrap 5.2 utility, the module also runs on TYPO3 v13
        self::assertStringNotContainsString('text-bg-', $html);
        self::assertStringContainsString('badge badge-info', $html);
        self::assertStringContainsString('badge badge-secondary', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function branch(
        int $uid,
        string $title,
        string $url,
        int $level,
        int $newsCount,
        int $newsTotal,
        array $children = []
    ): array {
        return [
            'uid' => $uid,
            'title' => $title,
            'path' => 'Root / ' . $title,
            'hidden' => false,
            'current' => false,
            'expanded' => false,
            'newsCount' => $newsCount,
            'newsTotal' => $newsTotal,
            'hasNews' => $newsTotal > 0,
            'hasOwnNews' => $newsCount > 0,
            'hasSubTotal' => $newsTotal > $newsCount,
            'level' => $level * 12,
            'url' => $url,
            'children' => $children,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $branches
     */
    private function render(array $branches): string
    {
        // the module renders the partial with arguments="{_all}", so the partial finds
        // the tree under "pageTree"
        return (string)$this->templateView()->renderPartial('NewsBunny/PageTree', null, [
            'pageTree' => [
                'show' => true,
                'allPagesUrl' => '/module?id=0',
                'allPagesCurrent' => false,
                'branches' => $branches,
            ],
        ]);
    }

    private function templateView(): FluidViewAdapter
    {
        $serverRequest = (new ServerRequest('https://example.com/typo3/main', 'GET'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest(
                new ServerRequest('https://example.com/typo3/main', 'GET')
            ))
            ->withAttribute('route', new Route('/typo3/main', ['packageName' => 'news_bunny']))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($serverRequest);
        self::assertInstanceOf(ModuleTemplate::class, $moduleTemplate);

        $view = (new \ReflectionProperty(ModuleTemplate::class, 'view'))->getValue($moduleTemplate);
        // The partials of the module resolve their short translation keys through the
        // request of the Extbase controller, which is where the extension name of the
        // labels comes from. Outside of that request the keys cannot be resolved.
        $extbaseParameters = (new ExtbaseRequestParameters())
            ->setControllerExtensionName('NewsBunny');
        $view->getRenderingContext()->setAttribute(
            ServerRequestInterface::class,
            GeneralUtility::makeInstance(
                ExtbaseRequest::class,
                $serverRequest->withAttribute('extbase', $extbaseParameters)
            )
        );

        return $view;
    }
}
