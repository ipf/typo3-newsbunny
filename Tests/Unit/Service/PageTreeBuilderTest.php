<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Unit\Service;

use Ipf\NewsBunny\Service\PageTreeBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PageTreeBuilderTest extends UnitTestCase
{
    private PageTreeBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new PageTreeBuilder();
    }

    /**
     * uid => [pid, title, hidden]
     */
    private function pages(array $definition): array
    {
        $pages = [];
        foreach ($definition as $uid => [$pid, $title, $hidden]) {
            $pages[$uid] = [
                'uid' => $uid,
                'pid' => $pid,
                'title' => $title,
                'path' => $title,
                'hidden' => $hidden,
            ];
        }

        return $pages;
    }

    public function testBuildReturnsEmptyTreeWithoutPages(): void
    {
        self::assertSame([], $this->subject->build([], 0));
    }

    public function testBuildNestsPagesBelowTheirParent(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
            3 => [2, 'Company', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([1], array_keys($tree));
        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([3], array_keys($tree[1]['children'][2]['children']));
        self::assertSame([], $tree[1]['children'][2]['children'][3]['children']);
    }

    public function testBuildExposesTheNodeMetadata(): void
    {
        $pages = $this->pages([5 => [0, 'Hidden news', true]]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([
            'uid' => 5,
            'title' => 'Hidden news',
            'path' => 'Hidden news',
            'hidden' => true,
            'current' => false,
            'children' => [],
        ], $tree[5]);
    }

    public function testBuildMarksTheCurrentPage(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
        ]);

        $tree = $this->subject->build($pages, 2);

        self::assertFalse($tree[1]['current']);
        self::assertTrue($tree[1]['children'][2]['current']);
    }

    /**
     * The code comment claims that "pages whose parent is not accessible are treated
     * as root pages", but the traversal always starts at pid 0, so such a page is
     * silently dropped from the tree. This test documents the current behaviour.
     */
    public function testBuildDropsPagesWhoseParentIsNotAccessible(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            // page 3 is only reachable through page 2, which is not accessible
            3 => [2, 'Orphan', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([1], array_keys($tree));
        self::assertArrayNotHasKey(3, $tree);
    }

    public function testBuildSortsSiblingsNaturallyByTitleCaseInsensitive(): void
    {
        $pages = $this->pages([
            1 => [0, 'News 10', false],
            2 => [0, 'news 2', false],
            3 => [0, 'News 1', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([3, 2, 1], array_keys($tree));
    }

    public function testBuildStopsAtTheMaximumDepth(): void
    {
        $pages = $this->pages([
            1 => [0, 'L1', false],
            2 => [1, 'L2', false],
            3 => [2, 'L3', false],
            4 => [3, 'L4', false],
        ]);

        // maxDepth 2 renders two levels, L3 and L4 are cut off
        $tree = $this->subject->build($pages, 0, 2);
        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([], $tree[1]['children'][2]['children']);

        // one more level is available with maxDepth 3
        $tree = $this->subject->build($pages, 0, 3);
        self::assertSame([3], array_keys($tree[1]['children'][2]['children']));
        self::assertSame([], $tree[1]['children'][2]['children'][3]['children']);
    }

    public function testBuildIgnoresAnUnknownCurrentPage(): void
    {
        $pages = $this->pages([1 => [0, 'Root', false]]);

        $tree = $this->subject->build($pages, 99);

        self::assertSame([1], array_keys($tree));
        self::assertFalse($tree[1]['current']);
    }

    public function testCollectIdsReturnsNothingForTheRootLevel(): void
    {
        $pages = $this->pages([1 => [0, 'Root', false]]);

        self::assertSame([], $this->subject->collectIds($pages, 0));
        self::assertSame([], $this->subject->collectIds($pages, -1));
    }

    public function testCollectIdsIncludesAllDescendants(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
            3 => [1, 'Blog', false],
            4 => [2, 'Company', false],
        ]);

        self::assertSame([1, 2, 3, 4], $this->collectIdsSorted($pages, 1));
        self::assertSame([2, 4], $this->collectIdsSorted($pages, 2));
        self::assertSame([4], $this->collectIdsSorted($pages, 4));
    }

    public function testCollectIdsReturnsThePageItselfWhenItIsNotAccessible(): void
    {
        $pages = $this->pages([
            2 => [1, 'News', false],
        ]);

        // page 1 is not accessible, its child is requested instead
        self::assertSame([1], $this->subject->collectIds($pages, 1));
    }

    public function testCollectIdsDoesNotLoopOnCyclicPageTrees(): void
    {
        $pages = $this->pages([
            1 => [2, 'A', false],
            2 => [1, 'B', false],
        ]);

        $ids = $this->subject->collectIds($pages, 1);

        self::assertSame([1, 2], $this->collectIdsSorted($pages, 1));
        self::assertSame([1, 2], $ids);
    }

    public function testCollectIdsDropsTheTreeOfUnrelatedBranches(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [0, 'Other root', false],
            3 => [2, 'Other child', false],
        ]);

        self::assertSame([1], $this->subject->collectIds($pages, 1));
    }

    public static function emptyPageIdProvider(): array
    {
        return [[0], [-1], [-100]];
    }

    #[DataProvider('emptyPageIdProvider')]
    public function testCollectIdsRejectsNonPositiveIds(int $pageId): void
    {
        $pages = $this->pages([1 => [0, 'Root', false]]);

        self::assertSame([], $this->subject->collectIds($pages, $pageId));
    }

    /**
     * The traversal order depends on the order of the accessible pages, so the
     * assertions are made independent of it.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return int[]
     */
    private function collectIdsSorted(array $pages, int $pageId): array
    {
        $ids = $this->subject->collectIds($pages, $pageId);
        sort($ids);

        return $ids;
    }
}
