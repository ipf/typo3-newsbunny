<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Template;

use Ipf\NewsBunny\Service\SettingsProvider;
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
 * Renders the record table with the view the module renders with.
 *
 * Two things are decided in the markup and not in the browser: the widths of the columns
 * and the cell of a record that has no state. The table renders with a fixed layout, so
 * the colgroup is the only thing that decides how wide a column is, and a placeholder
 * would be the only thing an editor reads in a status column of a normal record.
 */
final class RecordTableRenderTest extends AbstractFunctionalTestCase
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

    /**
     * Without a fixed layout the browser hands the space of the table to the column with
     * the longest content, which is the teaser, and the other columns lose their width.
     */
    public function testTheTableIsRenderedWithAFixedLayout(): void
    {
        self::assertStringContainsString('table-layout: fixed', $this->render());
    }

    /**
     * A table with a fixed layout lets content overflow a column instead of widening
     * itself, so the badges of the status column would sit on top of the text of the next
     * column. The min-width is what the wrapper scrolls for.
     */
    public function testTheTableHasAMinWidthToScrollWith(): void
    {
        $html = $this->render(['title', 'datetime', 'categories', 'status']);

        self::assertStringContainsString('min-width: ' . (4 + 1) * 9 . 'rem', $html);
    }

    public function testTheMinWidthGrowsWithTheCountOfColumns(): void
    {
        $few = $this->minWidthOf($this->render(['title', 'datetime']));
        $many = $this->minWidthOf($this->render(['title', 'datetime', 'categories', 'tags', 'status']));

        self::assertGreaterThan($few, $many);
    }

    public function testTheTableScrollsOnBothCoreVersions(): void
    {
        $html = $this->render();

        // the wrapper of the Core tables in TYPO3 14
        self::assertStringContainsString('table-fit', $html);
        // the wrapper that scrolls the table horizontally in TYPO3 13
        self::assertStringContainsString('table-responsive', $html);
    }

    public function testEveryColumnOfTheListGetsAColOfTheColgroup(): void
    {
        $html = $this->render(['title', 'teaser', 'datetime', 'categories', 'status']);

        // one col per column, and the last one for the buttons of the actions
        self::assertSame(6, substr_count($html, '<col '));
    }

    public function testTheWidthsOfTheColgroupFillTheTable(): void
    {
        $widths = $this->widthsOfColgroup($this->render(['title', 'teaser', 'datetime', 'categories', 'status']));

        // a fixed layout hands the space that is left over to the last column, so a sum
        // below 100 would blow up the column of the buttons
        self::assertSame(100, array_sum($widths));
    }

    public function testTheColumnOfTheButtonsHasAWidthOfItsOwn(): void
    {
        $widths = $this->widthsOfColgroup($this->render(['title', 'teaser', 'datetime', 'categories', 'status']));

        self::assertGreaterThanOrEqual(12, end($widths), 'the buttons of a record need room');
    }

    public function testTheDateKeepsOneLine(): void
    {
        $html = $this->render(['title', 'datetime']);

        // a date that wraps into two lines is the reason the colgroup exists
        self::assertStringContainsString('<th class="nb-col-datetime text-nowrap">', $html);
        self::assertStringContainsString('<td class="nb-col-datetime text-nowrap">', $html);
    }

    public function testTheTitleMayWrap(): void
    {
        $html = $this->render(['title']);

        self::assertStringContainsString('<th class="nb-col-title">', $html);
    }

    /**
     * The Core brings its own column classes for its tables under the same names, and
     * ".table .col-title" is 99 percent wide there, which would win over the colgroup.
     */
    public function testTheColumnClassesDoNotCollideWithTheOnesOfTheCore(): void
    {
        $html = $this->render(['title', 'teaser', 'datetime', 'categories', 'status']);

        // the class of a cell always starts with the namespace of the extension
        preg_match_all('#class="([^"]*)"#', $html, $matches);
        foreach ($matches[1] as $class) {
            foreach (explode(' ', $class) as $name) {
                if (str_starts_with($name, 'col-')) {
                    self::fail('the class "' . $name . '" is a column class of the Core');
                }
            }
        }
    }

    public function testARecordWithoutAStateLeavesTheStatusCellEmpty(): void
    {
        $record = $this->record();
        $record['isTopNews'] = false;
        $record['isArchived'] = false;
        $record['isTimeRestricted'] = false;

        $html = $this->renderCell('status', $record);

        self::assertSame('', trim(strip_tags($html)));
        self::assertStringNotContainsString('-', $html);
    }

    public function testTheBadgesOfTheStatesAreStillRendered(): void
    {
        $record = $this->record();
        $record['isTopNews'] = true;
        $record['isArchived'] = true;
        $record['isTimeRestricted'] = true;

        $html = $this->renderCell('status', $record);

        self::assertSame(3, substr_count($html, '<span class="badge'));
        // the placeholder is gone, not replaced by another one
        self::assertSame(3, substr_count($html, '<span'));
    }

    public function testTheTitleOfARecordIsRenderedWithItsLink(): void
    {
        $html = $this->renderCell('title', $this->record());

        self::assertStringContainsString('<a href="/edit" class="fw-bold">', $html);
        self::assertStringContainsString('Fragen zur SUB?', $html);
    }

    /**
     * @param string[] $columns
     */
    private function render(array $columns = ['title', 'teaser', 'datetime', 'categories', 'status']): string
    {
        $settings = new SettingsProvider();

        // the module renders the partial with arguments="{_all}", so the partial finds
        // the settings of the table under "columnLayout"
        return (string)$this->templateView()->renderPartial('NewsBunny/RecordTable', null, [
            'records' => [$this->record()],
            'columns' => $columns,
            'columnLayout' => $settings->getColumnLayout($columns),
            'sortingLinks' => [],
            'sortingIcons' => [],
            'controlPanels' => false,
            'pageId' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function renderCell(string $column, array $record): string
    {
        return (string)$this->templateView()->renderPartial('NewsBunny/RecordCell', null, [
            'column' => $column,
            'record' => $record,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function record(): array
    {
        return [
            'uid' => 1,
            'title' => 'Fragen zur SUB?',
            'teaser' => 'Teaser: Wo kann ich meine bestellten Bücher abholen?',
            'editUrl' => '/edit',
            'typeIconIdentifier' => 'content-text',
            'isHidden' => false,
            'isTopNews' => false,
            'isArchived' => false,
            'isTimeRestricted' => false,
            'tagsList' => '',
        ];
    }

    /**
     * The percent of the colgroup, in the order the cols stand in the markup.
     *
     * @return int[]
     */
    private function widthsOfColgroup(string $html): array
    {
        preg_match_all('#<col style="width: (\d+)%"\s*/?>#', $html, $matches);

        return array_map(intval(...), $matches[1]);
    }

    private function minWidthOf(string $html): int
    {
        self::assertSame(1, preg_match('#min-width: (\d+)rem#', $html, $matches));

        return (int)$matches[1];
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
        // the partials of the module resolve their short translation keys through the
        // request of the Extbase controller, which is where the extension name of the
        // labels comes from
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
