<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-newsbunny' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:news_bunny/Resources/Public/Icons/module-newsbunny.svg',
    ],
    'module-newsbunny-storage-pages' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:news_bunny/Resources/Public/Icons/module-newsbunny-storage-pages.svg',
    ],
];
