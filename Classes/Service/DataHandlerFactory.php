<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Service;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates DataHandler instances.
 *
 * A DataHandler carries the state of a single modification (control map, data
 * map, error log), so it must not be shared. It is therefore not injected into
 * the controller directly but created per operation through this factory, which
 * also keeps the controller testable.
 */
final class DataHandlerFactory
{
    public function create(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }
}
