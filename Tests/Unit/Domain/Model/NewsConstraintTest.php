<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Unit\Domain\Model;

use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class NewsConstraintTest extends UnitTestCase
{
    public function testDefaultsAreEmptyAndUnfiltered(): void
    {
        $constraint = new NewsConstraint();

        self::assertNull($constraint->getSearchWord());
        self::assertSame('', $constraint->getTimeRestriction());
        self::assertSame('', $constraint->getManualDateStart());
        self::assertSame('', $constraint->getManualDateStop());
        self::assertSame('', $constraint->getTopNewsRestriction());
        self::assertSame('', $constraint->getArchived());
        self::assertSame('', $constraint->getHidden());
        self::assertSame([], $constraint->getCategories());
        self::assertSame('or', $constraint->getCategoryConjunction());
        self::assertFalse($constraint->getIncludeSubCategories());
        self::assertSame(NewsConstraint::LANGUAGE_ALL, $constraint->getLanguage());
        self::assertSame(0, $constraint->getStoragePage());
        self::assertSame([], $constraint->getStoragePageIds());
        self::assertFalse($constraint->getRecursive());
        self::assertSame(1, $constraint->getPage());
        self::assertSame(20, $constraint->getPerPage());
        self::assertSame('datetime', $constraint->getSortingField());
        self::assertSame('desc', $constraint->getSortingDirection());
        self::assertSame(0, $constraint->getOffset());
        self::assertFalse($constraint->hasFilters());
    }

    public function testSearchWordIsTrimmedAndEmptyBecomesNull(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setSearchWord('  typo3  ');
        self::assertSame('typo3', $constraint->getSearchWord());

        $constraint->setSearchWord('   ');
        self::assertNull($constraint->getSearchWord());

        $constraint->setSearchWord(null);
        self::assertNull($constraint->getSearchWord());
    }

    public function testSearchWordIsTruncatedToColumnLength(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSearchWord(str_repeat('a', 300));

        self::assertSame(255, mb_strlen((string)$constraint->getSearchWord()));
    }

    public static function restrictedStringProvider(): array
    {
        return [
            'allowed value is kept' => ['timeRestriction', 'day', 'day'],
            'empty value is kept' => ['timeRestriction', '', ''],
            'unknown value is reset' => ['timeRestriction', 'week', ''],
            'case is significant' => ['timeRestriction', 'Day', ''],
            'topNewsRestriction allows 1' => ['topNewsRestriction', '1', '1'],
            'topNewsRestriction rejects 3' => ['topNewsRestriction', '3', ''],
            'archived allows 2' => ['archived', '2', '2'],
            'archived rejects yes' => ['archived', 'yes', ''],
            'hidden allows 1' => ['hidden', '1', '1'],
            'hidden rejects null' => ['hidden', 'null', ''],
        ];
    }

    #[DataProvider('restrictedStringProvider')]
    public function testStringFiltersOnlyAcceptKnownValues(
        string $property,
        string $input,
        string $expected
    ): void {
        $constraint = new NewsConstraint();
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);
        $constraint->{$setter}($input);

        self::assertSame($expected, $constraint->{$getter}());
    }

    public function testDateFiltersAreNormalizedToIsoFormat(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setManualDateStart('2024-03-07');
        $constraint->setManualDateStop('07.03.2024');

        self::assertSame('2024-03-07', $constraint->getManualDateStart());
        self::assertSame('2024-03-07', $constraint->getManualDateStop());
    }

    public function testInvalidDateIsRejected(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setManualDateStart('not a date');
        $constraint->setManualDateStop('2024-13-45');

        self::assertSame('', $constraint->getManualDateStart());
        self::assertSame('', $constraint->getManualDateStop());
    }

    public function testCategoriesAreFilteredUniqedAndReindexed(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setCategories([3, '1', 2, 3, 0, -4, 'abc', '5']);

        self::assertSame([3, 1, 2, 5], $constraint->getCategories());
    }

    public function testCategoryConjunctionFallsBackToOr(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setCategoryConjunction('and');
        self::assertSame('and', $constraint->getCategoryConjunction());

        $constraint->setCategoryConjunction('notand');
        self::assertSame('notand', $constraint->getCategoryConjunction());

        $constraint->setCategoryConjunction('xor');
        self::assertSame('or', $constraint->getCategoryConjunction());
    }

    public function testNegativeStoragePageIsClampedToZero(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setStoragePage(-5);
        self::assertSame(0, $constraint->getStoragePage());

        $constraint->setStoragePage(7);
        self::assertSame(7, $constraint->getStoragePage());
    }

    public function testStoragePageIdsAreCastToUniqueIntegers(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setStoragePageIds([4, '2', 4, '0', -1]);

        self::assertSame([4, 2, 0, -1], $constraint->getStoragePageIds());
    }

    public function testPageIsAtLeastOne(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setPage(0);
        self::assertSame(1, $constraint->getPage());

        $constraint->setPage(-3);
        self::assertSame(1, $constraint->getPage());
    }

    public function testPerPageOnlyAcceptsConfiguredOptions(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setPerPage(50);
        self::assertSame(50, $constraint->getPerPage());

        $constraint->setPerPage(25);
        self::assertSame(20, $constraint->getPerPage());
    }

    public function testSortingFieldAndDirectionAreValidated(): void
    {
        $constraint = new NewsConstraint();

        $constraint->setSortingField('title');
        $constraint->setSortingDirection('asc');
        self::assertSame('title', $constraint->getSortingField());
        self::assertSame('asc', $constraint->getSortingDirection());

        $constraint->setSortingField('teaser');
        $constraint->setSortingDirection('random');
        self::assertSame('datetime', $constraint->getSortingField());
        self::assertSame('desc', $constraint->getSortingDirection());
    }

    public function testOffsetIsDerivedFromPageAndPerPage(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setPerPage(10);

        self::assertSame(0, $constraint->getOffset());

        $constraint->setPage(3);
        self::assertSame(20, $constraint->getOffset());

        $constraint->setPerPage(50);
        self::assertSame(100, $constraint->getOffset());
    }

    public function testHasFiltersIsFalseForAnUntouchedConstraint(): void
    {
        self::assertFalse((new NewsConstraint())->hasFilters());
    }

    public static function filterProvider(): array
    {
        return [
            'search word' => [static function (NewsConstraint $c): void {
                $c->setSearchWord('foo');
            }],
            'time restriction' => [static function (NewsConstraint $c): void {
                $c->setTimeRestriction('month');
            }],
            'manual date start' => [static function (NewsConstraint $c): void {
                $c->setManualDateStart('2024-01-01');
            }],
            'manual date stop' => [static function (NewsConstraint $c): void {
                $c->setManualDateStop('2024-12-31');
            }],
            'top news' => [static function (NewsConstraint $c): void {
                $c->setTopNewsRestriction('1');
            }],
            'archived' => [static function (NewsConstraint $c): void {
                $c->setArchived('2');
            }],
            'hidden' => [static function (NewsConstraint $c): void {
                $c->setHidden('1');
            }],
            'categories' => [static function (NewsConstraint $c): void {
                $c->setCategories([1]);
            }],
            'language' => [static function (NewsConstraint $c): void {
                $c->setLanguage(1);
            }],
            'storage page' => [static function (NewsConstraint $c): void {
                $c->setStoragePage(5);
            }],
            'recursive' => [static function (NewsConstraint $c): void {
                $c->setRecursive(true);
            }],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testHasFiltersDetectsEveryFilter(callable $apply): void
    {
        $constraint = new NewsConstraint();
        self::assertFalse($constraint->hasFilters());

        $apply($constraint);
        self::assertTrue($constraint->hasFilters());
    }

    public function testHasFiltersIgnoresDisplayOnlyState(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setPerPage(50);
        $constraint->setPage(3);
        $constraint->setSortingField('title');
        $constraint->setSortingDirection('asc');
        $constraint->setCategoryConjunction('and');
        $constraint->setIncludeSubCategories(true);

        self::assertFalse($constraint->hasFilters());
    }

    public function testToQueryParametersDropsEmptyValues(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSearchWord('foo');
        $constraint->setPerPage(50);

        $parameters = $constraint->toQueryParameters();

        // Only "null", "" and [] are dropped, so the neutral values below are part of the result
        self::assertArrayHasKey('searchWord', $parameters);
        self::assertSame('foo', $parameters['searchWord']);
        self::assertSame(50, $parameters['perPage']);

        foreach (['timeRestriction', 'manualDateStart', 'manualDateStop', 'topNewsRestriction', 'archived', 'hidden', 'categories', 'searchWord'] as $key) {
            if ($key === 'searchWord') {
                continue;
            }
            self::assertArrayNotHasKey($key, $parameters);
        }
    }

    public function testToQueryParametersKeepsNeutralValuesSoLinksAreComplete(): void
    {
        $parameters = (new NewsConstraint())->toQueryParameters();

        self::assertSame(
            [
                'categoryConjunction' => 'or',
                'includeSubCategories' => false,
                'language' => NewsConstraint::LANGUAGE_ALL,
                'storagePage' => 0,
                'page' => 1,
                'perPage' => 20,
                'sortingField' => 'datetime',
                'sortingDirection' => 'desc',
            ],
            $parameters
        );
    }

    public function testToQueryParametersSurvivesRoundTrip(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSearchWord('needle');
        $constraint->setCategories([3, 1]);
        $constraint->setLanguage(2);
        $constraint->setStoragePage(4);
        $constraint->setStoragePageIds([4, 5]);
        $constraint->setPerPage(100);
        $constraint->setPage(2);

        $restored = new NewsConstraint();
        foreach ($constraint->toQueryParameters() as $property => $value) {
            $setter = 'set' . ucfirst($property);
            self::assertTrue(method_exists($restored, $setter), $setter . ' exists');
            $restored->{$setter}($value);
        }

        self::assertSame('needle', $restored->getSearchWord());
        self::assertSame([3, 1], $restored->getCategories());
        self::assertSame(2, $restored->getLanguage());
        self::assertSame(4, $restored->getStoragePage());
        self::assertSame(100, $restored->getPerPage());
        self::assertSame(2, $restored->getPage());
    }

    public function testWithValuesDoesNotMutateTheOriginal(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSearchWord('keep');

        $copy = $constraint->withValues(['searchWord' => 'changed', 'page' => 3]);

        self::assertNotSame($constraint, $copy);
        self::assertSame('keep', $constraint->getSearchWord());
        self::assertSame(1, $constraint->getPage());
        self::assertSame('changed', $copy->getSearchWord());
        self::assertSame(3, $copy->getPage());
    }

    public function testWithValuesCastsRequestStringsToTheExpectedTypes(): void
    {
        $copy = (new NewsConstraint())->withValues([
            'page' => '4',
            'perPage' => '50',
            'language' => '1',
            'storagePage' => '9',
            'includeSubCategories' => '1',
            'recursive' => '0',
            'categories' => '4,5',
            'searchWord' => 'term',
        ]);

        self::assertSame(4, $copy->getPage());
        self::assertSame(50, $copy->getPerPage());
        self::assertSame(1, $copy->getLanguage());
        self::assertSame(9, $copy->getStoragePage());
        self::assertTrue($copy->getIncludeSubCategories());
        self::assertFalse($copy->getRecursive());
        self::assertSame([4, 5], $copy->getCategories());
        self::assertSame('term', $copy->getSearchWord());
    }

    public function testWithValuesAcceptsNullAsSearchWord(): void
    {
        $copy = (new NewsConstraint())->withValues(['searchWord' => null]);

        self::assertNull($copy->getSearchWord());
    }

    public function testWithValuesIgnoresUnknownProperties(): void
    {
        $constraint = new NewsConstraint();

        $copy = $constraint->withValues(['notAProperty' => 'x', 'title' => 'y']);

        // Neither key exists on the model, so the copy stays at the neutral defaults
        self::assertSame(
            [
                'categoryConjunction' => 'or',
                'includeSubCategories' => false,
                'language' => NewsConstraint::LANGUAGE_ALL,
                'storagePage' => 0,
                'page' => 1,
                'perPage' => 20,
                'sortingField' => 'datetime',
                'sortingDirection' => 'desc',
            ],
            $copy->toQueryParameters()
        );
        self::assertFalse($copy->hasFilters());
    }

    public function testTimeLimitsAreNullWithoutRestriction(): void
    {
        $constraint = new NewsConstraint();

        self::assertNull($constraint->getTimeLimitLow());
        self::assertNull($constraint->getTimeLimitHigh());
    }

    /**
     * The module folds the filter away by default and opens it when a filter of the
     * form is set, so a selected page must not open it: the page is not a field of
     * the form and is shown as selected in the page tree.
     */
    public function testASelectedPageIsNoFilterOfTheForm(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setStoragePage(42);
        $constraint->setStoragePageIds([42, 43]);

        // it is a filter for the button which resets the filters
        self::assertTrue($constraint->hasFilters());
        // but not for the folded away filter form
        self::assertFalse($constraint->hasFormFilters());
    }

    /**
     * @return \Generator<string, array{\Closure(NewsConstraint): void}>
     */
    public static function formFilterProvider(): \Generator
    {
        yield 'searchWord' => [static function(NewsConstraint $c): void { $c->setSearchWord('needle'); }];
        yield 'timeRestriction' => [static function(NewsConstraint $c): void { $c->setTimeRestriction('month'); }];
        yield 'manualDateStart' => [static function(NewsConstraint $c): void { $c->setManualDateStart('2026-01-01'); }];
        yield 'manualDateStop' => [static function(NewsConstraint $c): void { $c->setManualDateStop('2026-12-31'); }];
        yield 'topNewsRestriction' => [static function(NewsConstraint $c): void { $c->setTopNewsRestriction('1'); }];
        yield 'archived' => [static function(NewsConstraint $c): void { $c->setArchived('1'); }];
        yield 'hidden' => [static function(NewsConstraint $c): void { $c->setHidden('1'); }];
        yield 'categories' => [static function(NewsConstraint $c): void { $c->setCategories([7]); }];
        yield 'language' => [static function(NewsConstraint $c): void { $c->setLanguage(1); }];
        yield 'recursive' => [static function(NewsConstraint $c): void { $c->setRecursive(true); }];
    }

    /**
     * Every field of the form has to open the form, otherwise a filter which narrows
     * the record list down stays hidden behind a closed form.
     *
     * @param \Closure(NewsConstraint): void $applyFilter
     */
    #[DataProvider('formFilterProvider')]
    public function testEveryFieldOfTheFormOpensTheFoldedAwayForm(\Closure $applyFilter): void
    {
        $constraint = new NewsConstraint();
        self::assertFalse($constraint->hasFormFilters(), 'a fresh constraint has no filter');

        $applyFilter($constraint);

        self::assertTrue($constraint->hasFormFilters());
    }

    /**
     * The sorting, the number of records per page and the combination of the
     * categories change the list without being a filter, they must not open the form
     * on every visit.
     */
    public function testSortingAndPageSizeAreNoFiltersOfTheForm(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSortingField('title');
        $constraint->setSortingDirection('asc');
        $constraint->setPerPage(50);
        $constraint->setCategoryConjunction('and');

        self::assertFalse($constraint->hasFormFilters());
    }

    public function testManualDatesWinOverTheRelativeTimeRestriction(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction('day');
        $constraint->setManualDateStart('2024-05-06');
        $constraint->setManualDateStop('2024-05-07');

        self::assertSame(strtotime('2024-05-06 00:00:00'), $constraint->getTimeLimitLow());
        self::assertSame(strtotime('2024-05-07 23:59:59'), $constraint->getTimeLimitHigh());
    }

    public function testTimeRestrictionDaySpansTheWholeDay(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction('day');

        $today = new \DateTimeImmutable('today');

        self::assertSame($today->getTimestamp(), $constraint->getTimeLimitLow());
        self::assertSame($today->setTime(23, 59, 59)->getTimestamp(), $constraint->getTimeLimitHigh());
    }

    public function testTimeRestrictionMonthSpansTheWholeMonth(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction('month');

        $today = new \DateTimeImmutable('today');

        self::assertSame(
            $today->modify('first day of this month')->getTimestamp(),
            $constraint->getTimeLimitLow()
        );
        self::assertSame(
            $today->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp(),
            $constraint->getTimeLimitHigh()
        );
    }

    public function testTimeRestrictionYearSpansTheWholeYear(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction('year');

        $today = new \DateTimeImmutable('today');

        self::assertSame(
            $today->modify('first day of january')->getTimestamp(),
            $constraint->getTimeLimitLow()
        );
        self::assertSame(
            $today->modify('december 31')->setTime(23, 59, 59)->getTimestamp(),
            $constraint->getTimeLimitHigh()
        );
    }

    public function testInvalidManualDateFallsBackToTheRelativeRestriction(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction('day');
        // "manual" is not a parsable date, so it is normalized to an empty string
        $constraint->setManualDateStart('manual');

        $today = new \DateTimeImmutable('today');

        self::assertSame('', $constraint->getManualDateStart());
        self::assertSame($today->getTimestamp(), $constraint->getTimeLimitLow());
    }

    public function testConstraintSurvivesTheModuleDataSerialization(): void
    {
        $constraint = new NewsConstraint();
        $constraint->setSearchWord('serialized');
        $constraint->setCategories([2, 4]);
        $constraint->setPage(7);
        $constraint->setPerPage(100);
        $constraint->setStoragePage(3);
        $constraint->setStoragePageIds([3, 4]);
        $constraint->setSortingField('title');
        $constraint->setSortingDirection('asc');
        $constraint->setLanguage(1);

        $restored = unserialize(
            serialize($constraint),
            ['allowed_classes' => [NewsConstraint::class]]
        );

        self::assertInstanceOf(NewsConstraint::class, $restored);
        self::assertEquals($constraint, $restored);
    }
}
