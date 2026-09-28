<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Repository;

use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Repository\PageRepository;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Fixture "page_permissions":
 *
 *   be_users 1 admin       admin flag, no group, no mount
 *   be_users 2 editor      group 1, mounted on page 1
 *   be_users 3 restricted  group 2, mounted on page 1
 *   be_users 4 nomodify    group 3 (no tables_modify), mounted on page 1
 *   be_users 5 submount    group 1, mounted on page 2 only
 *
 *   pages 1 Root               everybody show, editor + group 1 write
 *   pages 2 News               everybody show, editor + group 1 write
 *   pages 3 Company            everybody show, user 4 + group 1 write
 *   pages 4 Read only          everybody show, nobody writes
 *   pages 5 Invisible          nobody may see
 *   pages 6 Private group      only group 2 may see and write
 *   pages 7 Deleted page       soft deleted
 */
final class PageRepositoryTest extends AbstractFunctionalTestCase
{
    private PageRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->pageRepository();
        $this->importFixture('page_permissions');
    }

    private function backendUser(int $uid): BackendUserAuthentication
    {
        return $this->setUpBackendUserAuthentication($uid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pagesFor(int $userUid): array
    {
        return $this->subject->findAccessible($this->backendUser($userUid), NewsRepository::TABLE);
    }

    public function testAdminSeesEveryNotDeletedPage(): void
    {
        $pages = $this->pagesFor(1);

        // page 7 is deleted, page 5 has no permissions at all but admins see everything
        self::assertSame([1, 2, 3, 4, 5, 6], array_keys($pages));
    }

    public function testAdminHasAllPermissionsOnEveryPage(): void
    {
        foreach ($this->pagesFor(1) as $page) {
            self::assertTrue($page['editable'], 'page ' . $page['uid'] . ' is editable');
            self::assertTrue($page['creatable'], 'page ' . $page['uid'] . ' is creatable');
        }
    }

    public function testNonAdminOnlySeesPagesWithShowPermission(): void
    {
        // page 4 is readable for everybody, page 5 for nobody, page 6 only for group 2
        self::assertSame([1, 2, 3, 4], array_keys($this->pagesFor(2)));
    }

    public function testEditableAndCreatableReflectThePagePermissions(): void
    {
        $pages = $this->pagesFor(2);

        self::assertTrue($pages[2]['editable']);
        self::assertTrue($pages[2]['creatable']);
    }

    public function testAReadOnlyPageIsVisibleButNotEditable(): void
    {
        $pages = $this->pagesFor(2);

        self::assertFalse($pages[4]['editable'], 'nobody may write page 4');
        self::assertFalse($pages[4]['creatable'], 'nobody may create records on page 4');
    }

    public function testWithoutTableModifyPermissionRecordsCannotBeCreated(): void
    {
        // uid 4 owns the page permissions of page 3, but its group has no tables_modify
        $pages = $this->pagesFor(4);

        self::assertArrayHasKey(3, $pages);
        self::assertTrue($pages[3]['editable'], 'the user owns the page');
        self::assertFalse($pages[3]['creatable'], 'but may not modify the news table');
    }

    public function testPagesOutsideTheWebMountsAreExcluded(): void
    {
        // uid 5 is mounted on page 2, so page 1 is not reachable even with full permissions
        self::assertSame([2, 3, 4], array_keys($this->pagesFor(5)));
    }

    public function testAGroupOnlyPageIsVisibleForItsMembers(): void
    {
        $pages = $this->pagesFor(3);

        self::assertArrayHasKey(6, $pages);
        self::assertTrue($pages[6]['editable']);
        self::assertTrue($pages[6]['creatable']);
    }

    public function testAGroupOnlyPageIsHiddenFromOtherUsers(): void
    {
        self::assertArrayNotHasKey(6, $this->pagesFor(2));
    }

    public function testPathsAreBuiltFromTheAccessibleAncestors(): void
    {
        $pages = $this->pagesFor(1);

        self::assertSame('Root', $pages[1]['path']);
        self::assertSame('Root / News', $pages[2]['path']);
        self::assertSame('Root / News / Company', $pages[3]['path']);
        self::assertSame('Root / News / Read only', $pages[4]['path']);
        self::assertSame('Root / Invisible for everyone', $pages[5]['path']);
        self::assertSame('Root / News / Private to restricted group', $pages[6]['path']);
    }

    public function testPathsOnlyContainAccessibleAncestors(): void
    {
        // uid 5 is mounted on page 2, so the path of page 3 starts at "News"
        $pages = $this->pagesFor(5);

        self::assertSame('News', $pages[2]['path']);
        self::assertSame('News / Company', $pages[3]['path']);
    }

    public function testHiddenFlagIsExposed(): void
    {
        $pages = $this->pagesFor(1);

        self::assertFalse($pages[2]['hidden']);
        self::assertTrue($pages[5]['hidden']);
    }

    public function testUidAndPidAreExposed(): void
    {
        $pages = $this->pagesFor(1);

        self::assertSame(3, $pages[3]['uid']);
        self::assertSame(2, $pages[3]['pid']);
    }

    public function testDeletedPagesAreNeverReturned(): void
    {
        self::assertArrayNotHasKey(7, $this->pagesFor(1));
        self::assertArrayNotHasKey(7, $this->pagesFor(2));
    }

    public function testResolveRestrictionIsNullForAdmins(): void
    {
        $backendUser = $this->backendUser(1);
        $pages = $this->subject->findAccessible($backendUser, NewsRepository::TABLE);

        self::assertNull($this->subject->resolveRestriction($pages, $backendUser));
    }

    public function testResolveRestrictionReturnsThePageUidsForNonAdmins(): void
    {
        $backendUser = $this->backendUser(2);
        $pages = $this->subject->findAccessible($backendUser, NewsRepository::TABLE);

        self::assertSame([1, 2, 3, 4], $this->subject->resolveRestriction($pages, $backendUser));
    }

    public function testResolveRestrictionIsAnEmptyListForANonAdminWithoutAnyPage(): void
    {
        self::assertSame([], $this->subject->resolveRestriction([], $this->backendUser(2)));
    }
}
