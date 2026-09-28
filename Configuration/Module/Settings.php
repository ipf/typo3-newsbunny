<?php

declare(strict_types=1);

return [
    // Columns of the record list. Available: title, teaser, datetime, archive, categories, tags,
    // author, path_segment, status, language, page, uid, crdate, tstamp
    'columns' => 'teaser,datetime,categories,status',

    // Page tree of the module, see "defaultPage" for the alternative
    'hidePageTree' => '0',

    // Limit the page tree to the pages which hold news records, on the page itself
    // or on one of its subpages
    'hideEmptyPages' => '1',

    // Page the module starts with, if no page is selected in the page tree
    'defaultPage' => '0',

    // Limit the module to one page, the page tree is not needed then
    'allowedPage' => '0',

    // Redirect to this page if the editor has not selected a page
    'redirectToPageOnStart' => '0',

    // Pid of new records, if the record is not created on the page selected in the page tree
    'defaultPid' => [
        'tx_news_domain_model_news' => '0',
        'sys_category' => '0',
        'tx_news_domain_model_tag' => '0',
    ],

    // Additional column with the language of a record
    'localizationView' => '1',

    // Show the control panels to toggle, sort and delete records
    'controlPanels' => '1',

    // Start the filter form expanded, it is folded away otherwise and opens by itself
    // as soon as a filter narrows the record list down
    'alwaysShowFilter' => '0',

    // Limit the categories of the filter to these root categories (comma separated)
    'allowedCategoryRootIds' => '',

    // Default filter and sorting values, used as long as the editor did not change them
    'preselect' => [
        'timeRestriction' => '',
        'topNewsRestriction' => '',
        'archived' => '',
        'hidden' => '',
        'language' => '-1',
        'categoryConjunction' => 'or',
        'includeSubCategories' => '0',
        'recursive' => '0',
        'sortingField' => 'datetime',
        'sortingDirection' => 'desc',
        'perPage' => '20',
    ],

    // Enable or disable single filters of the filter form
    'filters' => [
        'searchWord' => '1',
        'timeRestriction' => '1',
        'topNewsRestriction' => '1',
        'archived' => '1',
        'hidden' => '1',
        'language' => '1',
        'categories' => '1',
        'categoryConjunction' => '1',
        'includeSubCategories' => '1',
        'recursive' => '1',
    ],
];
