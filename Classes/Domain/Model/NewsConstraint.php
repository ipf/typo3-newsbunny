<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Domain\Model;

/**
 * Filter, sorting and pagination state of the NewsBunny record list.
 *
 * The object is mapped from the request by Extbase (see NewsBunnyController::initializeListAction())
 * and is stored in the module data to keep the current state while navigating the backend.
 * All values are validated in the setters because they end up in database queries.
 */
final class NewsConstraint
{
    public const TIME_RESTRICTIONS = ['', 'day', 'month', 'year'];
    public const TOP_NEWS_RESTRICTIONS = ['', '1', '2'];
    public const ARCHIVED_RESTRICTIONS = ['', '1', '2'];
    public const HIDDEN_RESTRICTIONS = ['', '1', '2'];
    public const CATEGORY_CONJUNCTIONS = ['or', 'and', 'notor', 'notand'];
    public const SORTING_DIRECTIONS = ['asc', 'desc'];
    public const SORTABLE_FIELDS = [
        'uid',
        'sorting',
        'title',
        'datetime',
        'archive',
        'istopnews',
        'crdate',
        'tstamp',
        'author',
        'path_segment',
    ];
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    /**
     * The value of the language filter which means "all languages".
     */
    public const LANGUAGE_ALL = -1;

    private ?string $searchWord = null;
    private string $timeRestriction = '';
    private string $manualDateStart = '';
    private string $manualDateStop = '';
    private string $topNewsRestriction = '';
    private string $archived = '';
    private string $hidden = '';
    private array $categories = [];
    private string $categoryConjunction = 'or';
    private bool $includeSubCategories = false;
    private int $language = self::LANGUAGE_ALL;
    private int $storagePage = 0;
    private bool $recursive = false;
    private int $page = 1;
    private int $perPage = 20;
    private string $sortingField = 'datetime';
    private string $sortingDirection = 'desc';

    /**
     * The resolved storage pages including their children, set by the controller.
     *
     * @var int[]
     */
    private array $storagePageIds = [];

    public function getSearchWord(): ?string
    {
        return $this->searchWord;
    }

    public function setSearchWord(?string $searchWord): void
    {
        $searchWord = trim((string)$searchWord);
        $this->searchWord = $searchWord === '' ? null : mb_substr($searchWord, 0, 255);
    }

    public function getTimeRestriction(): string
    {
        return $this->timeRestriction;
    }

    public function setTimeRestriction(string $timeRestriction): void
    {
        $this->timeRestriction = in_array($timeRestriction, self::TIME_RESTRICTIONS, true) ? $timeRestriction : '';
    }

    public function getManualDateStart(): string
    {
        return $this->manualDateStart;
    }

    public function setManualDateStart(string $manualDateStart): void
    {
        $this->manualDateStart = $this->sanitizeDate($manualDateStart);
    }

    public function getManualDateStop(): string
    {
        return $this->manualDateStop;
    }

    public function setManualDateStop(string $manualDateStop): void
    {
        $this->manualDateStop = $this->sanitizeDate($manualDateStop);
    }

    public function getTopNewsRestriction(): string
    {
        return $this->topNewsRestriction;
    }

    public function setTopNewsRestriction(string $topNewsRestriction): void
    {
        $this->topNewsRestriction = in_array($topNewsRestriction, self::TOP_NEWS_RESTRICTIONS, true) ? $topNewsRestriction : '';
    }

    public function getArchived(): string
    {
        return $this->archived;
    }

    public function setArchived(string $archived): void
    {
        $this->archived = in_array($archived, self::ARCHIVED_RESTRICTIONS, true) ? $archived : '';
    }

    public function getHidden(): string
    {
        return $this->hidden;
    }

    public function setHidden(string $hidden): void
    {
        $this->hidden = in_array($hidden, self::HIDDEN_RESTRICTIONS, true) ? $hidden : '';
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): void
    {
        $sanitized = [];
        foreach ($categories as $category) {
            if (is_numeric($category) && (int)$category > 0) {
                $sanitized[] = (int)$category;
            }
        }
        $this->categories = array_values(array_unique($sanitized));
    }

    public function getCategoryConjunction(): string
    {
        return $this->categoryConjunction;
    }

    public function setCategoryConjunction(string $categoryConjunction): void
    {
        $this->categoryConjunction = in_array($categoryConjunction, self::CATEGORY_CONJUNCTIONS, true) ? $categoryConjunction : 'or';
    }

    public function getIncludeSubCategories(): bool
    {
        return $this->includeSubCategories;
    }

    public function setIncludeSubCategories(bool $includeSubCategories): void
    {
        $this->includeSubCategories = $includeSubCategories;
    }

    public function getLanguage(): int
    {
        return $this->language;
    }

    public function setLanguage(int $language): void
    {
        $this->language = $language;
    }

    public function getStoragePage(): int
    {
        return $this->storagePage;
    }

    public function setStoragePage(int $storagePage): void
    {
        $this->storagePage = max(0, $storagePage);
    }

    public function getRecursive(): bool
    {
        return $this->recursive;
    }

    public function setRecursive(bool $recursive): void
    {
        $this->recursive = $recursive;
    }

