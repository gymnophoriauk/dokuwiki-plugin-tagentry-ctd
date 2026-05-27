# Tagentry Plugin Changelog

## v1.0.1 (2026-05-27)
**Author:** Rob How

Updated for DokuWiki 2025-05-14a "Librarian" (PHP 8.3.30) compatibility.

### Compatibility Fixes

- **Replaced deprecated `HTML_EDITFORM_OUTPUT` event with `FORM_EDIT_OUTPUT`**
  The old `Doku_Form`-based event was removed in Librarian. The plugin now hooks
  into the new `dokuwiki\Form\Form`-based event.

- **Replaced deprecated `Doku_Form` API calls with new Form API**
  - `findElementByAttribute()` → `findPositionByAttribute()`
  - `findElementByType('wikitext')` / `_content[$wt]['_text']` → `findPositionByType('textarea')` / `getElementAt()->val()`
  - `insertElement()` → `addHTML()`

- **Replaced deprecated `resolve_pageid()` with `dokuwiki\File\PageResolver`**
  The `_getTagTitle()` method now uses the new `PageResolver` class to resolve
  tag page IDs, and `page_exists()` to check existence, instead of the removed
  `resolve_pageID()` function.

- **Replaced deprecated `require_once(DOKU_PLUGIN.'action.php')` with `use` statements**
  Now uses `use dokuwiki\Extension\ActionPlugin`, `use dokuwiki\Extension\EventHandler`,
  and `use dokuwiki\Extension\Event` as per Librarian conventions.

- **Replaced removed `DOKU_PLUGIN` constant usage**
  The `DOKU_PLUGIN` define block at the top of the file has been removed; it is
  no longer needed with the modern autoloading/use-statement approach.

### Code Quality Improvements

- Added `hsc()` escaping to tag name output in checkbox HTML attributes for XSS safety
- Fixed typo in JavaScript alert: "incomlete" → "incomplete"
- Added explicit visibility (`public`) to all class methods
- Modernised array syntax from `array()` to `[]` where appropriate
- Preserved all original functionality, config options, and language files
