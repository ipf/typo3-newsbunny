..  _installation:

============
Installation
============

Composer
========

Install the extension with Composer:

.. code-block:: bash

    composer require ipf/news_bunny

The extension requires TYPO3 CMS 13.4 or 14.3 and EXT:news 13.0 or 14.1, both are
pulled in as dependencies if they are not installed yet.

In a project which uses a local path repository, add the directory of the extension
to the repositories of the root :file:`composer.json` and require it as a
development version:

.. code-block:: json

    {
        "repositories": [
            {
                "type": "path",
                "url": "./packages/news_bunny"
            }
        ],
        "require": {
            "ipf/news_bunny": "@dev"
        }
    }

Setup
=====

The extension has no database tables, no TypoScript and no class aliases, so there
is nothing to do in the Extension Manager beyond activating the module for the
editors who should see it:

#. Go to :guilabel:`Administration > Modules`.
#. Find :guilabel:`NewsBunny` and make the module available for the
   :guilabel:`Administrators` and :guilabel:`Editors` groups.

The columns, filters and the page context of the module are configured per page, see
:ref:`Settings <settings>`.

Related settings of other extensions
===================================

EXT:news registers a backend module of its own for news records, which is enabled
by default. Editors should only work with one of the two modules, so the module of
EXT:news can be switched off with its extension setting:

.. code-block:: php

    // config/system/settings.php
    $GLOBALS['TYPO3_CONF_VARS]['EXTENSIONS']['news']['showAdministrationModule'] = '0';

The sort buttons of the control panels need the manual sorting of EXT:news, which is
an extension setting as well:

.. code-block:: php

    $GLOBALS['TYPO3_CONF_VARS]['EXTENSIONS']['news']['manualSorting'] = '1';

Extension settings are read while the dependency injection container is compiled.
Flush all caches after changing them, for example with:

.. code-block:: bash

    vendor/bin/typo3 cache:flush
