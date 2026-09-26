# NewsBunny

[![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-ff8700.svg)](https://typo3.org)
[![Tests](https://github.com/ipf/typo3-newsbunny/actions/workflows/tests.yml/badge.svg)](https://github.com/ipf/typo3-newsbunny/actions/workflows/tests.yml)
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

- TYPO3 CMS 13.4 or 14.3
- EXT:news 13.0 or 14.1

Both TYPO3 lines and both EXT:news lines are covered by the test suite, see
`Tests` below.

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

## Tests

The test suite uses [TYPO3 testing-framework](https://github.com/TYPO3/testing-framework).
Unit tests run without a database, functional tests boot a throwaway TYPO3 instance
with an SQLite database.

```bash
composer install
composer test:fixtures      # checks the structure of the CSV fixtures
composer test:unit          # no database
composer test:functional    # creates a temporary SQLite instance
composer test               # all of the above
```

`composer install` treats this repository as a standalone project, so TYPO3 itself
is installed into `vendor/`. The generated `public/`, `var/`, `vendor/` and
`composer.lock` are ignored by git.

To run a single test:

```bash
vendor/bin/phpunit -c Build/phpunit/UnitTests.xml --filter NewsConstraintTest
vendor/bin/phpunit -c Build/phpunit/FunctionalTests.xml --filter NewsRepositoryTest
```

The functional tests load their data from the CSV files in `Tests/Functional/Fixtures`.
`composer test:fixtures` verifies their structure and the `FixtureSchemaTest` verifies
that every column they use exists, which catches a broken fixture long before the
importer fails with an unrelated error.

Both suites also run on every push and pull request via GitHub Actions, see
`.github/workflows/tests.yml`.

## License

GNU General Public License v2.0 or later, see [LICENSE](LICENSE).
