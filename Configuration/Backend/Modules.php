<?php

declare(strict_types=1);

use Ipf\NewsBunny\Controller\NewsBunnyController;

/**
 * Backend module NewsBunny: administration of EXT:news records.
 */
return [
    'web_newsBunny' => [
        'parent' => 'web',
        'position' => ['after' => '*'],
        'access' => 'user',
        'path' => '/module/web/NewsBunny/',
        'iconIdentifier' => 'module-newsbunny',
        'labels' => 'LLL:EXT:news_bunny/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'NewsBunny',
        'controllerActions' => [
            NewsBunnyController::class => [
                'index',
                'storagePages',
                'createRecord',
                'confirmDelete',
                'deleteRecord',
                'toggleRecord',
                'moveRecord',
            ],
        ],
    ],
];
