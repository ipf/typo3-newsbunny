<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Repository;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides the pages (storage folders) of the news records the backend user is allowed to see.
 *
 * The result contains the effective page permissions, so the module can decide whether
 * a record may be edited and whether new records may be created on that page.
 */
final class PageRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, array{
     *     uid: int,
     *     pid: int,
     *     title: string,
     *     path: string,
     *     hidden: bool,
     *     editable: bool,
     *     creatable: bool
     * }>
     */
    public function findAccessible(BackendUserAuthentication $backendUser, string $table): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select(
                'uid',
                'pid',
                'title',
                'hidden',
                't3ver_oid',
                'sys_language_uid',
                'l10n_parent',
                'perms_userid',
                'perms_groupid',
                'perms_user',
                'perms_group',
                'perms_everybody',
            )
            ->from('pages');

        $permsClause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);
        if (trim($permsClause) !== '' && trim($permsClause) !== '1=1') {
            $queryBuilder->andWhere($permsClause);
        }

        $mayModifyTable = $backendUser->check('tables_modify', $table);
        $pages = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $uid = (int)$row['uid'];
            if (!$backendUser->isAdmin() && $backendUser->isInWebMount($row, $permsClause) === null) {
                // Page is readable by permission but outside the web mounts of the user
                continue;
            }
            $permissions = $this->calculatePermissions($backendUser, $row);
            $pages[$uid] = [
                'uid' => $uid,
                'pid' => (int)$row['pid'],
                'title' => (string)$row['title'],
                'path' => '',
                'hidden' => (bool)$row['hidden'],
                'editable' => ($permissions & Permission::PAGE_EDIT) === Permission::PAGE_EDIT,
                'creatable' => $mayModifyTable
                    && ($permissions & (Permission::PAGE_EDIT | Permission::CONTENT_EDIT)) === (Permission::PAGE_EDIT | Permission::CONTENT_EDIT),
            ];
        }

        $this->buildPaths($pages);

        return $pages;
    }

    /**
     * The uids of all pages the user may read, the restriction for the news queries.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return int[]|null Null means "no restriction", which is used for admin users
     */
    public function resolveRestriction(array $pages, BackendUserAuthentication $backendUser): ?array
    {
        return $backendUser->isAdmin() ? null : array_keys($pages);
    }

    /**
     * The pages as simple list of options for the storage page filter.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return array<int, string>
     */
    public function buildOptions(array $pages): array
    {
        $options = [];
        foreach ($pages as $page) {
            $options[$page['uid']] = $page['path'] !== '' ? $page['path'] : $page['title'];
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * Fills the "path" key of every page with the path of all accessible ancestors.
     *
     * @param array<int, array<string, mixed>> $pages
     */
    private function buildPaths(array &$pages): void
    {
        foreach (array_keys($pages) as $uid) {
            $path = [];
            $currentUid = $uid;
            // guard against loops, 64 levels are far beyond any sane page tree
            for ($level = 0; $level < 64; $level++) {
                if (!isset($pages[$currentUid])) {
                    break;
                }
                array_unshift($path, (string)$pages[$currentUid]['title']);
                $parentUid = (int)$pages[$currentUid]['pid'];
                if ($parentUid === $currentUid) {
                    break;
                }
                $currentUid = $parentUid;
            }
            $pages[$uid]['path'] = implode(' / ', $path);
        }
    }

    /**
     * Effective page permissions of the user, without the additional database
     * queries done by BackendUserAuthentication::calcPerms().
     *
     * @param array<string, mixed> $row
     */
    private function calculatePermissions(BackendUserAuthentication $backendUser, array $row): int
    {
        if ($backendUser->isAdmin()) {
            return Permission::ALL;
        }

        $permissions = (int)$row['perms_everybody'];
        if ((int)$backendUser->user['uid'] === (int)$row['perms_userid']) {
            $permissions |= (int)$row['perms_user'];
        }
        if (in_array((int)$row['perms_groupid'], array_map('intval', $backendUser->userGroupsUID), true)) {
            $permissions |= (int)$row['perms_group'];
        }

        return $permissions;
    }
}
