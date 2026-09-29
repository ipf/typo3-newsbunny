<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Registration of the icons of the module.
 *
 * The two Core versions draw the icons of the backend in two different languages, so the
 * module needs one file per language and a condition that picks the right one:
 *
 * - TYPO3 14 draws the module icons as line art: no background rectangle, the silhouette
 *   in currentColor so it follows the text color of the theme, and the accent from the CSS
 *   token "icon-color-accent". Those are the files in Resources/Public/Icons.
 * - TYPO3 13 draws them as a white symbol on a full-bleed box of a fixed color, the way
 *   its own module icons are drawn. A v14 icon is transparent, so in 13 it would land on
 *   the neutral tile of the module menu instead of reading as a module icon. Those files
 *   are in Resources/Public/Icons/v13.
 *
 * The identifiers stay the same in both branches, so the module registration and the button
 * of the controller do not have to know which Core version is running.
 */
$isLegacyIconLanguage = (new Typo3Version())->getMajorVersion() < 14;

$iconDirectory = $isLegacyIconLanguage ? 'Icons/v13' : 'Icons';

return [
    'module-newsbunny' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:news_bunny/Resources/Public/' . $iconDirectory . '/module-newsbunny.svg',
    ],
    'module-newsbunny-storage-pages' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:news_bunny/Resources/Public/' . $iconDirectory . '/module-newsbunny-storage-pages.svg',
    ],
];
