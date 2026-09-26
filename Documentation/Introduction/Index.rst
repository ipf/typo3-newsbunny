..  _introduction:

============
Introduction
============

NewsBunny adds a backend module to the backend of a site that uses EXT:news. The
module is registered below :guilabel:`Web` and shows up as :guilabel:`NewsBunny`.

Working with a large number of news records in the page module has two drawbacks:
The page module shows the records of one page at a time, and the available filters
depend on the fields of the content elements. The module of NewsBunny addresses
both: it shows the records of a whole page subtree, offers a full text search and
filters for the attributes of EXT:news, and provides control panels for the
operations that are done most often on a list of records.

Features
========

Page context
------------

The module contains its own page tree. Selecting a page limits the record list to
the records which are stored on that page, :guilabel:`Include subpages` extends the
selection to the children of that page. The entry :guilabel:`All pages` shows every
record the editor is allowed to see. The page tree can be switched off, in that case
the module always works on a configured page.

Record list
-----------

The record list shows the following information, the columns are configurable:

- Title, teaser, date, archive date, creation date, change date
- Categories, tags, author, slug and uid
- The storage page of the record
- The language of the record
- Status badges for hidden, top news, archived and time restricted records

The list can be sorted by title, date, archive date, top news flag, creation date,
change date, author and slug, and it is paginated with a configurable number of
records per page. Every record can be opened in the FormEngine, and news records
can be created, toggled, sorted and deleted from the module.

Filters
-------

- Full text search in title, teaser and body text
- Time range: today, this month, this year or a manual range
- Top news, archive state and visibility
- Language
- Categories, optionally including their subcategories and combined with
  "one of", "all of", "none of" or "not all of"

Single filters can be switched off in the configuration, and the filter and sorting
of the editor is remembered for the module.

Control panels
--------------

Every record of the list can be

- shown or hidden
- moved up or down within its storage page
- deleted, with a confirmation page

The module shows the number of news, category and tag records, and the second view
of the module lists all pages which contain records including their number, with
shortcuts to create new records.

Permissions
===========

The module is available for every backend user, but a user only sees, edits and
creates records on pages the user has access to:

- A non-admin user sees only records of pages where the user has the permission to
  show the page and where the page is within the web mounts of the user
- The edit, delete, toggle and move buttons are only rendered if the user may edit
  the storage page and the table of the record
- The create shortcuts are only rendered if the user may create records of the
  table on that page

Records are never filtered by :guilabel:`hidden`, :guilabel:`Start time` or
:guilabel:`End time`, because these are the fields an editor wants to see in the
list. Deleted records are always excluded.

Requirements
============

- TYPO3 CMS 13.4 or 14.3
- EXT:news 13.0 or 14.1
