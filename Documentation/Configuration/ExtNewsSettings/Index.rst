..  _ext-news-settings:

=================
EXT:news settings
=================

NewsBunny uses some extension settings of EXT:news. They are set in the Extension
Manager of the backend, or in :file:`config/system/settings.php` of the project.

..  confval-menu::
    :name: ext-news-settings
    :caption: Settings
    :display: table
    :type:

..  confval:: manualSorting
   :type: bool
   :Default: 0

   Adds a manual sorting field to the news table. The sort buttons of the control
   panels move a record with this field, without it the buttons are not rendered.

   .. code-block:: php

      // config/system/settings.php
      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['manualSorting'] = '1';

..  confval:: showAdministrationModule
   :type: bool
   :Default: 1

   EXT:news registers a backend module for news records of its own. Switch it off if
   the editors should only work with NewsBunny.

   .. code-block:: php

      // config/system/settings.php
      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['news']['showAdministrationModule'] = '0';

..  note::

   Extension settings are read while the dependency injection container is compiled.
   Flush all caches after changing them, see :ref:`installation`.
