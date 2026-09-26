<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Repository;

use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Database access for the NewsBunny record list.
 *
 * The list is a backend listing, therefore records are not filtered by
 * hidden/starttime/endtime - those are the fields the editor wants to see.
 */
final class NewsRepository
{
    public const TABLE = 'tx_news_domain_model_news';
    public const CATEGORY_TABLE = 'sys_category';
    public const TAG_TABLE = 'tx_news_domain_model_tag';
    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';
    private const TAG_MM_TABLE = 'tx_news_domain_model_news_tag_mm';
    private const SELECT_FIELDS = [
        'uid',
        'pid',
        'title',
        'teaser',
        'datetime',
        'archive',
        'istopnews',
        'hidden',
        'author',
        'path_segment',
        'type',
        'sys_language_uid',
        'l10n_parent',
        'starttime',
        'endtime',
        'tstamp',
        'crdate',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param int[]|null $pageIds Storage pages the user has access to, null means "no restriction"
     * @return array<int, array<string, mixed>>
     */
    public function findByConstraint(NewsConstraint $constraint, ?array $pageIds, int $offset, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder($constraint, $pageIds);
        $queryBuilder
            ->select(...self::SELECT_FIELDS)
            ->orderBy($constraint->getSortingField(), $constraint->getSortingDirection())
            // keep the pagination stable if the sorting field is not unique
            ->addOrderBy('uid', $constraint->getSortingDirection())
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param int[]|null $pageIds
     */
    public function countByConstraint(NewsConstraint $constraint, ?array $pageIds): int
    {
        $queryBuilder = $this->createQueryBuilder($constraint, $pageIds);
        $queryBuilder->count('uid');

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * Number of news records without any filter, used for the module header.
     *
     * @param int[]|null $pageIds
     */
    public function countAll(?array $pageIds): int
    {
        $queryBuilder = $this->createQueryBuilder(new NewsConstraint(), $pageIds);
        $queryBuilder->count('uid');

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * @param int[]|null $pageIds Storage pages the user has access to, null means "no restriction"
     * @return array<int, int> Number of news records per storage page
     */
    public function countNewsByPage(?array $pageIds): array
    {
        return $this->countByTable(self::TABLE, $pageIds);
    }

    /**
     * @param int[]|null $pageIds
     * @return array<int, int> Number of category records per storage page
     */
    public function countCategoriesByPage(?array $pageIds): array
    {
        return $this->countByTable(self::CATEGORY_TABLE, $pageIds);
    }

    /**
     * @param int[]|null $pageIds
     * @return array<int, int> Number of tag records per storage page
     */
    public function countTagsByPage(?array $pageIds): array
    {
        return $this->countByTable(self::TAG_TABLE, $pageIds);
    }

    /**
     * @param int[]|null $pageIds
     * @return array<int, int> Number of records per storage page
     */
    private function countByTable(string $table, ?array $pageIds): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('pid')
            ->addSelectLiteral('COUNT(' . $queryBuilder->quoteIdentifier('uid') . ') AS ' . $queryBuilder->quoteIdentifier('count'))
            ->from($table);

        if ($pageIds !== null) {
            $queryBuilder->where(
                $pageIds === []
                    ? $queryBuilder->expr()->eq('uid', 0)
                    : $queryBuilder->expr()->in('pid', $pageIds)
            );
        }

        $queryBuilder->groupBy('pid')->orderBy('pid');

        $counts = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $counts[(int)$row['pid']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * All categories, sorted by title. Used for the category filter.
     *
     * @param int[]|null $rootIds Limit the categories to these roots and their children,
     *                           null means all categories
     * @return array<int, string>
     */
    public function findCategories(?array $rootIds = null): array
    {
        if ($rootIds !== null) {
            $tree = $this->getCategoryTree();
            // The root categories themselves and all of their children
            $allowed = $rootIds;
            $queue = $rootIds;
            while ($queue !== []) {
                $current = array_shift($queue);
                foreach ($tree['children'][$current] ?? [] as $child) {
                    if (!in_array($child, $allowed, true)) {
                        $allowed[] = $child;
                        $queue[] = $child;
                    }
                }
            }
            $rootIds = array_values($allowed);
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('uid', 'title')
            ->from(self::CATEGORY_TABLE)
            ->where(
                $queryBuilder->expr()->neq('title', $queryBuilder->createNamedParameter('', Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('sys_language_uid', 0)
            )
            ->orderBy('title');

        if ($rootIds !== null) {
            if ($rootIds === []) {
                return [];
            }
            $queryBuilder->andWhere($queryBuilder->expr()->in('uid', $rootIds));
        }

        $categories = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $categories[(int)$row['uid']] = (string)$row['title'];
        }

        return $categories;
    }

    /**
     * Categories of the given news records.
     *
     * @param int[] $newsIds
     * @return array<int, array<int, string>> news uid => [category uid => title]
     */
    public function findCategoriesForRecords(array $newsIds): array
    {
        if ($newsIds === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('mm.uid_local', 'mm.uid_foreign', 'category.title')
            ->from(self::CATEGORY_MM_TABLE, 'mm')
            ->join(
                'mm',
                self::CATEGORY_TABLE,
                'category',
                $queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->quoteIdentifier('category.uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter(self::TABLE, Connection::PARAM_STR)),
                $queryBuilder->expr()->in('mm.uid_foreign', $newsIds)
            )
            ->orderBy('category.title');

        $relations = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $relations[(int)$row['uid_foreign']][(int)$row['uid_local']] = (string)$row['title'];
        }

        return $relations;
    }

    /**
     * Tags of the given news records.
     *
     * @param int[] $newsIds
     * @return array<int, array<int, string>> news uid => [tag uid => title]
     */
    public function findTagsForRecords(array $newsIds): array
    {
        if ($newsIds === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TAG_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('mm.uid_local', 'mm.uid_foreign', 'tag.title')
            ->from(self::TAG_MM_TABLE, 'mm')
            ->join(
                'mm',
                self::TAG_TABLE,
                'tag',
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier('tag.uid'))
            )
            ->where($queryBuilder->expr()->in('mm.uid_local', $newsIds))
            ->orderBy('tag.title');

        $relations = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $relations[(int)$row['uid_local']][(int)$row['uid_foreign']] = (string)$row['title'];
        }

        return $relations;
    }

    /**
     * All tags, sorted by title. Used to display the tag badges in the list.
     *
     * @return array<int, string>
     */
    public function findTags(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TAG_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('uid', 'title')
            ->from(self::TAG_TABLE)
            ->orderBy('title');

        $tags = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $tags[(int)$row['uid']] = (string)$row['title'];
        }

        return $tags;
    }

    /**
     * @param int[]|null $pageIds
     */
    private function createQueryBuilder(NewsConstraint $constraint, ?array $pageIds): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        // Backend listing: hidden and time restricted records have to be visible, deleted ones must not
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->from(self::TABLE);

        $constraints = $this->buildConstraints($queryBuilder, $constraint, $pageIds);
        $queryBuilder->where(...$constraints);

        return $queryBuilder;
    }

    /**
     * @param int[]|null $pageIds
     * @return string[]
     */
    private function buildConstraints(QueryBuilder $queryBuilder, NewsConstraint $constraint, ?array $pageIds): array
    {
        $expressionBuilder = $queryBuilder->expr();
        $constraints = [];

        if ($pageIds !== null) {
            if ($pageIds === []) {
                // The user has no access to any page, so no news record can be listed
                $constraints[] = $expressionBuilder->eq('uid', 0);
            } else {
                $constraints[] = $expressionBuilder->in('pid', $pageIds);
            }
        }

        if ($constraint->getStoragePage() > 0) {
            $storagePageIds = $constraint->getStoragePageIds();
            $constraints[] = $storagePageIds === []
                ? $expressionBuilder->eq('pid', 0)
                : $expressionBuilder->in('pid', $storagePageIds);
        }

        $searchWord = $constraint->getSearchWord();
        if ($searchWord !== null) {
            $searchConstraints = [];
            foreach (explode(' ', $searchWord) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $like = '%' . $queryBuilder->escapeLikeWildcards($part) . '%';
                $searchConstraints[] = $expressionBuilder->or(
                    $expressionBuilder->like('title', $queryBuilder->createNamedParameter($like, Connection::PARAM_STR)),
                    $expressionBuilder->like('teaser', $queryBuilder->createNamedParameter($like, Connection::PARAM_STR)),
                    $expressionBuilder->like('bodytext', $queryBuilder->createNamedParameter($like, Connection::PARAM_STR))
                );
            }
            if ($searchConstraints === []) {
                $constraints[] = $expressionBuilder->eq('uid', 0);
            } else {
                $constraints[] = $expressionBuilder->and(...$searchConstraints);
            }
        }

        $timeLimitLow = $constraint->getTimeLimitLow();
        if ($timeLimitLow !== null) {
            $constraints[] = $expressionBuilder->gte('datetime', $timeLimitLow);
        }
        $timeLimitHigh = $constraint->getTimeLimitHigh();
        if ($timeLimitHigh !== null) {
            $constraints[] = $expressionBuilder->lte('datetime', $timeLimitHigh);
        }

        $topNews = $constraint->getTopNewsRestriction();
        if ($topNews === '1') {
            $constraints[] = $expressionBuilder->eq('istopnews', 1);
        } elseif ($topNews === '2') {
            $constraints[] = $expressionBuilder->eq('istopnews', 0);
        }

        $hidden = $constraint->getHidden();
        if ($hidden === '1') {
            $constraints[] = $expressionBuilder->eq('hidden', 1);
        } elseif ($hidden === '2') {
            $constraints[] = $expressionBuilder->eq('hidden', 0);
        }

        $archived = $constraint->getArchived();
        if ($archived !== '') {
            $now = $this->getCurrentTimestamp();
            if ($archived === '1') {
                $constraints[] = $expressionBuilder->or(
                    $expressionBuilder->eq('archive', 0),
                    $expressionBuilder->gt('archive', $now)
                );
            } else {
                $constraints[] = $expressionBuilder->and(
                    $expressionBuilder->gt('archive', 0),
                    $expressionBuilder->lt('archive', $now)
                );
            }
        }

        $language = $constraint->getLanguage();
        if ($language !== NewsConstraint::LANGUAGE_ALL) {
            $constraints[] = $expressionBuilder->eq('sys_language_uid', $language);
        }

        $categories = $constraint->getCategories();
        if ($categories !== []) {
            $newsIds = $this->combineNewsIdsOfCategories(
                $this->findNewsIdsByCategories($categories, $constraint->getIncludeSubCategories()),
                $constraint->getCategoryConjunction()
            );
            $not = str_starts_with($constraint->getCategoryConjunction(), 'not');
            if ($newsIds !== []) {
                $constraints[] = $not
                    ? $expressionBuilder->notIn('uid', $newsIds)
                    : $expressionBuilder->in('uid', $newsIds);
            } elseif (!$not) {
                // No record is related to the selected categories at all
                $constraints[] = $expressionBuilder->eq('uid', 0);
            }
        }

        return $constraints;
    }

    /**
     * @param int[] $categoryIds
     * @return array<int, int[]> category uid => uids of the related news records
     */
    private function findNewsIdsByCategories(array $categoryIds, bool $includeSubCategories): array
    {
        $categoryIds = $this->expandCategories($categoryIds, $includeSubCategories);
        if ($categoryIds === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('uid_local', 'uid_foreign')
            ->from(self::CATEGORY_MM_TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE, Connection::PARAM_STR)),
                $queryBuilder->expr()->in('uid_local', $categoryIds)
            );

        $relations = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $relations[(int)$row['uid_local']][] = (int)$row['uid_foreign'];
        }

        return $relations;
    }

    /**
     * Reduces the news records of the single categories to the records matching the
     * requested combination: "or" builds the union, "and" the intersection of the
     * records. The "not" variants are handled as exclusion of that result by the caller.
     *
     * @param array<int, int[]> $relations category uid => news uids
     * @return int[]
     */
    private function combineNewsIdsOfCategories(array $relations, string $conjunction): array
    {
        if ($relations === []) {
            return [];
        }
        $newsIds = array_values(array_unique(array_merge(...array_values($relations))));
        if ($conjunction === 'and' || $conjunction === 'notand') {
            // keep only the records which are related to every single category
            $newsIds = array_values(array_filter($newsIds, static function (int $uid) use ($relations): bool {
                foreach ($relations as $categoryNewsIds) {
                    if (!in_array($uid, $categoryNewsIds, true)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        return $newsIds;
    }

    /**
     * The category tree, used to expand the selected categories by their children.
     *
     * @return array{titles: array<int, string>, children: array<int, int[]>}
     */
    private function getCategoryTree(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('uid', 'parent', 'title')
            ->from(self::CATEGORY_TABLE);

        $titles = [];
        $children = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $uid = (int)$row['uid'];
            $titles[$uid] = (string)$row['title'];
            $children[(int)$row['parent']][] = $uid;
        }

        return ['titles' => $titles, 'children' => $children];
    }

    /**
     * Adds the children of the given categories, using the tree of the category record.
     *
     * @param int[] $categoryIds
     * @return int[]
     */
    private function expandCategories(array $categoryIds, bool $includeSubCategories): array
    {
        if (!$includeSubCategories) {
            return $categoryIds;
        }

        $tree = $this->getCategoryTree();
        $result = $categoryIds;
        $queue = $categoryIds;
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($tree['children'][$current] ?? [] as $child) {
                if (!in_array($child, $result, true)) {
                    $result[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $result;
    }

    private function getCurrentTimestamp(): int
    {
        return (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
    }
}
