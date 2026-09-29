<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Service;

use Ipf\NewsBunny\Service\SettingsProvider;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Fixture "settings_pages":
 *
 *   1 Root                 no TSconfig
 *   2 Plain page           no TSconfig
 *   3 Configured page      overrides columns, pages, preselect, filters, defaultPid
 *   4 Resets defaults      sets values to an empty string
 *   5 Inherits from parent no own TSconfig
 */
final class SettingsProviderTest extends AbstractFunctionalTestCase
{
    private SettingsProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new SettingsProvider();
        $this->importFixture('settings_pages');
    }

    public function testDefaultsComeFromTheConfigurationFile(): void
    {
        self::assertSame('teaser,datetime,categories,status', $this->subject->get('columns'));
        self::assertSame('0', $this->subject->get('defaultPage'));
        self::assertSame('1', $this->subject->get('localizationView'));
    }

    public function testThePageTreeIsLimitedToThePagesWithNewsByDefault(): void
    {
        self::assertTrue($this->subject->getBool('hideEmptyPages'));
    }

    public function testTheFilterStartsFoldedAway(): void
    {
        self::assertFalse($this->subject->getBool('alwaysShowFilter'));
    }

    public function testTheFilterCanStartOpened(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertTrue($this->subject->getBool('alwaysShowFilter'));
    }

    public function testThePageTreeCanShowThePagesWithoutNews(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertFalse($this->subject->getBool('hideEmptyPages'));
    }

    public function testGetReturnsTheDefaultForUnknownKeys(): void
    {
        self::assertNull($this->subject->get('doesNotExist'));
        self::assertSame('fallback', $this->subject->get('doesNotExist', 'fallback'));
    }

    public function testGetIntCastsNumericValues(): void
    {
        self::assertSame(0, $this->subject->getInt('defaultPage'));
        self::assertSame(42, $this->subject->getInt('doesNotExist', 42));
    }

    public function testGetIntFallsBackForNonNumericValues(): void
    {
        $this->subject->applyPageTsConfig(4);

        // "defaultPage" was reset to an empty string
        self::assertSame(7, $this->subject->getInt('defaultPage', 7));
    }

    public function testGetBoolUnderstandsTheTypoScriptSpellings(): void
    {
        self::assertTrue($this->subject->getBool('localizationView'));
        self::assertFalse($this->subject->getBool('doesNotExist'));
        self::assertTrue($this->subject->getBool('doesNotExist', true));
    }

    public function testGetIntListParsesCommaSeparatedIds(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertSame([3, 4], $this->subject->getIntList('allowedCategoryRootIds'));
    }

    public function testGetIntListIsEmptyByDefault(): void
    {
        self::assertSame([], $this->subject->getIntList('allowedCategoryRootIds'));
    }

    public function testGetDefaultPidReadsTheTableSpecificValue(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertSame(2, $this->subject->getDefaultPid('tx_news_domain_model_news'));
        self::assertSame(3, $this->subject->getDefaultPid('sys_category'));
        self::assertSame(0, $this->subject->getDefaultPid('tx_news_domain_model_tag'));
        self::assertSame(0, $this->subject->getDefaultPid('unknown_table'));
    }

    public function testGetDefaultPidIsZeroWithoutConfiguration(): void
    {
        self::assertSame(0, $this->subject->getDefaultPid('tx_news_domain_model_news'));
    }

    public function testGetPreselectReturnsTheConfiguredDefaults(): void
    {
        $preselect = $this->subject->getPreselect();

        self::assertSame('datetime', $preselect['sortingField']);
        self::assertSame('desc', $preselect['sortingDirection']);
        self::assertSame('20', $preselect['perPage']);
    }

    public function testGetPreselectMergesTheTsconfigOverTheDefaults(): void
    {
        $this->subject->applyPageTsConfig(3);
        $preselect = $this->subject->getPreselect();

        self::assertSame('50', $preselect['perPage']);
        self::assertSame('title', $preselect['sortingField']);
        self::assertSame('asc', $preselect['sortingDirection']);
        self::assertSame('1', $preselect['recursive']);
        self::assertSame('1', $preselect['language']);
        // not overridden, so the default is still there
        self::assertSame('or', $preselect['categoryConjunction']);
    }

    public function testApplyPageTsConfigOverridesScalarSettings(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertSame('3', $this->subject->get('allowedPage'));
        self::assertSame(3, $this->subject->getInt('defaultPage'));
        self::assertTrue($this->subject->getBool('hidePageTree'));
        // the default of the filter is the folded form, so this is an override
        self::assertTrue($this->subject->getBool('alwaysShowFilter'));
        self::assertFalse($this->subject->getBool('hideEmptyPages'));
        self::assertFalse($this->subject->getBool('localizationView'));
        self::assertFalse($this->subject->getBool('controlPanels'));
    }

    public function testApplyPageTsConfigLeavesUnconfiguredSettingsAtTheirDefault(): void
    {
        $this->subject->applyPageTsConfig(3);

        // the raw value is passed through, only getColumns() trims the single values
        self::assertSame('teaser, author', $this->subject->get('columns'));
        // not part of the TSconfig of page 3
        self::assertSame('0', $this->subject->get('redirectToPageOnStart'));
    }

    public function testAnEmptyTsconfigValueResetsAStringDefault(): void
    {
        $this->subject->applyPageTsConfig(4);

        self::assertSame('', $this->subject->get('columns'));
        self::assertSame('', $this->subject->get('hidePageTree'));
        self::assertSame('', $this->subject->get('defaultPage'));
    }

    public function testApplyPageTsConfigWithoutAnyConfigurationKeepsTheDefaults(): void
    {
        $this->subject->applyPageTsConfig(2);

        self::assertSame('teaser,datetime,categories,status', $this->subject->get('columns'));
        // the key exists in the defaults with the value "0", so the fallback is not used
        self::assertFalse($this->subject->getBool('hidePageTree', true));
    }

    public function testApplyPageTsConfigForTheRootPageKeepsTheDefaults(): void
    {
        $this->subject->applyPageTsConfig(1);

        self::assertSame('teaser,datetime,categories,status', $this->subject->get('columns'));
    }

    public function testApplyPageTsConfigIsCalledRepeatedlyWithoutAccumulating(): void
    {
        $this->subject->applyPageTsConfig(3);
        self::assertSame('teaser, author', $this->subject->get('columns'));

        $this->subject->applyPageTsConfig(2);
        self::assertSame('teaser,datetime,categories,status', $this->subject->get('columns'));
    }

    public function testFiltersAreEnabledByDefault(): void
    {
        foreach (['searchWord', 'timeRestriction', 'topNewsRestriction', 'archived', 'hidden', 'language', 'categories', 'categoryConjunction', 'includeSubCategories', 'recursive'] as $filter) {
            self::assertTrue($this->subject->isFilterEnabled($filter), $filter . ' is enabled');
        }
    }

    public function testUnknownFiltersAreEnabled(): void
    {
        self::assertTrue($this->subject->isFilterEnabled('somethingElse'));
    }

    public function testDisabledFiltersAreReadFromTheTsconfig(): void
    {
        $this->subject->applyPageTsConfig(3);

        self::assertFalse($this->subject->isFilterEnabled('searchWord'));
        self::assertFalse($this->subject->isFilterEnabled('archived'));
        self::assertTrue($this->subject->isFilterEnabled('hidden'));
    }

    public function testTheCategoryConjunctionFollowsTheCategoryFilter(): void
    {
        $this->subject->applyPageTsConfig(3);

        // "categories" is still enabled, so the conjunction is enabled as well
        self::assertTrue($this->subject->isFilterEnabled('categoryConjunction'));
        self::assertTrue($this->subject->isFilterEnabled('includeSubCategories'));
    }

    public function testTitleIsPrependedWhenItIsNotConfigured(): void
    {
        $this->subject->applyPageTsConfig(3);

        // page 3 configures "teaser, author" without the title
        self::assertSame(['title', 'teaser', 'author'], $this->subject->getColumns());
    }

    public function testTitleKeepsItsConfiguredPosition(): void
    {
        $this->subject->applyPageTsConfig(2);

        // the defaults already contain the title, so it is not prepended twice
        self::assertSame(['title', 'teaser', 'datetime', 'categories', 'status'], $this->subject->getColumns());
    }

    public function testColumnsFallBackToTitleOnlyForAnEmptyConfiguration(): void
    {
        $this->subject->applyPageTsConfig(4);

        self::assertSame(['title'], $this->subject->getColumns());
    }

    public function testUnknownColumnsAreDropped(): void
    {
        $this->subject->applyPageTsConfig(2);

        self::assertSame(['title', 'teaser', 'datetime', 'categories', 'status'], $this->subject->getColumns());
    }

    public function testEveryColumnOfTheListGetsAWidth(): void
    {
        $layout = $this->subject->getColumnLayout(['title', 'teaser', 'datetime', 'status']);

        self::assertSame(
            ['title', 'teaser', 'datetime', 'status', 'control'],
            array_keys($layout['widths']),
            'the configured columns and the column of the actions get a width'
        );
    }

    /**
     * A table with a fixed layout hands the space that is left over to the last column, so
     * a sum below 100 would blow up the column of the buttons.
     */
    #[DataProvider('columnSetProvider')]
    public function testTheWidthsAddUpToTheWholeTable(array $columns): void
    {
        $widths = $this->subject->getColumnLayout($columns)['widths'];

        $percent = array_map(static fn(string $width): int => (int)rtrim($width, '%'), $widths);

        self::assertSame(100, array_sum($percent), 'the widths of ' . implode(', ', $columns));
    }

    #[DataProvider('columnSetProvider')]
    public function testTheButtonsKeepAShareOfTheirOwn(array $columns): void
    {
        $widths = $this->subject->getColumnLayout($columns)['widths'];

        $percent = (int)rtrim($widths['control'], '%');

        self::assertGreaterThanOrEqual(12, $percent, 'the buttons of a record need room');
        self::assertLessThanOrEqual(24, $percent, 'the buttons must not take the table');
    }

    public function testTheShareOfTheButtonsDoesNotDependOnTheCountOfColumns(): void
    {
        $widths = [
            $this->subject->getColumnLayout(['title', 'datetime'])['widths'],
            $this->subject->getColumnLayout(['title', 'teaser', 'datetime', 'categories', 'status'])['widths'],
            $this->subject->getColumnLayout($this->subject::COLUMNS)['widths'],
        ];

        foreach ($widths as $layout) {
            $percent = (int)rtrim($layout['control'], '%');
            self::assertGreaterThanOrEqual(12, $percent);
            self::assertLessThanOrEqual(24, $percent);
        }
    }

    public function testATitleOnlyListLeavesTheSpaceToTheTitle(): void
    {
        $widths = $this->subject->getColumnLayout(['title'])['widths'];

        self::assertSame('76%', $widths['title']);
        self::assertSame('24%', $widths['control']);
    }

    public function testTheTeaserGetsMoreRoomThanTheTitle(): void
    {
        $widths = $this->subject->getColumnLayout(['title', 'teaser'])['widths'];

        self::assertGreaterThan((int)rtrim($widths['title'], '%'), (int)rtrim($widths['teaser'], '%'));
    }

    public function testTheColumnsWithAShortContentKeepOneLine(): void
    {
        $nowrap = $this->subject->getColumnLayout(
            ['title', 'teaser', 'datetime', 'archive', 'crdate', 'tstamp', 'uid', 'language', 'status']
        )['nowrap'];

        // a date or a uid that wraps is the reason the widths exist at all
        self::assertSame(
            ['datetime', 'archive', 'crdate', 'tstamp', 'uid', 'language'],
            array_keys($nowrap)
        );
    }

    public function testTheColumnsWithLongContentMayWrap(): void
    {
        $nowrap = $this->subject->getColumnLayout(['title', 'teaser', 'page', 'categories', 'status'])['nowrap'];

        self::assertSame([], $nowrap);
    }

    /**
     * @return array<string, array{0: string[]}>
     */
    public static function columnSetProvider(): array
    {
        return [
            'title only' => [['title']],
            'default' => [['title', 'teaser', 'datetime', 'categories', 'status']],
            'every column' => [[
                'title', 'teaser', 'datetime', 'archive', 'categories', 'tags', 'author',
                'path_segment', 'status', 'language', 'page', 'uid', 'crdate', 'tstamp',
            ]],
        ];
    }
}
