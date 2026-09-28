..  _settings:

========
Settings
========

All settings are read per page, so the settings of the page which is currently
selected in the module are applied. They are written in the page TSconfig of that
page with the path :file:`tx_newsbunny.module`.

..  confval-menu::
    :name: settings
    :caption: Settings
    :display: table
    :type:

Page context
============

..  confval:: hidePageTree
   :type: bool
   :Path: tx_newsbunny > module
   :Default: 0

   Hide the page tree of the module. Together with :ref:`defaultPage` this is the
   fastest way to let editors work with the records of a single page.

   .. code-block:: typoscript

      tx_newsbunny.module.hidePageTree = 1

..  confval:: hideEmptyPages
   :type: bool
   :Path: tx_newsbunny > module
   :Default: 1

   Limit the page tree to the pages which hold news records. A page is kept if it
   holds records itself or if one of its subpages does, because a page that only
   leads to a page with records is the way down to it.

   The page the editor has selected and the pages leading to it are always shown,
   also without records, so the editor can see where the record list is empty.

   .. code-block:: typoscript

      tx_newsbunny.module.hideEmptyPages = 0

..  confval:: defaultPage
   :type: int
   :Path: tx_newsbunny > module
   :Default: 0

   Page the module starts with if no page is selected in the page tree.

   .. code-block:: typoscript

      tx_newsbunny.module.defaultPage = 123

..  confval:: allowedPage
   :type: int
   :Path: tx_newsbunny > module
   :Default: 0

   Limit the module to one page. The page tree is not rendered in that case and the
   module always works on the records of that page.

   .. code-block:: typoscript

      tx_newsbunny.module.allowedPage = 123

..  confval:: redirectToPageOnStart
   :type: int
   :Path: tx_newsbunny > module
   :Default: 0

   Redirect the editor to that page if no page is selected yet.

   .. code-block:: typoscript

      tx_newsbunny.module.redirectToPageOnStart = 123

..  confval:: defaultPid
   :type: array
   :Path: tx_newsbunny > module

   Pid of new records, used if a record is not created on the page which is selected
   in the page tree.

   .. code-block:: typoscript

      tx_newsbunny.module.defaultPid {
          tx_news_domain_model_news = 123
          sys_category = 124
          tx_news_domain_model_tag = 125
      }

Record list
===========

..  confval:: columns
   :type: string
   :Path: tx_newsbunny > module
   :Default: teaser,datetime,categories,status

   Comma separated list of the columns of the record list. The title is always the
   first column. Available columns:

   ``title``, ``teaser``, ``datetime``, ``archive``, ``categories``, ``tags``,
   ``author``, ``path_segment``, ``status``, ``language``, ``page``, ``uid``,
   ``crdate``, ``tstamp``

   .. code-block:: typoscript

      tx_newsbunny.module.columns = teaser,datetime,categories,status

..  confval:: localizationView
   :type: bool
   :Path: tx_newsbunny > module
   :Default: 1

   Show the language of a record as an additional column.

   .. code-block:: typoscript

      tx_newsbunny.module.localizationView = 1

..  confval:: allowedCategoryRootIds
   :type: string, comma separated list of integers
   :Path: tx_newsbunny > module
   :Default:

   Limit the categories of the filter to these root categories and their children.

   .. code-block:: typoscript

      tx_newsbunny.module.allowedCategoryRootIds = 12,15

   Example: the category tree below is reduced to the roots 12 and 15.

   .. code-block:: none

      Category tree

      ├── [10] Cat 1
      ├── [12] Cat 2
      │   └── [13] Cat 2 b
      ├── [14] Cat 3
      └── [15] Cat 4

      Categories of the filter

      ├── [12] Cat 2
      │   └── [13] Cat 2 b
      └── [15] Cat 4

Control panels
==============

..  confval:: controlPanels
   :type: bool
   :Path: tx_newsbunny > module
   :Default: 1

   Show the buttons to toggle, sort and delete records in the record list. The sort
   buttons need a manual sorting field, see :ref:`manualSorting <ext-news-settings>`.

   .. code-block:: typoscript

      tx_newsbunny.module.controlPanels = 1

Filters
=======

..  confval:: alwaysShowFilter
   :type: bool
   :Path: tx_newsbunny > module
   :Default: 0

   Start the filter form expanded. The form can be folded away either way, the
   setting only decides how the module opens it.

   The form is opened by itself as soon as a filter of the form is set, so a
   narrowed down record list is never hidden behind a closed form. A selected page
   is not a filter of the form, it is shown as selected in the page tree.

   .. code-block:: typoscript

      tx_newsbunny.module.alwaysShowFilter = 1

..  confval:: preselect
   :type: array
   :Path: tx_newsbunny > module

   Default values of the filter and of the sorting. They are used as long as the
   editor has not changed them, afterwards the module remembers the state of the
   editor in the module data.

   .. code-block:: typoscript

      tx_newsbunny.module.preselect {
          timeRestriction = year
          topNewsRestriction =
          archived =
          hidden =
          language = -1
          categoryConjunction = or
          includeSubCategories = 0
          recursive = 0
          sortingField = datetime
          sortingDirection = desc
          perPage = 20
      }

   The available values are

   ``timeRestriction``: ````, ``day``, ``month``, ``year``

   ``topNewsRestriction``, ``archived``, ``hidden``: ```` for all,
   ``1`` and ``2`` for the two states of the attribute

   ``language``: ``-1`` for all languages, ``0`` for the default language, otherwise
   the uid of a site language

   ``categoryConjunction``: ``or``, ``and``, ``notor``, ``notand``

   ``includeSubCategories``, ``recursive``: ``0`` or ``1``

   ``sortingDirection``: ``asc`` or ``desc``

..  confval:: filters
   :type: array
   :Path: tx_newsbunny > module

   Enable or disable single filters of the filter form. A filter which is switched
   off is not rendered and is not applied to the record list. All filters are enabled
   by default.

   .. code-block:: typoscript

      tx_newsbunny.module.filters {
          searchWord = 1
          timeRestriction = 1
          topNewsRestriction = 1
          archived = 1
          hidden = 1
          language = 1
          categories = 1
          categoryConjunction = 1
          includeSubCategories = 1
          recursive = 1
      }

   ..  note::

      ``categoryConjunction`` and ``includeSubCategories`` only have an effect if
      ``categories`` is enabled.
