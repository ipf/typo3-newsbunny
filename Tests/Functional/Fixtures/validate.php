<?php

declare(strict_types=1);

/*
 * Small helper that verifies the structural integrity of the CSV fixtures used
 * by the functional tests. It catches the mistakes that are easy to make in the
 * TYPO3 data set format: a table name line that still carries the leading comma
 * of the field lines, and rows with a different number of values than the field
 * list of their table.
 *
 * The fields are read with fgetcsv, exactly like
 * \TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet does, so
 * values spanning multiple lines (a page TSconfig for example) are handled.
 *
 * Unknown column names are not checked here, that is done by
 * Tests/Functional/FixtureSchemaTest.php which has a database at hand.
 *
 * Usage: php Tests/Functional/Fixtures/validate.php
 */

$files = glob(__DIR__ . '/*.csv') ?: [];
$failed = false;

foreach ($files as $file) {
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        printf("%s\n  can not be opened\n", basename($file));
        $failed = true;
        continue;
    }

    $table = null;
    $fieldCount = 0;
    $problems = [];
    $lineNumber = 0;

    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $lineNumber++;
        if ($values === [null] || implode('', array_map('strval', $values)) === '') {
            continue;
        }

        $first = $values[0] ?? '';
        if ($first !== null && $first !== '') {
            if (str_starts_with(implode(',', array_map('strval', $values)), ',')) {
                $problems[] = sprintf('record %d: table name "%s" must not start with a comma', $lineNumber, $first);
            }
            $table = $first;
            $fieldCount = 0;
            continue;
        }

        $values = array_slice($values, 1);
        $valueCount = count($values);

        if ($fieldCount === 0) {
            $fieldCount = $valueCount;
            // the mm tables of TYPO3 v14 have no uid, every other table is keyed by it
            if (!str_ends_with((string)$table, '_mm') && !in_array('uid', $values, true)) {
                $problems[] = sprintf('record %d: field list of table "%s" has no "uid" column', $lineNumber, (string)$table);
            }
            continue;
        }

        if ($valueCount !== $fieldCount) {
            $problems[] = sprintf(
                'record %d: table "%s" has %d values but %d fields are declared',
                $lineNumber,
                (string)$table,
                $valueCount,
                $fieldCount
            );
        }
    }
    fclose($handle);

    if ($problems !== []) {
        $failed = true;
        printf("%s\n", basename($file));
        foreach ($problems as $problem) {
            printf("  %s\n", $problem);
        }
    }
}

exit($failed ? 1 : 0);
