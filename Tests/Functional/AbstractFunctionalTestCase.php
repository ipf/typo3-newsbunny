<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional;

use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Repository\PageRepository;
use Ipf\NewsBunny\Repository\SortingRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for the functional tests of EXT:news_bunny.
 *
 * The repositories of this extension work on the tables of EXT:news and on the
 * core tables, so both extensions are activated in the test instance.
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'ipf/news_bunny',
        'georgringer/news',
    ];

    /**
     * The repositories get the same dependencies the container injects into them,
     * so a change of a constructor is noticed here. They are private services and
     * can therefore not be fetched from the container.
     *
     * A DeletedRestriction is a stateless collaborator that only holds the schema
     * factory, which is also why the repositories may keep it as a dependency.
     */
    final protected function newsRepository(): NewsRepository
    {
        return new NewsRepository(
            $this->getConnectionPool(),
            new DeletedRestriction(),
            $this->get(Context::class),
        );
    }

    final protected function pageRepository(): PageRepository
    {
        return new PageRepository(
            $this->getConnectionPool(),
            new DeletedRestriction(),
        );
    }

    final protected function sortingRepository(): SortingRepository
    {
        return new SortingRepository(
            $this->getConnectionPool(),
            new DeletedRestriction(),
        );
    }

    /**
     * Path of a fixture relative to Tests/Functional/Fixtures.
     */
    final protected function fixturePath(string $name): string
    {
        return __DIR__ . '/Fixtures/' . $name . '.csv';
    }

    final protected function importFixture(string $name): void
    {
        $this->importCSVDataSet($this->fixturePath($name));
    }

    /**
     * Uids of the given news records, in the order returned by the repository.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return int[]
     */
    final protected function uidsOf(array $rows): array
    {
        return array_map(static fn(array $row): int => (int)$row['uid'], $rows);
    }

    final protected function setUpBackendUserAuthentication(int $userUid): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $backendUser->fetchGroupData();

        return $backendUser;
    }
}