    /**
     * @return int[]
     */
    public function getStoragePageIds(): array
    {
        return $this->storagePageIds;
    }

    /**
     * @param int[] $storagePageIds
     */
    public function setStoragePageIds(array $storagePageIds): void
    {
        $this->storagePageIds = array_values(array_unique(array_map('intval', $storagePageIds)));
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;
    }

    public function getSortingField(): string
    {
        return $this->sortingField;
    }

    public function setSortingField(string $sortingField): void
    {
        $this->sortingField = in_array($sortingField, self::SORTABLE_FIELDS, true) ? $sortingField : 'datetime';
    }

    public function getSortingDirection(): string
    {
        return $this->sortingDirection;
    }

    public function setSortingDirection(string $sortingDirection): void
    {
        $this->sortingDirection = in_array($sortingDirection, self::SORTING_DIRECTIONS, true) ? $sortingDirection : 'desc';
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Is any filter active? Used to render the "reset filters" button.
     */
    public function hasFilters(): bool
    {
        return $this->searchWord !== null
            || $this->timeRestriction !== ''
            || $this->manualDateStart !== ''
            || $this->manualDateStop !== ''
            || $this->topNewsRestriction !== ''
            || $this->archived !== ''
            || $this->hidden !== ''
            || $this->categories !== []
            || $this->language !== self::LANGUAGE_ALL
            || $this->storagePage !== 0
            || $this->recursive;
    }

    /**
     * Is any filter of the filter form set?
     *
     * Unlike hasFilters() this leaves the page out, because a selected page is not a
     * filter the editor has to see explained in the form: it is shown as selected in
     * the page tree. The module folds the filter away by default and opens it when
     * this says that a filter is set, so that the record list is never narrowed down
     * behind a closed form.
     */
    public function hasFormFilters(): bool
    {
        return $this->searchWord !== null
            || $this->timeRestriction !== ''
            || $this->manualDateStart !== ''
            || $this->manualDateStop !== ''
            || $this->topNewsRestriction !== ''
            || $this->archived !== ''
            || $this->hidden !== ''
            || $this->categories !== []
            || $this->language !== self::LANGUAGE_ALL
            || $this->recursive;
    }

    /**
     * Filter state as request parameters, used to build links (pagination, sorting, ...).
     */
    public function toQueryParameters(): array
    {
        $parameters = [
            'searchWord' => $this->searchWord,
            'timeRestriction' => $this->timeRestriction,
            'manualDateStart' => $this->manualDateStart,
            'manualDateStop' => $this->manualDateStop,
            'topNewsRestriction' => $this->topNewsRestriction,
            'archived' => $this->archived,
            'hidden' => $this->hidden,
            'categories' => $this->categories,
            'categoryConjunction' => $this->categoryConjunction,
            'includeSubCategories' => $this->includeSubCategories,
            'language' => $this->language,
            'storagePage' => $this->storagePage,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sortingField' => $this->sortingField,
            'sortingDirection' => $this->sortingDirection,
        ];

        return array_filter($parameters, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * A copy of this constraint with the given values applied. Used to build
     * links without touching the state of the current request.
     */
    public function withValues(array $values): self
    {
        $clone = clone $this;
        foreach ($values as $property => $value) {
            if (!property_exists($clone, $property)) {
                continue;
            }
            $setter = 'set' . ucfirst($property);
            if (!method_exists($clone, $setter)) {
                continue;
            }
            $value = match ($property) {
                'searchWord' => $value === null ? null : (string)$value,
                'categories' => is_array($value) ? $value : array_filter(explode(',', (string)$value)),
                'page', 'perPage', 'language', 'storagePage' => (int)$value,
                'includeSubCategories', 'recursive' => (bool)$value,
                default => (string)$value,
            };
            $clone->{$setter}($value);
        }
        return $clone;
    }

    /**
     * Timestamp of the lower bound of the time filter or null if not limited.
     */
    public function getTimeLimitLow(): ?int
    {
        if ($this->manualDateStart !== '') {
            $start = strtotime($this->manualDateStart . ' 00:00:00');
            if ($start !== false) {
                return $start;
            }
        }

        $today = new \DateTimeImmutable('today');

        return match ($this->timeRestriction) {
            'day' => $today->getTimestamp(),
            'month' => $today->modify('first day of this month')->getTimestamp(),
            'year' => $today->modify('first day of january')->getTimestamp(),
            default => null,
        };
    }

    /**
     * Timestamp of the upper bound of the time filter or null if not limited.
     */
    public function getTimeLimitHigh(): ?int
    {
        if ($this->manualDateStop !== '') {
            $stop = strtotime($this->manualDateStop . ' 23:59:59');
            if ($stop !== false) {
                return $stop;
            }
        }

        $today = new \DateTimeImmutable('today');

        return match ($this->timeRestriction) {
            'day' => $today->setTime(23, 59, 59)->getTimestamp(),
            'month' => $today->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp(),
            'year' => $today->modify('december 31')->setTime(23, 59, 59)->getTimestamp(),
            default => null,
        };
    }

    private function sanitizeDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        return date('Y-m-d', $timestamp);
    }
}
