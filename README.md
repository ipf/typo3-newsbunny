# NewsBunny

[![TYPO3](https://img.shields.io/badge/TYPO3-14.3-ff8700.svg)](https://typo3.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

Backend module for [EXT:news](https://extensions.typo3.org/extension/news) that gives
editors a dedicated working area for the news records of a site.

## Features

- Own page tree, optionally including subpages or limited to a single page
- Record list with configurable columns, sorting and pagination
- Filters for full text, time range, top news, archive state, visibility, language
  and categories
- Control panels to show, hide, move and delete records
- Listing of all pages which contain news, category or tag records
- Shortcuts to create news, category and tag records
- Per page configuration in the page TSconfig

## Requirements

- TYPO3 CMS 14.3 or later
- EXT:news 14.1 or later

## Installation

```bash
composer require ipf/news_bunny
```

The module is registered as `web_newsBunny` below the :guilabel:`Web` module. Make it
available for the relevant user groups in :guilabel:`Administration > Modules`, see
`Documentation/Installation/Index.rst`.

## Documentation

The full documentation is written in reStructuredText and follows the TYPO3
documentation conventions, see `Documentation/Index.rst`:

- `Documentation/Introduction/Index.rst` – features and permissions
- `Documentation/Installation/Index.rst` – installation and setup
- `Documentation/Configuration/Index.rst` – all settings, per page in the page TSconfig
- `Documentation/Usage/Index.rst` – manual for editors
- `Documentation/Reference/Index.rst` – module actions, tables and files

Render it with [docs.typo3.org](https://docs.typo3.org) or with a local Sphinx
installation, for example:

```bash
pip install -r requirements.txt
sphinx-build -b html Documentation Documentation/_build/html
```

## License

GNU General Public License v2.0 or later, see [LICENSE](LICENSE).
