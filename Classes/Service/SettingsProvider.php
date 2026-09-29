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

    /**
     * The column with the buttons of the actions, it has no record field behind it.
     */
    public const CONTROL_COLUMN = 'control';

    /**
     * Layout of the columns of the record list: the weight of the column in the width of
     * the table and whether its content stays in one line.
     *
     * Without a width the browser hands the space of the table to the column with the
     * longest content, which is the teaser or the title, and squeezes the short columns
     * until a date breaks into two lines. The table renders with a fixed layout, so the
     * colgroup of the table is what decides the width of a column.
     */
    private const COLUMN_LAYOUT = [
        'title' => ['weight' => 26, 'nowrap' => false],
        'teaser' => ['weight' => 30, 'nowrap' => false],
        'page' => ['weight' => 16, 'nowrap' => false],
        'categories' => ['weight' => 12, 'nowrap' => false],
        'tags' => ['weight' => 12, 'nowrap' => false],
        'path_segment' => ['weight' => 12, 'nowrap' => false],
        'author' => ['weight' => 10, 'nowrap' => false],
        'status' => ['weight' => 12, 'nowrap' => false],
        'datetime' => ['weight' => 12, 'nowrap' => true],
        'archive' => ['weight' => 12, 'nowrap' => true],
        'crdate' => ['weight' => 12, 'nowrap' => true],
        'tstamp' => ['weight' => 12, 'nowrap' => true],
        'language' => ['weight' => 8, 'nowrap' => true],
        'uid' => ['weight' => 5, 'nowrap' => true],
    ];

    /**
     * The column with the buttons of a record is not a text column: its buttons are the
     * same in every row, they must not wrap and the count of the columns has no influence
     * on their size. It therefore gets a share of its own instead of a share of the
     * weights, and only within these bounds - a title only list must not spend a third of
     * the table on the buttons, and a list with ten columns still has to fit them.
     */
    private const CONTROL_COLUMN_PERCENT_MIN = 12;
    private const CONTROL_COLUMN_PERCENT_MAX = 24;
    private const CONTROL_COLUMN_WEIGHT = 14;

    /**
     * Weight of a column without an entry in COLUMN_LAYOUT. getColumns() only returns the
     * known columns, so this is a safety net.
     */
    private const FALLBACK_COLUMN_WEIGHT = 10;

    /**
     * Narrowest column of the table in rem, used to work out the min-width of the table.
     * It is the width of a date with its padding.
     */
    private const MIN_COLUMN_WIDTH_REM = 9;

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
     * Widths of the columns of the record list and the columns that keep one line.
     *
     * The widths are the percent of the table a column takes, including the column with
     * the actions, and they always add up to 100: a table with a fixed layout hands the
     * space that is left over to the last column, so a sum below 100 would blow up the
     * column of the buttons. The weights are therefore normalized over the columns the
     * editor has configured, which keeps a date readable and the teaser wide, no matter
     * whether two or ten columns are configured.
     *
     * @param string[] $columns
     * @return array{widths: array<string, string>, nowrap: array<string, true>, minWidth: string}
     */
    public function getColumnLayout(array $columns): array
    {
        $weights = [];
        foreach ($columns as $column) {
            $weights[$column] = self::COLUMN_LAYOUT[$column]['weight'] ?? self::FALLBACK_COLUMN_WEIGHT;
        }

        $controlPercent = $this->resolveControlColumnPercent($weights);
        $widths = $this->distributePercent($weights, 100 - $controlPercent);
        $widths[self::CONTROL_COLUMN] = $controlPercent . '%';

        $nowrap = [];
        foreach ($columns as $column) {
            if (self::COLUMN_LAYOUT[$column]['nowrap'] ?? false) {
                $nowrap[$column] = true;
            }
        }

        return [
            'widths' => $widths,
            'nowrap' => $nowrap,
            'minWidth' => $this->resolveMinWidth($columns),
        ];
    }

    /**
     * Narrowest width of the table, so that a list with many columns scrolls sideways
     * instead of letting the content of the columns run into each other.
     *
     * A table with a fixed layout does not widen itself for content that does not fit into
     * a column, it lets the content overflow instead, and the badges of the status column
     * then sit on top of the text of the column next to it.
     *
     * @param string[] $columns
     */
    private function resolveMinWidth(array $columns): string
    {
        // one width for the columns, one for the column with the buttons
        $rem = (count($columns) + 1) * self::MIN_COLUMN_WIDTH_REM;

        return $rem . 'rem';
    }

    /**
     * Share of the table for the column with the buttons of the actions.
     *
     * @param array<string, int> $weights
     */
    private function resolveControlColumnPercent(array $weights): int
    {
        $sum = array_sum($weights);
        $percent = $sum > 0 ? (int)round(self::CONTROL_COLUMN_WEIGHT / ($sum + self::CONTROL_COLUMN_WEIGHT) * 100) : 0;

        return max(self::CONTROL_COLUMN_PERCENT_MIN, min(self::CONTROL_COLUMN_PERCENT_MAX, $percent));
    }

    /**
     * Shares the given percent of the table over the given weights.
     *
     * Every column is rounded down, the percent that are left over go to the columns with
     * the largest share, so the widths add up to exactly the given percent.
     *
     * @param array<string, int> $weights
     * @return array<string, string>
     */
    private function distributePercent(array $weights, int $percent): array
    {
        $sum = array_sum($weights);
        $widths = [];
        $rest = $percent;
        foreach ($weights as $column => $weight) {
            $share = $sum > 0 ? (int)floor($weight / $sum * $percent) : 0;
            $widths[$column] = $share;
            $rest -= $share;
        }

        // the columns with the largest share get the percent that are left over, so the
        // rounding does not take the space away from the column with the least text
        arsort($weights);
        foreach (array_keys($weights) as $column) {
            if ($rest === 0) {
                break;
            }
            $widths[$column]++;
            $rest--;
        }

        return array_map(static fn(int $share): string => $share . '%', $widths);
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
