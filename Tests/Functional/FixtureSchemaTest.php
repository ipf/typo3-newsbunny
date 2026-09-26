<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Guards the CSV fixtures of the functional tests: every column they declare has
 * to exist in the table, otherwise importing a data set fails with an obscure
 * "Call to a member function getType() on null" error.
 */
final class FixtureSchemaTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'ipf/news_bunny',
        'georgringer/news',
    ];

    public function testAllFixtureColumnsExistInTheDatabaseSchema(): void
    {
        $problems = [];

        foreach ((array)glob(__DIR__ . '/Fixtures/*.csv') as $file) {
            $table = null;
            $isFieldList = false;

            foreach (file((string)$file, FILE_IGNORE_NEW_LINES) ?: [] as $lineNumber => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $values = str_getcsv($line, ',', '"', '');

                if (($values[0] ?? '') !== '') {
                    $table = $values[0];
                    $isFieldList = false;
                    continue;
                }
                $values = array_slice($values, 1);

                if (!$isFieldList && in_array('uid', $values, true)) {
                    $isFieldList = true;
                    // the columns have to be looked up with the same API the data set
                    // importer uses, because it matches against the TCA field names
                    // (e.g. "TSconfig") and not against the plain database columns
                    $columns = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable($table)
                        ->getSchemaInformation()
                        ->listTableColumnInfos($table);
                    foreach ($values as $column) {
                        if ($column !== 'uid' && !array_key_exists($column, $columns)) {
                            $problems[] = sprintf(
                                '%s line %d: table "%s" has no field "%s"',
                                basename((string)$file),
                                $lineNumber + 1,
                                $table,
                                $column
                            );
                        }
                    }
                }
            }
        }

        self::assertSame([], $problems, "unknown fixture columns:\n" . implode("\n", $problems));
    }
}
