<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the functional test suite of EXT:news_bunny.
 *
 * The actual TYPO3 instance (including the database) is set up per test case by
 * \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase.
 */
(static function () {
    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
