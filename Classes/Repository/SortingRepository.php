<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads the records which are needed to move a record up or down within its storage page.
 */
final class SortingRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Uids of the two records before and after the given record within the same page.
     * The first entry of each list is the direct neighbour.
     *
     * @return array{before: int[], after: int[]}
     */
    public function findNeighbours(string $table, int $pid, int $uid, string $sortingField): array
    {
        $sorting = $this->findSorting($table, $uid, $sortingField);
        if ($sorting === null) {
            return ['before' => [], 'after' => []];
        }

        return [
            'before' => $this->fetchNeighbours($table, $pid, $uid, $sortingField, $sorting, 'lt', 'desc'),
            'after' => $this->fetchNeighbours($table, $pid, $uid, $sortingField, $sorting, 'gt', 'asc'),
        ];
    }

    /**
     * @return int[]
     */
    private function fetchNeighbours(
        string $table,
        int $pid,
        int $uid,
        string $sortingField,
        int $sorting,
        string $operator,
        string $order
    ): array {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->select('uid')->from($table);
        $queryBuilder->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
            $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            $operator === 'lt'
                ? $queryBuilder->expr()->lt($sortingField, $sorting)
                : $queryBuilder->expr()->gt($sortingField, $sorting)
        );
        $queryBuilder->orderBy($sortingField, $order)->setMaxResults(2);

        $uids = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $uids[] = (int)$row['uid'];
        }

        return $uids;
    }

    private function findSorting(string $table, int $uid, string $sortingField): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $sorting = $queryBuilder
            ->select($sortingField)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        return $sorting === false ? null : (int)$sorting;
    }
}
