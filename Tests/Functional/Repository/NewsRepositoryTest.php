<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Repository;

use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class NewsRepositoryTest extends AbstractFunctionalTestCase
{
    private NewsRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->newsRepository();
        $this->importFixture('news_records');
    }

    private function constraint(): NewsConstraint
    {
        return new NewsConstraint();
    }

    public function testFindByConstraintReturnsAllNonDeletedRecordsSortedByDatetimeDesc(): void
    {
        $rows = $this->subject->findByConstraint($this->constraint(), null, 0, 100);

        // uid 11 is soft deleted and therefore not listed, uid 8 is the newest one
        self::assertSame([8, 10, 9, 7, 6, 5, 4, 3, 2, 1], $this->uidsOf($rows));
    }

    public function testFindByConstraintHonoursTheSortingDirection(): void
    {
        $constraint = $this->constraint();
        $constraint->setSortingDirection('asc');

        $rows = $this->subject->findByConstraint($constraint, null, 0, 100);

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 9, 10, 8], $this->uidsOf($rows));
    }

    public function testFindByConstraintHonoursTheSortingField(): void
    {
        $constraint = $this->constraint();
        $constraint->setSortingField('title');
        $constraint->setSortingDirection('asc');

        $rows = $this->subject->findByConstraint($constraint, null, 0, 100);

        $titles = array_map(static fn(array $row): string => (string)$row['title'], $rows);
        $sorted = $titles;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        self::assertSame($sorted, $titles);
        self::assertSame('Archived article', $titles[0]);
    }

    public function testFindByConstraintAppliesOffsetAndLimit(): void
    {
        $rows = $this->subject->findByConstraint($this->constraint(), null, 2, 3);

        self::assertSame([9, 7, 6], $this->uidsOf($rows));
    }

    public function testFindByConstraintReturnsAnEmptyListForAnOffsetBehindTheEnd(): void
    {
        $rows = $this->subject->findByConstraint($this->constraint(), null, 500, 20);

        self::assertSame([], $rows);
    }

    public function testFindByConstraintLimitsToTheGivenStoragePages(): void
    {
        $rows = $this->subject->findByConstraint($this->constraint(), [4], 0, 100);

        self::assertSame([5], $this->uidsOf($rows));
    }

    public function testFindByConstraintReturnsNothingForAnEmptyPageRestriction(): void
    {
        $rows = $this->subject->findByConstraint($this->constraint(), [], 0, 100);

        self::assertSame([], $rows);
    }

    public function testStoragePageRestrictionIncludesTheCollectedSubpages(): void
    {
        $constraint = $this->constraint();
        $constraint->setStoragePage(2);
        // page 2 "News" and its children 3 "Company" and 4 "Blog"
        $constraint->setStoragePageIds([2, 3, 4]);

        $rows = $this->subject->findByConstraint($constraint, null, 0, 100);

        // uid 10 lives on page 5, which is not part of the collected subpages
        self::assertSame([8, 9, 7, 6, 5, 4, 3, 2, 1], $this->uidsOf($rows));
    }

    public function testStoragePageRestrictionWithoutCollectedSubpagesMatchesNothing(): void
    {
        $constraint = $this->constraint();
        $constraint->setStoragePage(2);
        $constraint->setStoragePageIds([]);

        $rows = $this->subject->findByConstraint($constraint, null, 0, 100);

        self::assertSame([], $rows);
    }

    public function testSearchWordMatchesTitleTeaserAndBodytext(): void
    {
        $constraint = $this->constraint();
        $constraint->setSearchWord('Breaking');

        self::assertSame([1], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));

        $constraint->setSearchWord('Body three');
        self::assertSame([3], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));

        $constraint->setSearchWord('bold');
        self::assertSame([1], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testSearchWordCombinesAllWordsWithAnd(): void
    {
        $constraint = $this->constraint();
        $constraint->setSearchWord('teaser six');

        self::assertSame([6], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testSearchWordEscapesLikeWildcards(): void
    {
        $constraint = $this->constraint();
        $constraint->setSearchWord('%');

        // without escaping, "%" would match every record
        self::assertSame([], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));

        $constraint->setSearchWord('_');
        self::assertSame([], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testSearchWordOnlyMatchesWholeWords(): void
    {
        $constraint = $this->constraint();
        $constraint->setSearchWord('Needle');

        // uid 9 also has the "haystack" tag, but the title is matched as a whole word
        self::assertSame([9], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testHiddenFilterSeparatesVisibleAndHiddenRecords(): void
    {
        $visible = $this->constraint();
        $visible->setHidden('2');
        self::assertNotContains(3, $this->uidsOf($this->subject->findByConstraint($visible, null, 0, 100)));
        self::assertNotContains(6, $this->uidsOf($this->subject->findByConstraint($visible, null, 0, 100)));

        $hidden = $this->constraint();
        $hidden->setHidden('1');
        self::assertSame([6, 3], $this->uidsOf($this->subject->findByConstraint($hidden, null, 0, 100)));
    }

    public function testTopNewsFilter(): void
    {
        $top = $this->constraint();
        $top->setTopNewsRestriction('1');
        self::assertSame([6, 1], $this->uidsOf($this->subject->findByConstraint($top, null, 0, 100)));

        $notTop = $this->constraint();
        $notTop->setTopNewsRestriction('2');
        $uids = $this->uidsOf($this->subject->findByConstraint($notTop, null, 0, 100));
        self::assertNotContains(1, $uids);
        self::assertNotContains(6, $uids);
    }

    public function testArchivedFilterSeparatesPastFromUpcomingArchives(): void
    {
        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        $archived = $this->constraint();
        $archived->setArchived('2');
        self::assertSame([2], $this->uidsOf($this->subject->findByConstraint($archived, null, 0, 100)));

        $upcoming = $this->constraint();
        $upcoming->setArchived('1');
        $uids = $this->uidsOf($this->subject->findByConstraint($upcoming, null, 0, 100));
        // uid 2 is already archived, uid 4 gets archived in 2096
        self::assertNotContains(2, $uids);
        self::assertContains(4, $uids);
        self::assertSame([8, 10, 9, 7, 6, 5, 4, 3, 1], $uids);
        self::assertGreaterThan(0, $now);
    }

    public function testArchivedFilterTreatsAFutureArchiveAsNotArchived(): void
    {
        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        $archived = $this->constraint();
        $archived->setArchived('2');
        $uids = $this->uidsOf($this->subject->findByConstraint($archived, null, 0, 100));

        // uid 4 has an archive date far in the future
        self::assertNotContains(4, $uids);
    }

    public function testLanguageFilter(): void
    {
        $constraint = $this->constraint();
        $constraint->setLanguage(1);

        self::assertSame([5], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testLanguageAllDoesNotFilter(): void
    {
        $constraint = $this->constraint();
        $constraint->setLanguage(NewsConstraint::LANGUAGE_ALL);

        self::assertCount(10, $this->subject->findByConstraint($constraint, null, 0, 100));
    }

    public function testTimeRestrictionLimitsByDatetime(): void
    {
        $constraint = $this->constraint();
        $constraint->setManualDateStart('2024-01-01');
        $constraint->setManualDateStop('2024-12-31');

        // only uid 8 (1730000000) is in 2024
        self::assertSame([8], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testCategoryFilterWithOr(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1, 2]);

        // category 1: news 1, 2    category 2: news 3, 6
        self::assertSame([6, 3, 2, 1], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testCategoryFilterWithAndKeepsOnlyRecordsOfEveryCategory(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1, 3]);
        $constraint->setCategoryConjunction('and');

        // category 1: 1, 2   category 3: 1  -> only news 1 is in both
        self::assertSame([1], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testCategoryFilterWithNotOrExcludesTheMatchingRecords(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1, 2]);
        $constraint->setCategoryConjunction('notor');

        $uids = $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100));

        self::assertNotContains(1, $uids);
        self::assertNotContains(2, $uids);
        self::assertNotContains(3, $uids);
        self::assertContains(9, $uids);
    }

    public function testCategoryFilterWithNotAnd(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1, 3]);
        $constraint->setCategoryConjunction('notand');

        $uids = $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100));

        // only news 1 is in both categories, so everything else remains
        self::assertNotContains(1, $uids);
        self::assertContains(2, $uids);
        self::assertContains(3, $uids);
    }

    public function testCategoryFilterMatchesNothingWhenNoRecordIsRelated(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([5]);

        self::assertSame([], $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100)));
    }

    public function testCategoryFilterExpandsSubCategoriesOnDemand(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1]);
        $constraint->setIncludeSubCategories(true);

        // category 1 plus its children 2 (Germany) and 4 (Berlin, child of 2)
        $uids = $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100));

        // category 1 -> news 1, 2   category 2 -> news 3, 6   category 4 -> news 4
        self::assertSame([6, 4, 3, 2, 1], $uids);
    }

    public function testCategoryFilterDoesNotExpandSubCategoriesByDefault(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1]);

        $uids = $this->uidsOf($this->subject->findByConstraint($constraint, null, 0, 100));

        self::assertSame([2, 1], $uids);
    }

    public function testCountByConstraintMatchesTheNumberOfListedRecords(): void
    {
        $constraint = $this->constraint();
        $constraint->setCategories([1, 2]);
        $constraint->setSortingField('title');

        $rows = $this->subject->findByConstraint($constraint, null, 0, 100);

        self::assertSame(4, $this->subject->countByConstraint($constraint, null));
        self::assertCount(4, $rows);
    }

    public function testCountByConstraintRespectsThePageRestriction(): void
    {
        self::assertSame(1, $this->subject->countByConstraint($this->constraint(), [4]));
        self::assertSame(0, $this->subject->countByConstraint($this->constraint(), []));
    }

    public function testCountAllIgnoresFiltersButRespectsThePageRestriction(): void
    {
        self::assertSame(10, $this->subject->countAll(null));
        self::assertSame(1, $this->subject->countAll([4]));
        self::assertSame(0, $this->subject->countAll([]));
    }

    public function testCountByTableGroupsByStoragePage(): void
    {
        self::assertSame(
            [2 => 6, 3 => 2, 4 => 1, 5 => 1],
            $this->subject->countNewsByPage(null)
        );
    }

    public function testCountByTableRespectsThePageRestriction(): void
    {
        self::assertSame([4 => 1], $this->subject->countNewsByPage([4]));
        self::assertSame([], $this->subject->countNewsByPage([]));
    }

    public function testCountCategoriesAndTagsByPage(): void
    {
        // uid 7 is deleted; uid 6 is translated but the count is not language aware
        self::assertSame([2 => 6], $this->subject->countCategoriesByPage(null));
        self::assertSame([2 => 2], $this->subject->countTagsByPage(null));
    }

    public function testFindCategoriesReturnsDefaultLanguageCategoriesSortedByTitle(): void
    {
        self::assertSame(
            [
                4 => 'Berlin',
                2 => 'Germany',
                1 => 'Politics',
                3 => 'Sports',
                5 => 'Unused category',
            ],
            $this->subject->findCategories()
        );
    }

    public function testFindCategoriesLimitsToTheGivenRootsAndTheirChildren(): void
    {
        $categories = $this->subject->findCategories([1]);

        self::assertSame([4 => 'Berlin', 2 => 'Germany', 1 => 'Politics'], $categories);
    }

    public function testFindCategoriesReturnsNothingForAnEmptyRootList(): void
    {
        self::assertSame([], $this->subject->findCategories([]));
    }

    public function testFindCategoriesForRecordsGroupsByNewsUid(): void
    {
        $categories = $this->subject->findCategoriesForRecords([1, 2, 3, 4, 6]);
        // the query is ordered by category title across all records, so the grouping
        // order is not significant - the categories of a record are
        ksort($categories);

        self::assertSame(
            [
                1 => [1 => 'Politics', 3 => 'Sports'],
                2 => [1 => 'Politics'],
                3 => [2 => 'Germany'],
                4 => [4 => 'Berlin', 3 => 'Sports'],
                6 => [2 => 'Germany'],
            ],
            $categories
        );
    }

    public function testFindCategoriesForRecordsIgnoresTheFieldname(): void
    {
        // the mm row with fieldname "keywords" also links uid_local 1, but the query
        // does not limit the fieldname, so it shows up as an additional category
        $categories = $this->subject->findCategoriesForRecords([1]);

        self::assertSame([1 => [1 => 'Politics', 3 => 'Sports']], $categories);
    }

    public function testFindCategoriesForRecordsReturnsNothingForAnEmptyList(): void
    {
        self::assertSame([], $this->subject->findCategoriesForRecords([]));
    }

    public function testFindTagsForRecordsGroupsByNewsUid(): void
    {
        $tags = $this->subject->findTagsForRecords([1, 2, 9]);
        ksort($tags);

        self::assertSame(
            [
                1 => [1 => 'Alpha', 2 => 'Beta'],
                2 => [1 => 'Alpha'],
            ],
            $tags
        );
    }

    public function testFindTagsForRecordsSkipsDeletedTags(): void
    {
        // uid 9 is only related to uid 3, which is a deleted tag
        self::assertArrayNotHasKey(9, $this->subject->findTagsForRecords([9]));
    }

    public function testFindCategoriesForRecordsSkipsDeletedCategories(): void
    {
        // uid 6 is related to category 2, the deleted category 7 is not linked to any news
        $categories = $this->subject->findCategoriesForRecords([6]);

        self::assertSame([6 => [2 => 'Germany']], $categories);
    }

    public function testFindTagsForRecordsReturnsNothingForAnEmptyList(): void
    {
        self::assertSame([], $this->subject->findTagsForRecords([]));
    }

    public function testFindTagsSkipsDeletedTags(): void
    {
        self::assertSame([1 => 'Alpha', 2 => 'Beta'], $this->subject->findTags());
    }
}
