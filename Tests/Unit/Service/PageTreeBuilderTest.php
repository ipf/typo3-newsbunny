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
                'language' => 0,
                'l10nParent' => 0,
            ];
        }

        return $pages;
    }

    /**
     * Adds the language variants of a page, which are records of their own with
     * their own uid and the pid of the page in the default language.
     */
    private function addVariants(array $pages, array $variants): array
    {
        foreach ($variants as $variantUid => [$l10nParent, $language, $title]) {
            $pages[$variantUid] = [
                'uid' => $variantUid,
                'pid' => $pages[$l10nParent]['pid'],
                'title' => $title,
                'path' => $title,
                'hidden' => false,
                'language' => $language,
                'l10nParent' => $l10nParent,
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
            'newsCount' => 0,
            'newsTotal' => 0,
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
        $tree = $this->subject->build($pages, 0, [], 2);
        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([], $tree[1]['children'][2]['children']);

        // one more level is available with maxDepth 3
        $tree = $this->subject->build($pages, 0, [], 3);
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

    /**
     * A page of the tree may be hidden while the record the editor works on is a
     * translation of it, so the current page is found through the language variants.
     */
    public function testBuildMarksThePageOfTheCurrentRecordEvenIfItIsATranslation(): void
    {
        $pages = $this->addVariants(
            $this->pages([1 => [0, 'Root', false], 2 => [1, 'News', false]]),
            [8 => [2, 1, 'Aktuelles']]
        );

        $tree = $this->subject->build($pages, 8);

        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertTrue($tree[1]['children'][2]['current']);
    }

    /**
     * The page tree must not show a page once per language: a translated page is a
     * record of its own with its own uid, and it used to appear next to the page it
     * is a translation of.
     */
    public function testBuildShowsATranslatedPageOnlyOnce(): void
    {
        $pages = $this->addVariants(
            $this->pages([
                1 => [0, 'Root', false],
                2 => [1, 'News', false],
                3 => [2, 'Company', false],
            ]),
            [
                8 => [2, 1, 'Aktuelles'],
                9 => [3, 1, 'Unternehmen'],
            ]
        );

        $tree = $this->subject->build($pages, 0);

        self::assertSame([1], array_keys($tree));
        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([3], array_keys($tree[1]['children'][2]['children']));
        // the node carries the title of the record in the default language
        self::assertSame('News', $tree[1]['children'][2]['title']);
    }

    /**
     * A child of a translated page points to the translated record, which is not a
     * node of the tree. The child has to be attached to the page it belongs to.
     */
    public function testBuildNestsChildrenOfATranslatedPageBelowTheOriginal(): void
    {
        $pages = $this->addVariants(
            $this->pages([
                1 => [0, 'Root', false],
                2 => [1, 'News', false],
            ]),
            [8 => [2, 1, 'Aktuelles']]
        );
        $pages[10] = [
            'uid' => 10,
            'pid' => 8,
            'title' => 'Unternehmen',
            'path' => 'Unternehmen',
            'hidden' => false,
            'language' => 0,
            'l10nParent' => 0,
        ];

        $tree = $this->subject->build($pages, 0);

        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([10], array_keys($tree[1]['children'][2]['children']));
    }

    /**
     * A translation whose page in the default language is not accessible must not
     * disappear, it is the only record of that page the editor may see.
     */
    public function testBuildKeepsATranslationWithoutAccessibleOriginal(): void
    {
        $pages = $this->pages([1 => [0, 'Root', false]]);
        $pages[8] = [
            'uid' => 8,
            'pid' => 1,
            'title' => 'Aktuelles',
            'path' => 'Aktuelles',
            'hidden' => false,
            'language' => 1,
            'l10nParent' => 2,
        ];

        $tree = $this->subject->build($pages, 0);

        self::assertSame([8], array_keys($tree[1]['children']));
        self::assertSame('Aktuelles', $tree[1]['children'][8]['title']);
    }

    public function testBuildMarksAPageAsHiddenIfAnyTranslationIsHidden(): void
    {
        $pages = $this->addVariants(
            $this->pages([1 => [0, 'Root', false], 2 => [1, 'News', false]]),
            [8 => [2, 1, 'Aktuelles']]
        );
        $pages[8]['hidden'] = true;

        $tree = $this->subject->build($pages, 0);

        self::assertTrue($tree[1]['children'][2]['hidden']);
    }

    /**
     * An editor mounted on a sub page does not see the parents of that page, and the
     * whole tree used to be empty for them.
     */
    public function testBuildUsesPagesWithAnInaccessibleParentAsRoot(): void
    {
        $pages = $this->pages([
            // page 1 is not accessible, so 2 and 3 are the roots of the module tree
            2 => [1, 'News', false],
            3 => [2, 'Company', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([2], array_keys($tree));
        self::assertSame([3], array_keys($tree[2]['children']));
    }

    /**
     * A page may only be a root of the module tree if its parent is not accessible.
     * A page that is its own parent would otherwise be its own child.
     */
    public function testBuildUsesAPageThatIsItsOwnParentAsRoot(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
            5 => [5, 'Self parent', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([1, 5], array_keys($tree));
        self::assertSame([2], array_keys($tree[1]['children']));
        self::assertSame([], $tree[5]['children']);
    }

    /**
     * Two pages pointing at each other are not reachable from a root of the tree, and
     * must not send the traversal into a loop.
     */
    public function testBuildDoesNotLoopOnCyclicPageTrees(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
            3 => [4, 'A', false],
            4 => [3, 'B', false],
        ]);

        $tree = $this->subject->build($pages, 0);

        self::assertSame([1], array_keys($tree));
        self::assertSame([2], array_keys($tree[1]['children']));
    }

    public function testBuildCountsTheNewsOfThePageAndOfItsSubpages(): void
    {
        $pages = $this->pages([
            1 => [0, 'Root', false],
            2 => [1, 'News', false],
            3 => [2, 'Company', false],
        ]);

        $tree = $this->subject->build($pages, 0, [1 => 5, 3 => 2]);

        self::assertSame(5, $tree[1]['newsCount']);
        self::assertSame(7, $tree[1]['newsTotal']);
        self::assertSame(0, $tree[1]['children'][2]['newsCount']);
        self::assertSame(2, $tree[1]['children'][2]['newsTotal']);
        self::assertSame(2, $tree[1]['children'][2]['children'][3]['newsCount']);
        self::assertSame(2, $tree[1]['children'][2]['children'][3]['newsTotal']);
    }

    /**
     * The records of a translation belong to the page, so they are counted on the
     * single node of that page instead of being split over two of them.
     */
    public function testBuildCountsTheNewsOfTheTranslationsOnTheSameNode(): void
    {
        $pages = $this->addVariants(
            $this->pages([
                1 => [0, 'Root', false],
                2 => [1, 'News', false],
                3 => [2, 'Company', false],
            ]),
            [8 => [2, 1, 'Aktuelles']]
        );

        $tree = $this->subject->build($pages, 0, [2 => 1, 8 => 4, 3 => 2]);

        self::assertSame(5, $tree[1]['children'][2]['newsCount']);
        self::assertSame(7, $tree[1]['children'][2]['newsTotal']);
    }

    /**
     * The total of a page may not depend on the level the tree is cut off at.
     */
    public function testBuildCountsTheSubpagesBelowTheMaximumDepth(): void
    {
        $pages = $this->pages([
            1 => [0, 'L1', false],
            2 => [1, 'L2', false],
            3 => [2, 'L3', false],
        ]);

        $tree = $this->subject->build($pages, 0, [2 => 1, 3 => 1], 2);

        self::assertSame([], $tree[1]['children'][2]['children']);
        self::assertSame(2, $tree[1]['newsTotal']);
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

    /**
     * The records stored on a translated page belong to the page the editor selected,
     * otherwise they would be missing from the list and the page would look empty.
     */
    public function testCollectIdsIncludesTheTranslationsOfEveryDescendant(): void
    {
        $pages = $this->addVariants(
            $this->pages([
                1 => [0, 'Root', false],
                2 => [1, 'News', false],
                3 => [2, 'Company', false],
            ]),
            [
                8 => [2, 1, 'Aktuelles'],
                9 => [3, 1, 'Unternehmen'],
            ]
        );

        self::assertSame([2, 3, 8, 9], $this->collectIdsSorted($pages, 2));
    }

    /**
     * The variants of the selected page itself are part of the selection, even if the
     * page is not the one the request is about.
     */
    public function testCollectIdsStartsAtTheVariantOfTheGivenPage(): void
    {
        $pages = $this->addVariants(
            $this->pages([1 => [0, 'Root', false], 2 => [1, 'News', false]]),
            [8 => [2, 1, 'Aktuelles']]
        );

        self::assertSame([2, 8], $this->collectIdsSorted($pages, 8));
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
