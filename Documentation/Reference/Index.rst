..  _reference:

=========
Reference
=========

Module
======

..  rst-class:: summary

:Module identifier:
   web_newsBunny

:Route:
   /module/web/NewsBunny/

:Extension name:
   NewsBunny

:Label file:
   :file:`Resources/Private/Language/locallang_mod.xlf`

The module is registered in
:file:`Configuration/Backend/Modules.php` and offers the following actions:

..  list-table::
   :header-rows: 1
   :widths: 25 25 50

   * - Action
     - HTTP method
     - Description
   * - index
     - GET
     - The record list, the default action of the module
   * - storagePages
     - GET
     - The listing of the pages which contain records
   * - createRecord
     - GET
     - Redirects to the FormEngine to create a record of
       :file:`tx_news_domain_model_news`, :file:`sys_category` or
       :file:`tx_news_domain_model_tag`
   * - confirmDelete
     - GET
     - The confirmation page of the deletion of a record
   * - deleteRecord
     - POST
     - Deletes a news record with the DataHandler
   * - toggleRecord
     - POST
     - Sets the field :file:`hidden` of a news record
   * - moveRecord
     - POST
     - Moves a news record within its storage page with the DataHandler

Data
====

The module reads the following tables:

..  list-table::
   :header-rows: 1
   :widths: 40 60

   * - Table
     - Used for
   * - :file:`tx_news_domain_model_news`
     - The records of the list
   * - :file:`sys_category`
     - The category filter and the categories of a record
   * - :file:`tx_news_domain_model_tag`
     - The tags of a record
   * - :file:`sys_category_record_mm`
     - The relation of a record to its categories
   * - :file:`tx_news_domain_model_news_tag_mm`
     - The relation of a record to its tags
   * - :file:`pages`
     - The page tree, the storage pages and the permissions of the editor

Records are written with the DataHandler, the module does not modify the database on
its own.

Files
=====

..  list-table::
   :header-rows: 1
   :widths: 45 55

   * - File
     - Content
   * - :file:`Configuration/Backend/Modules.php`
     - Registration of the backend module
   * - :file:`Configuration/Module/Settings.php`
     - Default values of the settings
   * - :file:`Configuration/page.tsconfig`
     - The defaults as page TSconfig
   * - :file:`Configuration/Icons.php`
     - The registration of the icons, with the version condition for the icon language
   * - :file:`Configuration/Services.yaml`
     - Registration of the services
   * - :file:`Classes/Controller/NewsBunnyController.php`
     - The actions of the module
   * - :file:`Classes/Domain/Model/NewsConstraint.php`
     - The filter, sorting and pagination state with the validation of all values
   * - :file:`Classes/Domain/Model/ModuleContext.php`
     - The page, the pages and the settings of a request
   * - :file:`Classes/Repository/NewsRepository.php`
     - The queries for the records, categories and tags
   * - :file:`Classes/Repository/PageRepository.php`
     - The pages, their language variants and the permissions of the editor
   * - :file:`Classes/Repository/SortingRepository.php`
     - The neighbours of a record for the sort buttons
   * - :file:`Classes/Service/PageTreeBuilder.php`
     - The page tree with the number of records, and the pages of a page subtree
   * - :file:`Classes/Service/SettingsProvider.php`
     - The settings, merged from the defaults and the page TSconfig, and the widths of the
       columns of the record list
   * - :file:`Resources/Private/Language/locallang.xlf`
     - The labels of the module

Icons
=====

The two supported Core versions draw the icons of the backend in two different languages,
so the module has one file per language and a condition in
:file:`Configuration/Icons.php` that picks the right one. The identifiers stay the same in
both branches, so the module registration and the button of the controller do not have to
know which Core version is running.

..  list-table::
   :header-rows: 1
   :widths: 20 40 40

   * - Core version
     - Module icon
     - Icon of the storage page button
   * - TYPO3 14
     - :file:`Resources/Public/Icons/module-newsbunny.svg`
     - :file:`Resources/Public/Icons/module-newsbunny-storage-pages.svg`
   * - TYPO3 13
     - :file:`Resources/Public/Icons/v13/module-newsbunny.svg`
     - :file:`Resources/Public/Icons/v13/module-newsbunny-storage-pages.svg`

TYPO3 14 draws the module icons as line art: no background rectangle, the silhouette in
``currentColor`` so it follows the text color of the theme, and the accent from the CSS
token ``icon-color-accent``. One file is therefore correct in the light and in the dark
color scheme. TYPO3 13 draws them as a white symbol on a full-bleed box of a fixed color,
the way its own module icons are drawn, and a v14 icon would land on the neutral tile of
the module menu instead of reading as a module icon.

The icon of the storage page view is not drawn for the module menu but for a button of the
doc header, which is 16px, so its v13 file is a small monochrome icon without a box.
