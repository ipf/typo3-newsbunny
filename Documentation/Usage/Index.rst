..  _usage:

===============
Editors manual
===============

Go to :guilabel:`Web > NewsBunny`.

Select the page the records are stored on
=========================================

The page tree of the module shows the pages the editor is allowed to see. Clicking a
page limits the record list to the records of that page, the entry
:guilabel:`All pages` shows every record. Check :guilabel:`Include subpages` to
include the records which are stored on the children of the selected page. Hidden
pages are marked with a badge.

The filter
==========

The filter form is placed above the record list and can be folded away unless the
setting :ref:`alwaysShowFilter` is switched on. A filter is applied when the button
:guilabel:`Apply filters` is used, the button :guilabel:`Reset filters` clears the
filter but keeps the sorting and the number of records per page. The module remembers
the filter and the page of the editor for the next visit.

The full text search looks into the title, the teaser and the body text of a record.
The time filter works with the date of a record, the archive filter with its archive
date. The category filter combines the selected categories with
:guilabel:`One of the categories`, :guilabel:`All categories`,
:guilabel:`None of the categories` or :guilabel:`Not all categories`, optionally
including the subcategories of the selected categories.

The record list
===============

Clicking a column header sorts the list by that column, a second click reverses the
order. The buttons at the end of a row:

- :guilabel:`Edit` opens the record in the FormEngine, the module is used as the
  return target
- The eye button hides a visible record or shows a hidden one
- The arrow buttons move the record up or down within its storage page
- :guilabel:`Delete` opens a confirmation page, the record is deleted there

The buttons for hiding, sorting and deleting are only shown if the editor may change
the record, see :ref:`introduction`. Records with a badge in the column
:guilabel:`Status` are hidden, top news, archived or time restricted.

Creating records
================

The buttons in the top right of the module create a news record, a category or a tag.
The new record is created on the page which is selected in the page tree, on the page
of the setting :ref:`defaultPid`, or on the first page the editor may add records to.
The :guilabel:`Create news record` button is the same as the one in the page module.

The page listing
================

The button :guilabel:`Storage pages` in the top right switches to the second view of
the module. It lists all pages which contain news, category or tag records
including the number of records per page. The link in the first column filters the
record list by that page, the button :guilabel:`Edit` opens the page in the page
module, and the three buttons on the right create a news record, a category or a tag
on that page.
