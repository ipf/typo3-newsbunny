<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Controller;

use Ipf\NewsBunny\Domain\Model\ModuleContext;
use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Repository\PageRepository;
use Ipf\NewsBunny\Repository\SortingRepository;
use Ipf\NewsBunny\Service\DataHandlerFactory;
use Ipf\NewsBunny\Service\PageTreeBuilder;
use Ipf\NewsBunny\Service\SettingsProvider;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module NewsBunny.
 *
 * Provides a record list with powerful filters, a listing of the pages the records
 * are stored on, control panels to toggle, sort and delete records and shortcuts to
 * create news, category and tag records.
 */
final class NewsBunnyController extends ActionController
{
    use AllowedMethodsTrait;

    private const MODULE_IDENTIFIER = 'web_newsBunny';
    private const CATEGORY_TABLE = 'sys_category';
    private const CREATEABLE_TABLES = [
        NewsRepository::TABLE => 'news',
        self::CATEGORY_TABLE => 'category',
        NewsRepository::TAG_TABLE => 'tag',
    ];

    /**
     * The page of the current request, part of every generated module url.
     */
    private int $pageId = 0;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly NewsRepository $newsRepository,
        private readonly PageRepository $pageRepository,
        private readonly SortingRepository $sortingRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly SettingsProvider $settingsProvider,
        private readonly PageTreeBuilder $pageTreeBuilder,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly UriBuilder $moduleUriBuilder,
        private readonly IconFactory $iconFactory,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly DataHandlerFactory $dataHandlerFactory,
        private readonly Context $context,
    ) {}

    public function initializeIndexAction(): void
    {
        $this->arguments->getArgument('constraint')->getPropertyMappingConfiguration()->allowAllProperties();
    }

    /**
     * The record list with the page tree, the filter and the record columns.
     */
    public function indexAction(?NewsConstraint $constraint = null): ResponseInterface
    {
        $context = $this->createContext($constraint);
        $redirect = $this->applyRedirectOnStart($context);
        if ($redirect !== null) {
            return $redirect;
        }

        $constraint = $context->constraint;
        $pageIds = $context->pageIds;
        $total = $this->newsRepository->countByConstraint($constraint, $pageIds);
        $perPage = $constraint->getPerPage();
        $pageCount = max(1, (int)ceil($total / $perPage));
        if ($constraint->getPage() > $pageCount) {
            $constraint->setPage($pageCount);
        }

        $controlPanels = $context->settings->getBool('controlPanels');
        $records = [];
        if ($total > 0) {
            $rows = $this->newsRepository->findByConstraint($constraint, $pageIds, $constraint->getOffset(), $perPage);
            $uids = array_map(static fn(array $row): int => (int)$row['uid'], $rows);
            $categories = $this->newsRepository->findCategoriesForRecords($uids);
            $tags = $this->newsRepository->findTagsForRecords($uids);
            foreach ($rows as $row) {
                $record = $this->prepareRecord($row, $context);
                $record['categories'] = $categories[$record['uid']] ?? [];
                $record['tags'] = $tags[$record['uid']] ?? [];
                $record['tagsList'] = implode(', ', $record['tags']);
                $records[] = $record;
            }
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->addButtonBarButtons($moduleTemplate, $context);
        $this->prepareModuleTemplate($moduleTemplate);

        $columns = $context->settings->getColumns();

        // One grouped query serves both the header and the indicator of the page tree
        $newsCounts = $this->newsRepository->countNewsByPage($context->pageIds);

        return $moduleTemplate
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle($this->getLabel('module.title', [], 'locallang_mod.xlf'))
            ->assignMultiple([
                'context' => $context,
                'pageId' => $context->pageId,
                'pageTree' => $this->createPageTreeView($context, $newsCounts),
                'filter' => [
                    // the filter is folded away by default, but a filter which narrows
                    // the list down has to stay visible, also with a folded form
                    'open' => $context->settings->getBool('alwaysShowFilter')
                        || $constraint->hasFormFilters(),
                    'enabled' => $this->createFilterMap($context->settings),
                ],
                'records' => $records,
                'total' => $total,
                'totalCount' => array_sum($newsCounts),
                'page' => $constraint->getPage(),
                'pageCount' => $pageCount,
                'perPageOptions' => NewsConstraint::PER_PAGE_OPTIONS,
                'formValues' => $this->createFormValues($constraint),
                'columns' => $columns,
                'localizationView' => $context->settings->getBool('localizationView'),
                'controlPanels' => $controlPanels,
                'sortable' => $this->isRecordTableSortingAware(),
                'counts' => $this->fetchCounts($context, $newsCounts),
                'categoryOptions' => $this->newsRepository->findCategories($this->resolveCategoryRoots($context)),
                'sortingLinks' => $this->createSortingLinks($constraint, $context),
                'sortingIcons' => $this->createSortingIcons($constraint),
                'columnLayout' => $context->settings->getColumnLayout($columns),
                'pagination' => $this->createPagination($constraint, $pageCount),
                'resetUrl' => $this->createResetUri($constraint, $context),
                'storagePagesUrl' => $this->createModuleUri(['action' => 'storagePages']),
            ])
            ->renderResponse('NewsBunny/Index');
    }

    /**
     * Shows on which pages news, categories and tags are stored.
     */
    public function storagePagesAction(): ResponseInterface
    {
        $context = $this->createContext(null);
        $redirect = $this->applyRedirectOnStart($context);
        if ($redirect !== null) {
            return $redirect;
        }

        $pageIds = $context->pageIds;
        $newsCounts = $this->newsRepository->countNewsByPage($pageIds);
        $categoryCounts = $this->newsRepository->countCategoriesByPage($pageIds);
        $tagCounts = $this->newsRepository->countTagsByPage($pageIds);

        $rows = [];
        $storagePageIds = array_unique(array_merge(
            array_keys($newsCounts),
            array_keys($categoryCounts),
            array_keys($tagCounts)
        ));
        foreach ($storagePageIds as $storagePageId) {
            if (!isset($context->pages[$storagePageId])) {
                continue;
            }
            $page = $context->pages[$storagePageId];
            $rows[] = [
                'page' => $page,
                'newsCount' => $newsCounts[$storagePageId] ?? 0,
                'categoryCount' => $categoryCounts[$storagePageId] ?? 0,
                'tagCount' => $tagCounts[$storagePageId] ?? 0,
                'listUrl' => $this->createModuleUri(['constraint' => ['storagePage' => $storagePageId]]),
                'pageEditUrl' => $page['editable'] ? $this->createRecordEditUri('pages', $storagePageId) : '',
                'createUrls' => $this->createRecordUrls($page),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['page']['path'], $b['page']['path']));

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->prepareModuleTemplate($moduleTemplate);

        return $moduleTemplate
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle($this->getLabel('storagePages.title'))
            ->assignMultiple([
                'context' => $context,
                'pageId' => $context->pageId,
                'rows' => $rows,
                'listUrl' => $this->createModuleUri(['action' => 'index']),
            ])
            ->renderResponse('NewsBunny/StoragePages');
    }

    /**
     * Redirects to the FormEngine to create a new news, category or tag record.
     */
    public function createRecordAction(string $table, int $pid): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        if (!isset(self::CREATEABLE_TABLES[$table])) {
            return $this->redirect('index');
        }
        if (!$backendUser->check('tables_modify', $table)) {
            $this->addFlashMessage(
                $this->getLabel('error.noPermission', [$this->getLabel('recordType.' . self::CREATEABLE_TABLES[$table])]),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::WARNING
            );
            return $this->redirect('index');
        }

        $pages = $this->pageRepository->findAccessible($backendUser, NewsRepository::TABLE);
        if (!($pages[$pid]['creatable'] ?? false)) {
            $this->addFlashMessage(
                $this->getLabel('error.noCreatePermissionOnPage', [$pages[$pid]['path'] ?? (string)$pid]),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::WARNING
            );
            return $this->redirect('index');
        }

        return new RedirectResponse($this->createRecordEditUri($table, $pid, true));
    }

    public function initializeConfirmDeleteAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'GET');
    }

    /**
     * Asks the editor to confirm before a news record is deleted.
     */
    public function confirmDeleteAction(int $uid): ResponseInterface
    {
        $context = $this->createContext(null);
        $record = $this->fetchRecord($uid, $context);
        if ($record === null) {
            return $this->redirect('index');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->prepareModuleTemplate($moduleTemplate);

        return $moduleTemplate
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle($this->getLabel('delete.title'))
            ->assignMultiple([
                'context' => $context,
                'pageId' => $context->pageId,
                'record' => $record,
                'cancelUrl' => $this->createModuleUri(['action' => 'index']),
            ])
            ->renderResponse('NewsBunny/ConfirmDelete');
    }

    public function initializeDeleteRecordAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    /**
     * Deletes the news record which the editor confirmed to delete.
     */
    public function deleteRecordAction(int $uid): ResponseInterface
    {
        $context = $this->createContext(null);
        $record = $this->fetchRecord($uid, $context);
        if ($record === null) {
            return $this->redirect('index');
        }

        $dataHandler = $this->dataHandlerFactory->create();
        $dataHandler->start([], [NewsRepository::TABLE => [$uid => ['delete' => '1']]]);
        $dataHandler->process_cmdmap();

        $this->addFlashMessagesFromDataHandler($dataHandler, $this->getLabel('delete.success', [$uid]));

        return $this->redirect('index');
    }

    public function initializeToggleRecordAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    /**
     * Control panel action to show or hide a news record.
     */
    public function toggleRecordAction(int $uid): ResponseInterface
    {
        $context = $this->createContext(null);
        $record = $this->fetchRecord($uid, $context);
        if ($record === null) {
            return $this->redirect('index');
        }

        $hidden = (int)$record['hidden'] === 1 ? 0 : 1;
        $dataHandler = $this->dataHandlerFactory->create();
        $dataHandler->start([NewsRepository::TABLE => [$uid => ['hidden' => $hidden]]], []);
        $dataHandler->process_datamap();

        $this->addFlashMessagesFromDataHandler(
            $dataHandler,
            $this->getLabel('toggle.success', [$hidden === 1 ? $this->getLabel('toggle.hidden') : $this->getLabel('toggle.visible')])
        );

        return $this->redirect('index');
    }

    public function initializeMoveRecordAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    /**
     * Control panel action to move a news record up or down within its storage page.
     */
    public function moveRecordAction(int $uid, string $direction): ResponseInterface
    {
        $context = $this->createContext(null);
        $record = $this->fetchRecord($uid, $context);
        if ($record === null) {
            return $this->redirect('index');
        }
        if (!$this->isRecordTableSortingAware()) {
            $this->addFlashMessage(
                $this->getLabel('error.manualSortingDisabled'),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::WARNING
            );
            return $this->redirect('index');
        }
        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->redirect('index');
        }

        $sortingField = $this->getSortingField();
        $neighbours = $this->sortingRepository->findNeighbours(
            NewsRepository::TABLE,
            (int)$record['pid'],
            $uid,
            $sortingField
        );
        // The record is moved behind its second neighbour, or to the top of the page
        $target = $direction === 'up' ? ($neighbours['before'][1] ?? 0) : ($neighbours['after'][1] ?? 0);
        $destination = $target > 0 ? -$target : (int)$record['pid'];

        $dataHandler = $this->dataHandlerFactory->create();
        $dataHandler->start([], [NewsRepository::TABLE => [$uid => ['move' => $destination]]]);
        $dataHandler->process_cmdmap();

        $this->addFlashMessagesFromDataHandler(
            $dataHandler,
            $this->getLabel('move.success', [$direction === 'up' ? $this->getLabel('move.up') : $this->getLabel('move.down')])
        );

        return $this->redirect('index');
    }

    /**
     * Resolves the page of the request, the settings, the pages and the filter state.
     */
    private function createContext(?NewsConstraint $constraint): ModuleContext
    {
        $backendUser = $this->getBackendUser();
        $settings = $this->settingsProvider;
        $this->pageId = $this->resolvePageId($settings);

        $settings->applyPageTsConfig($this->pageId);
        $pages = $this->pageRepository->findAccessible($backendUser, NewsRepository::TABLE);
        $pageIds = $this->pageRepository->resolveRestriction($pages, $backendUser);

        $constraint ??= $this->getConstraintFromModuleData() ?? $this->createConstraintFromPreselect($settings);
        $constraint = $this->applyEnabledFilters($constraint, $settings);
        $constraint->setStoragePage($this->pageId);
        $constraint->setStoragePageIds(
            $this->pageTreeBuilder->collectIds($pages, $this->pageId)
        );

        $this->persistModuleData($constraint, $this->pageId);

        return new ModuleContext(
            pageId: $this->pageId,
            pages: $pages,
            pageIds: $pageIds,
            settings: $settings,
            constraint: $constraint,
        );
    }

    /**
     * The page the module works on: the page tree selection, limited by "allowedPage"
     * and falling back to "defaultPage".
     */
    private function resolvePageId(SettingsProvider $settings): int
    {
        $allowedPage = $settings->getInt('allowedPage');
        if ($allowedPage > 0) {
            return $allowedPage;
        }

        $pageId = (int)($this->request->getQueryParams()['id'] ?? $this->request->getParsedBody()['id'] ?? 0);
        if ($pageId > 0) {
            return $pageId;
        }

        $moduleData = $this->request->getAttribute('moduleData');
        $storedPageId = (int)$moduleData->get('pageId');
        if ($storedPageId > 0) {
            return $storedPageId;
        }

        return $settings->getInt('defaultPage');
    }

    /**
     * Redirects to a given page if "redirectToPageOnStart" is configured and the
     * editor has not selected a page yet.
     */
    private function applyRedirectOnStart(ModuleContext $context): ?ResponseInterface
    {
        $redirectToPage = $context->settings->getInt('redirectToPageOnStart');
        if ($redirectToPage <= 0 || $context->isPageRestricted()) {
            return null;
        }

        $moduleData = $this->request->getAttribute('moduleData');
        if ((int)$moduleData->get('pageId') === $redirectToPage) {
            // The editor was already redirected to that page, do not loop
            return null;
        }

        return new RedirectResponse($this->createModuleUri(['id' => $redirectToPage]));
    }

    /**
     * Filters which are switched off in the configuration are not applied.
     */
    private function applyEnabledFilters(NewsConstraint $constraint, SettingsProvider $settings): NewsConstraint
    {
        $preselect = $settings->getPreselect();

        if (!$settings->isFilterEnabled('searchWord')) {
            $constraint->setSearchWord(null);
        }
        if (!$settings->isFilterEnabled('timeRestriction')) {
            $constraint->setTimeRestriction('');
            $constraint->setManualDateStart('');
            $constraint->setManualDateStop('');
        }
        if (!$settings->isFilterEnabled('topNewsRestriction')) {
            $constraint->setTopNewsRestriction('');
        }
        if (!$settings->isFilterEnabled('archived')) {
            $constraint->setArchived('');
        }
        if (!$settings->isFilterEnabled('hidden')) {
            $constraint->setHidden('');
        }
        if (!$settings->isFilterEnabled('language')) {
            $constraint->setLanguage($this->intPreselect($preselect, 'language', NewsConstraint::LANGUAGE_ALL));
        }
        if (!$settings->isFilterEnabled('categories')) {
            $constraint->setCategories([]);
        }
        if (!$settings->isFilterEnabled('categoryConjunction')) {
            $constraint->setCategoryConjunction($this->stringPreselect($preselect, 'categoryConjunction', 'or'));
        }
        if (!$settings->isFilterEnabled('includeSubCategories')) {
            $constraint->setIncludeSubCategories($this->boolPreselect($preselect, 'includeSubCategories'));
        }
        if (!$settings->isFilterEnabled('recursive')) {
            $constraint->setRecursive($this->boolPreselect($preselect, 'recursive'));
        }

        return $constraint;
    }

    /**
     * @param array<string, mixed> $preselect
     */
    private function stringPreselect(array $preselect, string $key, string $default): string
    {
        $value = $preselect[$key] ?? $default;

        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @param array<string, mixed> $preselect
     */
    private function intPreselect(array $preselect, string $key, int $default): int
    {
        $value = $preselect[$key] ?? $default;

        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * @param array<string, mixed> $preselect
     */
    private function boolPreselect(array $preselect, string $key): bool
    {
        $value = $preselect[$key] ?? '0';
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower((string)$value), ['0', 'false', 'off', 'no', ''], true);
    }

    /**
     * The filter state of the editor or, if there is none yet, the configured defaults.
     */
    private function createConstraintFromPreselect(SettingsProvider $settings): NewsConstraint
    {
        $preselect = $settings->getPreselect();
        $constraint = new NewsConstraint();
        $constraint->setTimeRestriction($this->stringPreselect($preselect, 'timeRestriction', ''));
        $constraint->setTopNewsRestriction($this->stringPreselect($preselect, 'topNewsRestriction', ''));
        $constraint->setArchived($this->stringPreselect($preselect, 'archived', ''));
        $constraint->setHidden($this->stringPreselect($preselect, 'hidden', ''));
        $constraint->setLanguage($this->intPreselect($preselect, 'language', NewsConstraint::LANGUAGE_ALL));
        $constraint->setCategoryConjunction($this->stringPreselect($preselect, 'categoryConjunction', 'or'));
        $constraint->setIncludeSubCategories($this->boolPreselect($preselect, 'includeSubCategories'));
        $constraint->setRecursive($this->boolPreselect($preselect, 'recursive'));
        $constraint->setSortingField($this->stringPreselect($preselect, 'sortingField', 'datetime'));
        $constraint->setSortingDirection($this->stringPreselect($preselect, 'sortingDirection', 'desc'));
        $constraint->setPerPage($this->intPreselect($preselect, 'perPage', 20));

        return $constraint;
    }

    /**
     * Counts of the record types, shown above the record list.
     *
     * @param array<int, int> $newsCounts News records per storage page, already fetched for the page tree
     * @return array{news: int, categories: int, tags: int}
     */
    private function fetchCounts(ModuleContext $context, array $newsCounts): array
    {
        return [
            'news' => array_sum($newsCounts),
            'categories' => array_sum($this->newsRepository->countCategoriesByPage($context->pageIds)),
            'tags' => array_sum($this->newsRepository->countTagsByPage($context->pageIds)),
        ];
    }

    /**
     * The page tree of the module including the link and the state of every node.
     *
     * A node carries the number of the news records on the page and the number on the
     * page and all its subpages. The partial shows the first one on the page and the
     * second one only if it adds something, and a page without any record of its own
     * shows the number of its subpages alone, because a plain zero next to records
     * below it says nothing.
     *
     * The nodes carry the indent of their own list of children instead of an indent
     * for the row. The disclosure marker of a branch is drawn at the left edge of the
     * row and does not move with the padding of the row, so a row indent would leave
     * the marker of every level on the same line. A list which is indented carries
     * the marker of a level along with the whole level below it.
     *
     * Every row of the tree and the row of the filter panel reserve a box of the
     * width of a small icon before their title, which holds the marker of the partial
     * NewsBunny/DisclosureMarker. A page without children keeps the box empty, so the
     * titles of a level share one line. The marker of the browser is switched off on
     * the summaries: it is drawn in the box of the list item and takes the space of a
     * box that only the branches of a level would have, and in the filter panel it
     * ends up above the row, whose row starts with a heading.
     * The marker of the markup does not turn with the state of a branch, which keeps
     * the extension free of a stylesheet. The state is carried by the "details"
     * element itself and by the content appearing and disappearing.
     *
     * @param array<int, int> $newsCounts News records per storage page
     * @return array{show: bool, allPagesUrl: string, allPagesCurrent: bool, branches: array}
     */
    private function createPageTreeView(ModuleContext $context, array $newsCounts): array
    {
        $settings = $context->settings;
        $ancestors = $this->resolveAncestorIds($context);
        $pageTree = $this->pageTreeBuilder->build(
            $context->pages,
            $context->pageId,
            $newsCounts,
            hideEmptyPages: $settings->getBool('hideEmptyPages')
        );

        $decorate = function (array $branches, int $level) use (&$decorate, $ancestors): array {
            $result = [];
            foreach ($branches as $branch) {
                $uid = $branch['uid'];
                $result[$uid] = [
                    'uid' => $uid,
                    'title' => $branch['title'],
                    'path' => $branch['path'],
                    'hidden' => $branch['hidden'],
                    'current' => (bool)$branch['current'],
                    'expanded' => (bool)$branch['current'] || in_array($uid, $ancestors, true),
                    'newsCount' => $branch['newsCount'],
                    'newsTotal' => $branch['newsTotal'],
                    'hasNews' => $branch['newsTotal'] > 0,
                    'hasOwnNews' => $branch['newsCount'] > 0,
                    'hasSubTotal' => $branch['newsTotal'] > $branch['newsCount'],
                    // the indent of the list which holds the children of this page, the
                    // pages of the module tree itself start without one
                    'childIndent' => $level * 12,
                    'url' => $this->createModuleUri(['id' => $uid]),
                    'children' => $decorate($branch['children'], $level + 1),
                ];
            }

            return $result;
        };

        return [
            'show' => !$settings->getBool('hidePageTree') && $settings->getInt('allowedPage') <= 0,
            'allPagesUrl' => $this->createModuleUri(['id' => 0]),
            'allPagesCurrent' => $context->pageId === 0,
            'branches' => $decorate($pageTree, 1),
        ];
    }

    /**
     * Ids of the parents of the current page, used to fold the tree open. A parent
     * is resolved to the page it stands for in the tree, because a record may point
     * to a translated record of its parent page.
     *
     * @return int[]
     */
    private function resolveAncestorIds(ModuleContext $context): array
    {
        $ancestors = [];
        $pageId = $context->pageId;
        // guard against loops in a broken page tree
        for ($level = 0; $level < 64 && $pageId > 0; $level++) {
            $parentUid = (int)($context->pages[$pageId]['pid'] ?? 0);
            if ($parentUid === 0) {
                break;
            }
            $pageId = $this->resolveTreePageId($context->pages, $parentUid);
            $ancestors[] = $pageId;
        }

        return $ancestors;
    }

    /**
     * The record of the default language of a page, which is the one the module
     * tree shows and links to.
     *
     * @param array<int, array<string, mixed>> $pages
     */
    private function resolveTreePageId(array $pages, int $pageId): int
    {
        $parentUid = (int)($pages[$pageId]['l10nParent'] ?? 0);
        if ((int)($pages[$pageId]['language'] ?? 0) === 0 || $parentUid === 0) {
            return $pageId;
        }

        return isset($pages[$parentUid]) ? $parentUid : $pageId;
    }

    /**
     * The current filter state as strings, used to fill the filter form. The values are
     * passed to the form fields directly, because Fluid compares the selected option and
     * the value of a text field as strings and falls back to the submitted data, which is
     * empty if the filter was set through a link instead of the form.
     *
     * @return array<string, string|string[]>
     */
    private function createFormValues(NewsConstraint $constraint): array
    {
        return [
            'searchWord' => (string)$constraint->getSearchWord(),
            'manualDateStart' => $constraint->getManualDateStart(),
            'manualDateStop' => $constraint->getManualDateStop(),
            'timeRestriction' => $constraint->getTimeRestriction(),
            'topNewsRestriction' => $constraint->getTopNewsRestriction(),
            'archived' => $constraint->getArchived(),
            'hidden' => $constraint->getHidden(),
            'language' => (string)$constraint->getLanguage(),
            'perPage' => (string)$constraint->getPerPage(),
            'categoryConjunction' => $constraint->getCategoryConjunction(),
            'categories' => array_map('strval', $constraint->getCategories()),
        ];
    }

    /**
     * The configured root categories of the category filter, null if all categories are allowed.
     *
     * @return int[]|null
     */
    private function resolveCategoryRoots(ModuleContext $context): ?array
    {
        $configuredRoots = $context->settings->getIntList('allowedCategoryRootIds');

        return $configuredRoots === [] ? null : $configuredRoots;
    }

    /**
     * @return array<string, bool>
     */
    private function createFilterMap(SettingsProvider $settings): array
    {
        $map = [];
        foreach (['searchWord', 'timeRestriction', 'topNewsRestriction', 'archived', 'hidden', 'language', 'categories', 'categoryConjunction', 'includeSubCategories', 'recursive'] as $filter) {
            $map[$filter] = $settings->isFilterEnabled($filter);
        }

        return $map;
    }

    /**
     * Adds the button to switch to the storage page listing and, if filters are
     * active, a button to reset them.
     */
    private function addButtonBarButtons(ModuleTemplate $moduleTemplate, ModuleContext $context): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        foreach ($this->createRecordUrls($this->resolveCreatePage($context)) as $type => $url) {
            $button = $buttonBar->makeLinkButton()
                ->setHref($url)
                ->setTitle($this->getLabel('create.' . $type))
                ->setIcon($this->iconFactory->getIcon('actions-' . ($type === 'news' ? 'document-new' : 'plus'), IconSize::SMALL))
                ->setShowLabelText(false);
            $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT, 10);
        }

        $storagePagesButton = $buttonBar->makeLinkButton()
            ->setHref($this->createModuleUri(['action' => 'storagePages']))
            ->setTitle($this->getLabel('storagePages.title'))
            ->setIcon($this->iconFactory->getIcon('module-newsbunny-storage-pages', IconSize::SMALL))
            ->setShowLabelText(true);
        $buttonBar->addButton($storagePagesButton, ButtonBar::BUTTON_POSITION_RIGHT, 10);

        if ($context->constraint->hasFilters()) {
            $resetButton = $buttonBar->makeLinkButton()
                ->setHref($this->createResetUri($context->constraint, $context))
                ->setTitle($this->getLabel('filter.reset'))
                ->setIcon($this->iconFactory->getIcon('actions-refresh', IconSize::SMALL))
                ->setShowLabelText(true);
            $buttonBar->addButton($resetButton, ButtonBar::BUTTON_POSITION_RIGHT, 5);
        }
    }

    private function prepareModuleTemplate(ModuleTemplate $moduleTemplate): void
    {
        $docHeader = $moduleTemplate->getDocHeaderComponent();
        $title = $this->getLabel('module.title', [], 'locallang_mod.xlf');

        if (method_exists($docHeader, 'setShortcutContext')) {
            // TYPO3 v14 and later render the shortcut button as part of the doc header
            $docHeader->setShortcutContext(self::MODULE_IDENTIFIER, $title);
            return;
        }

        // TYPO3 v13 has no automatic shortcut button, so it is added explicitly
        $buttonBar = $docHeader->getButtonBar();
        $buttonBar->addButton(
            $buttonBar->makeShortcutButton()
                ->setRouteIdentifier(self::MODULE_IDENTIFIER)
                ->setDisplayName($title),
            ButtonBar::BUTTON_POSITION_RIGHT,
            5
        );
    }

    /**
     * The page new records are created on: the selected page, the configured
     * default pid or, as a fallback, the first page the editor may use.
     */
    private function resolveCreatePage(ModuleContext $context): ?array
    {
        $settings = $context->settings;
        $candidates = [];
        if ($context->isPageRestricted() && isset($context->pages[$context->pageId])) {
            $candidates[] = $context->pages[$context->pageId];
        }
        foreach (self::CREATEABLE_TABLES as $table => $type) {
            $defaultPid = $settings->getDefaultPid($table);
            if ($defaultPid > 0 && isset($context->pages[$defaultPid])) {
                $candidates[] = $context->pages[$defaultPid];
            }
        }
        foreach ($context->pages as $page) {
            $candidates[] = $page;
        }

        foreach ($candidates as $page) {
            if ($page['creatable']) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Links to create a new record of every createable table on the given page.
     *
     * @param array<string, mixed>|null $page
     * @return array<string, string>
     */
    private function createRecordUrls(?array $page): array
    {
        if ($page === null) {
            return [];
        }
        $urls = [];
        $backendUser = $this->getBackendUser();
        foreach (self::CREATEABLE_TABLES as $table => $identifier) {
            if ($backendUser->check('tables_modify', $table)) {
                $urls[$identifier] = $this->createModuleUri([
                    'action' => 'createRecord',
                    'table' => $table,
                    'pid' => $page['uid'],
                ]);
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function prepareRecord(array $row, ModuleContext $context): array
    {
        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp');
        $page = $context->pages[(int)$row['pid']] ?? null;
        $editable = $page !== null && $page['editable'];

        return [
            'uid' => (int)$row['uid'],
            'pid' => (int)$row['pid'],
            'title' => (string)$row['title'],
            'teaser' => $this->createTeaser((string)$row['teaser']),
            'author' => (string)$row['author'],
            'pathSegment' => (string)$row['path_segment'],
            'datetime' => (int)$row['datetime'],
            'archive' => (int)$row['archive'],
            'crdate' => (int)$row['crdate'],
            'tstamp' => (int)$row['tstamp'],
            'hidden' => (int)$row['hidden'],
            'sysLanguageUid' => (int)$row['sys_language_uid'],
            'isTopNews' => (bool)$row['istopnews'],
            'isHidden' => (bool)$row['hidden'],
            'isArchived' => (int)$row['archive'] > 0 && (int)$row['archive'] < $now,
            'isTimeRestricted' => (int)$row['starttime'] > 0 || (int)$row['endtime'] > 0,
            'typeIconIdentifier' => $this->getTypeIconIdentifier((string)$row['type']),
            'categories' => [],
            'tags' => [],
            'tagsList' => '',
            'page' => $page,
            'editable' => $editable,
            'editUrl' => $editable ? $this->createRecordEditUri(NewsRepository::TABLE, (int)$row['uid']) : '',
            'deleteUrl' => $editable ? $this->createModuleUri(['action' => 'confirmDelete', 'uid' => (int)$row['uid']]) : '',
            'toggleUrl' => $editable ? $this->createModuleUri(['action' => 'toggleRecord', 'uid' => (int)$row['uid']]) : '',
            'moveUpUrl' => $editable ? $this->createModuleUri(['action' => 'moveRecord', 'uid' => (int)$row['uid'], 'direction' => 'up']) : '',
            'moveDownUrl' => $editable ? $this->createModuleUri(['action' => 'moveRecord', 'uid' => (int)$row['uid'], 'direction' => 'down']) : '',
            'l10nParent' => (int)($row['l10n_parent'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRecord(int $uid, ModuleContext $context): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(NewsRepository::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select('uid', 'pid', 'title', 'hidden')
            ->from(NewsRepository::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($record === false) {
            $this->addFlashMessage(
                $this->getLabel('error.recordNotFound', [$uid]),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::WARNING
            );

            return null;
        }

        $page = $context->pages[(int)$record['pid']] ?? null;
        if ($page === null || !$page['editable'] || !$this->getBackendUser()->check('tables_modify', NewsRepository::TABLE)) {
            $this->addFlashMessage(
                $this->getLabel('error.noEditPermissionOnPage', [$page['path'] ?? (string)$record['pid']]),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::WARNING
            );

            return null;
        }

        return [
            'uid' => (int)$record['uid'],
            'pid' => (int)$record['pid'],
            'title' => (string)$record['title'],
            'hidden' => (int)$record['hidden'],
            'page' => $page,
        ];
    }

    /**
     * Is the manual sorting of EXT:news enabled? Records can only be reordered if the
     * news TCA defines a "sortby" field, which is controlled by the extension setting
     * "manualSorting" of EXT:news.
     */
    private function isRecordTableSortingAware(): bool
    {
        $schema = $this->tcaSchemaFactory->get(NewsRepository::TABLE);

        return $schema->hasCapability(TcaSchemaCapability::SortByField);
    }

    private function getSortingField(): string
    {
        $schema = $this->tcaSchemaFactory->get(NewsRepository::TABLE);

        return $schema->getCapability(TcaSchemaCapability::SortByField)->getFieldName();
    }

    /**
     * The teaser is only shown as a short hint in the list, so it is cut and stripped.
     */
    private function createTeaser(string $teaser): string
    {
        $teaser = trim(preg_replace('/\s+/', ' ', strip_tags($teaser)) ?? '');
        if ($teaser === '') {
            return '';
        }

        return mb_strlen($teaser) > 200 ? mb_substr($teaser, 0, 200) . '…' : $teaser;
    }

    private function getTypeIconIdentifier(string $type): string
    {
        return match ($type) {
            '1' => 'ext-news-type-internal',
            '2' => 'ext-news-type-external',
            default => 'ext-news-type-default',
        };
    }

    private function addFlashMessagesFromDataHandler(DataHandler $dataHandler, string $successMessage): void
    {
        if ($dataHandler->errorLog !== []) {
            $this->addFlashMessage(
                implode('<br>', array_map(
                    static fn(string|array $error): string => is_array($error) ? (string)($error['log'] ?? '') : $error,
                    $dataHandler->errorLog
                )),
                $this->getLabel('error.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return;
        }

        $this->addFlashMessage($successMessage);
    }

    /**
     * @return array<string, string>
     */
    private function createSortingLinks(NewsConstraint $constraint, ModuleContext $context): array
    {
        $links = [];
        foreach ($this->getSortableFields() as $field) {
            $isSortedBy = $constraint->getSortingField() === $field;
            $direction = $isSortedBy && $constraint->getSortingDirection() === 'asc' ? 'desc' : 'asc';
            $links[$field] = $this->createModuleUri([
                'constraint' => $constraint->withValues([
                    'sortingField' => $field,
                    'sortingDirection' => $direction,
                    'page' => 1,
                ])->toQueryParameters(),
            ]);
        }

        return $links;
    }

    /**
     * Icon of every sortable column, showing the current sorting direction.
     *
     * @return array<string, string>
     */
    private function createSortingIcons(NewsConstraint $constraint): array
    {
        $icons = [];
        foreach ($this->getSortableFields() as $field) {
            if ($constraint->getSortingField() !== $field) {
                $icons[$field] = 'actions-sort-amount';
                continue;
            }
            $icons[$field] = $constraint->getSortingDirection() === 'asc'
                ? 'actions-sort-amount-up'
                : 'actions-sort-amount-down';
        }

        return $icons;
    }

    /**
     * @return string[]
     */
    private function getSortableFields(): array
    {
        $fields = NewsConstraint::SORTABLE_FIELDS;
        if (!$this->isRecordTableSortingAware()) {
            // The manual sorting field does not exist without the news setting "manualSorting"
            return array_values(array_diff($fields, ['sorting']));
        }

        return $fields;
    }

    /**
     * @return array{
     *     hasPrevious: bool,
     *     hasNext: bool,
     *     previous: string,
     *     next: string,
     *     first: string,
     *     last: string,
     *     numbers: array<int, array{label: int, url: string, current: bool}>
     * }
     */
    private function createPagination(NewsConstraint $constraint, int $pageCount): array
    {
        $numbers = [];
        $first = max(1, $constraint->getPage() - 2);
        $last = min($pageCount, max($constraint->getPage() + 2, 5));
        for ($number = $first; $number <= $last; $number++) {
            $numbers[$number] = [
                'label' => $number,
                'url' => $this->createPageUri($constraint, $number),
                'current' => $number === $constraint->getPage(),
            ];
        }

        return [
            'hasPrevious' => $constraint->getPage() > 1,
            'hasNext' => $constraint->getPage() < $pageCount,
            'previous' => $this->createPageUri($constraint, max(1, $constraint->getPage() - 1)),
            'next' => $this->createPageUri($constraint, min($pageCount, $constraint->getPage() + 1)),
            'first' => $this->createPageUri($constraint, 1),
            'last' => $this->createPageUri($constraint, $pageCount),
            'numbers' => $numbers,
        ];
    }

    private function createPageUri(NewsConstraint $constraint, int $page): string
    {
        return $this->createModuleUri([
            'constraint' => $constraint->withValues(['page' => $page])->toQueryParameters(),
        ]);
    }

    /**
     * Link which clears all filters but keeps sorting, page size and page context.
     */
    private function createResetUri(NewsConstraint $constraint, ModuleContext $context): string
    {
        return $this->createModuleUri([
            'constraint' => [
                'perPage' => $constraint->getPerPage(),
                'sortingField' => $constraint->getSortingField(),
                'sortingDirection' => $constraint->getSortingDirection(),
                'recursive' => $this->boolPreselect($context->settings->getPreselect(), 'recursive') ? 1 : 0,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createModuleUri(array $parameters = []): string
    {
        $module = $this->request->getAttribute('module');
        if ($this->pageId > 0 && !isset($parameters['id'])) {
            $parameters['id'] = $this->pageId;
        }

        return (string)$this->moduleUriBuilder->buildUriFromRoute(
            $module instanceof ModuleInterface ? $module->getIdentifier() : self::MODULE_IDENTIFIER,
            $parameters
        );
    }

    private function createRecordEditUri(string $table, int $uid, bool $createNew = false): string
    {
        $parameters = [
            'edit' => [$table => [$uid => $createNew ? 'new' : 'edit']],
            'module' => self::MODULE_IDENTIFIER,
        ];
        if (!$createNew) {
            $parameters['returnUrl'] = $this->createModuleUri();
        }

        return (string)$this->moduleUriBuilder->buildUriFromRoute('record_edit', $parameters);
    }

    /**
     * The stored filter state of the module, used if the editor does not submit filters.
     */
    private function getConstraintFromModuleData(): ?NewsConstraint
    {
        $serialized = $this->request->getAttribute('moduleData')->get('constraint');
        if (!is_string($serialized) || $serialized === '') {
            return null;
        }
        $constraint = @unserialize($serialized, ['allowed_classes' => [NewsConstraint::class]]);

        return $constraint instanceof NewsConstraint ? $constraint : null;
    }

    private function persistModuleData(NewsConstraint $constraint, int $pageId): void
    {
        $moduleData = $this->request->getAttribute('moduleData');
        $moduleData->set('constraint', serialize($constraint));
        $moduleData->set('pageId', $pageId);
        $this->getBackendUser()->pushModuleData(
            $moduleData->getModuleIdentifier(),
            $moduleData->toArray()
        );
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @param array<int, string|int> $arguments
     */
    private function getLabel(string $key, array $arguments = [], string $file = 'locallang.xlf'): string
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $label = $languageService->sL('LLL:EXT:news_bunny/Resources/Private/Language/' . $file . ':' . $key);
        if ($arguments !== []) {
            return vsprintf($label, $arguments);
        }

        return $label;
    }
}
