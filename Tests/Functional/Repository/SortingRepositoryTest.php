<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Repository;

use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Repository\SortingRepository;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;

/**
 * Fixture "sorting_records", page 10 sorted ascending by "sorting":
 *
 *   10 (0), 5 (5), 7 (7), 1 (10), 2 (20), [8 (25) deleted], 3 (30), 4 (40), 6 (50)
 *
 * uid 9 lives on another page and must never be returned.
 */
final class SortingRepositoryTest extends AbstractFunctionalTestCase
{
    private const PAGE = 10;

    private SortingRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->sortingRepository();
        $this->importFixture('sorting_records');
    }

    private function neighbours(int $uid): array
    {
        return $this->subject->findNeighbours(NewsRepository::TABLE, self::PAGE, $uid, 'sorting');
    }

    public function testFindsTheTwoRecordsBeforeAndAfter(): void
    {
        $neighbours = $this->neighbours(2);

        // the direct neighbour comes first, the one behind it second:
        // before uid 2 (20) are uid 1 (10) and uid 7 (7)
        self::assertSame([1, 7], $neighbours['before']);
        // after it are uid 3 (30) and uid 4 (40)
        self::assertSame([3, 4], $neighbours['after']);
    }

    public function testNeighboursOfTheTopRecordAreEmptyOnOneSide(): void
    {
        $neighbours = $this->neighbours(10);

        self::assertSame([], $neighbours['before']);
        self::assertSame([5, 7], $neighbours['after']);
    }

    public function testNeighboursOfTheBottomRecordAreEmptyOnOneSide(): void
    {
        $neighbours = $this->neighbours(6);

        self::assertSame([4, 3], $neighbours['before']);
        self::assertSame([], $neighbours['after']);
    }

    public function testTheOnlyRecordOfAPageHasNoNeighbours(): void
    {
        $neighbours = $this->subject->findNeighbours(NewsRepository::TABLE, 20, 9, 'sorting');

        self::assertSame([], $neighbours['before']);
        self::assertSame([], $neighbours['after']);
    }

    public function testNeighboursAreLimitedToAtMostTwoRecords(): void
    {
        $neighbours = $this->neighbours(1);

        // before uid 1 (10) are uid 7 (7) and uid 5 (5)
        self::assertSame([7, 5], $neighbours['before']);
        self::assertCount(2, $neighbours['after']);
    }

    public function testRecordsOfOtherPagesAreNotConsidered(): void
    {
        // uid 9 is on page 20 and has a sorting value between 5 and 10
        $neighbours = $this->neighbours(10);

        self::assertNotContains(9, $neighbours['after']);
        self::assertNotContains(9, $neighbours['before']);
    }

    public function testDeletedRecordsAreNotConsidered(): void
    {
        // uid 8 (sorting 25) is deleted and would sit between 2 and 3
        $neighbours = $this->neighbours(2);

        self::assertNotContains(8, $neighbours['after']);
        self::assertSame([3, 4], $neighbours['after']);
    }

    public function testUnknownRecordsHaveNoNeighbours(): void
    {
        $neighbours = $this->neighbours(9999);

        self::assertSame([], $neighbours['before']);
        self::assertSame([], $neighbours['after']);
    }

    public function testTheRecordItselfIsNeverReturned(): void
    {
        $neighbours = $this->neighbours(3);

        self::assertNotContains(3, $neighbours['before']);
        self::assertNotContains(3, $neighbours['after']);
    }

    /**
     * The neighbour queries use strict comparisons, so records with exactly the
     * same sorting value are invisible to each other. This documents the behaviour.
     */
    public function testRecordsWithEqualSortingAreNotSeenAsNeighbours(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable(NewsRepository::TABLE);
        $connection->insert(NewsRepository::TABLE, [
            'pid' => self::PAGE,
            'title' => 'Same sorting as second',
            'sorting' => 20,
            'datetime' => 1700001100,
        ]);
        $twinUid = (int)$connection->lastInsertId();

        $neighbours = $this->neighbours(2);

        self::assertNotContains($twinUid, array_merge($neighbours['before'], $neighbours['after']));
    }
}
