<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves the settings of the module.
 *
 * The defaults are defined in Configuration/Module/Settings.php and can be overwritten
 * per page in the page TSconfig with "tx_newsbunny.module.*", which is the way the
 * settings of a page specific module are usually configured.
 */
final class SettingsProvider
{
    public const EXTENSION_KEY = 'news_bunny';
    public const TS_CONFIG_PATH = 'tx_newsbunny.module.';

    public const COLUMNS = [
        'title',
        'teaser',
        'datetime',
        'archive',
        'categories',
        'tags',
        'author',
        'path_segment',
        'status',
        'language',
        'page',
        'uid',
        'crdate',
        'tstamp',
    ];

    private const FILTERS = [
        'searchWord',
        'timeRestriction',
        'topNewsRestriction',
        'archived',
        'hidden',
        'language',
        'categories',
        'categoryConjunction',
        'includeSubCategories',
        'recursive',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct()
    {
        $this->settings = $this->getDefaults();
    }

    /**
     * Starts over with the defaults and merges the TSconfig of the given page into them.
     */
    public function applyPageTsConfig(int $pageId): void
    {
        $this->settings = $this->merge($this->getDefaults(), $this->readTsConfig($pageId));
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        $defaults = require GeneralUtility::getFileAbsFileName('EXT:' . self::EXTENSION_KEY . '/Configuration/Module/Settings.php');

        return is_array($defaults) ? $defaults : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->settings[$key] ?? $default;

        return is_numeric($value) ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->settings[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower((string)$value), ['0', 'false', 'off', 'no'], true);
    }

    /**
     * @return int[]
     */
    public function getIntList(string $key): array
    {
        $value = $this->settings[$key] ?? '';
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $list = GeneralUtility::trimExplode(',', (string)$value, true);
        $list = array_map('intval', $list);

        return array_values(array_filter($list, static fn(int $id): bool => $id > 0));
    }

    public function getDefaultPid(string $table): int
    {
        $defaultPid = $this->settings['defaultPid'] ?? [];
        if (!is_array($defaultPid)) {
            return 0;
        }
        $value = $defaultPid[$table] ?? 0;

        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * @return string[]
     */
    public function getPreselect(): array
    {
        $preselect = $this->settings['preselect'] ?? [];

        return is_array($preselect) ? $preselect : [];
    }

    /**
     * Is the given filter enabled in the filter form?
     */
    public function isFilterEnabled(string $filter): bool
    {
        if (!in_array($filter, self::FILTERS, true)) {
            return true;
        }
        $filters = $this->settings['filters'] ?? [];
        if (!is_array($filters) || !array_key_exists($filter, $filters)) {
            return true;
        }
        $value = $filters[$filter];
        if (is_bool($value)) {
            return $value;
        }
        $enabled = !in_array(strtolower((string)$value), ['0', 'false', 'off', 'no', ''], true);

        // The category combination only makes sense together with the category filter
        if (!$enabled && $filter === 'categoryConjunction') {
            return $this->isFilterEnabled('categories');
        }
        if (!$enabled && $filter === 'includeSubCategories') {
            return $this->isFilterEnabled('categories');
        }

        return $enabled;
    }

    /**
     * The columns of the record list, "title" is always the first column.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        $value = $this->settings['columns'] ?? '';
        $columns = array_values(array_filter(array_map('trim', GeneralUtility::trimExplode(',', (string)$value, true))));
        $columns = array_values(array_intersect($columns, self::COLUMNS));
        if (!in_array('title', $columns, true)) {
            array_unshift($columns, 'title');
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function readTsConfig(int $pageId): array
    {
        try {
            $tsConfig = BackendUtility::getPagesTSconfig($pageId);
        } catch (\Throwable) {
            return [];
        }
        $settings = $tsConfig['tx_newsbunny.']['module.'] ?? [];

        return is_array($settings) ? $this->normalizeKeys($settings) : [];
    }

    /**
     * The TSconfig uses dot suffixed keys, e.g. "filters.", which are normalized
     * to the plain keys used by the settings.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $settings): array
    {
        $normalized = [];
        foreach ($settings as $key => $value) {
            $key = rtrim((string)$key, '.');
            $normalized[$key] = is_array($value) ? $this->normalizeKeys($value) : $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }
            if ($value === '' && ($base[$key] ?? null) !== '' && is_string($base[$key] ?? null)) {
                // An empty value in the TSconfig resets a value of the defaults
                $base[$key] = '';
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
