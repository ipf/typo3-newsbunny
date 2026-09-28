<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Repository;

use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Service\PageTreeBuilder;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Fixture "page_translations":
 *
 *   be_users 1 admin  no mount
 *   be_users 2 editor mounted on page 2 only
 *
 *   pages 1  Root              everybody show
 *   pages 2  News              everybody show, translation 8 "Aktuelles"
 *   pages 3  Company           everybody show, translation 9 "Unternehmen"
 *   pages 8  Aktuelles         translation of page 2
 *   pages 9  Unternehmen       translation of page 3
 *   pages 10 Nur auf Deutsch   translation of page 99, which does not exist
 *
 *   news 1 on page 2, news 2 on page 8 (both belong to the page "News")
 *   news 3 on page 3, news 4 on page 9 (both belong to the page "Company")
 *
 * A translated page is a record of its own, with its own uid and the pid of the page
 * in the default language. The page tree used to show every translation as an own
 * entry, next to the page it belongs to.
 */
final class PageTranslationTreeTest extends AbstractFunctionalTestCase
{
    private PageTreeBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixture('page_translations');
        $this->builder = new PageTreeBuilder();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pagesFor(int $userUid): array
    {
        return $this->pageRepository()->findAccessible(
            $this->setUpBackendUserAuthentication($userUid),
            NewsRepository::TABLE
        );
    }

    /**
     * @return array<int, int>
     */
    private function newsCounts(): array
    {
        return $this->newsRepository()->countNewsByPage(null);
    }

    public function testEveryTranslationIsAnAccessiblePageOfItsOwn(): void
    {
        $pages = $this->pagesFor(1);

        // the translations stay part of the result, the records stored on them are
        // found through the uids of the pages
        self::assertSame([1, 2, 3, 8, 9, 10], array_keys($pages));
    }

    public function testTheRepositoryExposesTheLanguageOfEveryPage(): void
    {
        $pages = $this->pagesFor(1);

        self::assertSame(0, $pages[2]['language']);
        self::assertSame(0, $pages[2]['l10nParent']);
        self::assertSame(1, $pages[8]['language']);
        self::assertSame(2, $pages[8]['l10nParent']);
    }

    public function testTheTreeShowsEveryPageOnce(): void
    {
        $tree = $this->builder->build($this->pagesFor(1), 0);

        self::assertSame([1], array_keys($tree));
        // the translations 8 and 9 are not entries of their own
        self::assertSame([2, 10], array_keys($tree[1]['children']));
        self::assertSame([3], array_keys($tree[1]['children'][2]['children']));
    }

    public function testTheTreeNodeCarriesTheTitleOfTheDefaultLanguage(): void
    {
        $tree = $this->builder->build($this->pagesFor(1), 0);

        self::assertSame('News', $tree[1]['children'][2]['title']);
        self::assertSame('Company', $tree[1]['children'][2]['children'][3]['title']);
    }

    public function testATranslationWithoutAnAccessibleOriginalIsStillShown(): void
    {
        $tree = $this->builder->build($this->pagesFor(1), 0);

        self::assertArrayHasKey(10, $tree[1]['children']);
        self::assertSame('Nur auf Deutsch', $tree[1]['children'][10]['title']);
    }

    public function testTheRecordsOfATranslationBelongToThePageOfTheTree(): void
    {
        $tree = $this->builder->build($this->pagesFor(1), 0, $this->newsCounts());

        // one record on page 2 and one on its translation 8
        self::assertSame(2, $tree[1]['children'][2]['newsCount']);
        // plus the record of the subpage and the record of its translation
        self::assertSame(4, $tree[1]['children'][2]['newsTotal']);
        self::assertSame(2, $tree[1]['children'][2]['children'][3]['newsCount']);
        self::assertSame(4, $tree[1]['newsTotal']);
    }

    public function testCollectIdsContainsTheTranslationsOfTheSelectedSubtree(): void
    {
        $pages = $this->pagesFor(1);

        $ids = $this->builder->collectIds($pages, 2);
        sort($ids);

        // 2 and 3 are the pages, 8 and 9 the records the translations are stored on
        self::assertSame([2, 3, 8, 9], $ids);
    }

    public function testCollectIdsRestrictsANonAdminToThePagesOfTheSubtree(): void
    {
        $backendUser = $this->setUpBackendUserAuthentication(2);
        $pages = $this->pageRepository()->findAccessible($backendUser, NewsRepository::TABLE);

        // the editor is mounted on page 2, so page 1 and the translation 10 are gone
        self::assertSame([2, 3, 8, 9], array_keys($pages));

        $ids = $this->builder->collectIds($pages, 2);
        sort($ids);
        self::assertSame([2, 3, 8, 9], $ids);
    }

    public function testTheTreeIsNotEmptyForAnEditorMountedOnASubpage(): void
    {
        $tree = $this->builder->build($this->pagesFor(2), 0);

        // page 1 is not accessible, so "News" is the root of the module tree
        self::assertSame([2], array_keys($tree));
        self::assertSame([3], array_keys($tree[2]['children']));
    }

    public function testTheCountsOfTheModuleCoverTheTranslations(): void
    {
        $backendUser = $this->setUpBackendUserAuthentication(2);
        $pages = $this->pageRepository()->findAccessible($backendUser, NewsRepository::TABLE);
        $pageIds = $this->pageRepository()->resolveRestriction($pages, $backendUser);
        $counts = $this->newsRepository()->countNewsByPage($pageIds);

        self::assertSame(['2' => 1, '3' => 1, '8' => 1, '9' => 1], $counts);
        self::assertSame(4, array_sum($counts));

        $tree = $this->builder->build($pages, 0, $counts);
        self::assertSame(2, $tree[2]['newsCount']);
        self::assertSame(4, $tree[2]['newsTotal']);
    }

    public function testTheNewsOfATranslatedPageAreListedForTheSelectedPage(): void
    {
        $backendUser = $this->setUpBackendUserAuthentication(2);
        $pages = $this->pageRepository()->findAccessible($backendUser, NewsRepository::TABLE);
        $pageIds = $this->pageRepository()->resolveRestriction($pages, $backendUser);
        $storagePageIds = $this->builder->collectIds($pages, 2);

        $constraint = new \Ipf\NewsBunny\Domain\Model\NewsConstraint();
        $constraint->setStoragePage(2);
        $constraint->setStoragePageIds($storagePageIds);

        $rows = $this->newsRepository()->findByConstraint($constraint, $pageIds, 0, 20);

        // all four records, the ones on the translated pages included
        self::assertSame(4, count($rows));
    }
}
