# How it works

Most search modules index database fields. That misses everything a page shows but doesn't store in `Content`: elemental blocks, related items, content added by templates. This module instead renders the page the way an anonymous visitor would see it and indexes the resulting text.

## From page to search document

For every page in a collection, `SiteTreeExtension::getTypesenseDocument()` builds a document:

1. **Skip hidden pages.** Pages with *Show in search* turned off are not indexed.
2. **Render the page as a visitor.** `TypesenseService::renderPageAsNonMember()` logs out for a moment (`Member::actAs(null, ...)`) and requests the live version of the page internally with `Director::test()`. No real HTTP request leaves the server. If the response isn't `200`, the page is skipped.
3. **Replace images with their text.** Before rendering, the `[image]` shortcode is swapped for `TypesenseImageShortcodeProvider`, which outputs only the image's alt and title text.
4. **Clean up the DOM.**
   - Tags in `strip_dom_tags` are removed (by default `script`, `style`, `img`, `svg`, `iframe`, `video`, `head` and similar non-content tags).
   - Elements with a class from `strip_dom_classes` are removed.
   - If `target_dom_class` is set and an element with that class exists, only that element is kept. Otherwise the whole page is used.

   See [Configuration](configuration.md#cleaning-up-the-page-content) for these options.
5. **Convert to readable text.** `convertHtmlToPlainText()` turns the remaining HTML into plain text that keeps some structure:
   - headings as `#`, `##`, ...
   - list items with bullets or numbers
   - tables as `cell | cell` rows, with their caption
   - alt text, `aria-label`s, iframe titles and form field labels in brackets, e.g. `[Afbeelding: ...]`
   - external links (`target="_blank"`) with their URL
6. **Store the document.** The result is stored as `FullPage`, together with `id`, `ClassName`, `Title`, `Created`, `LastEdited` and the live `URL`.

## Keeping the index up to date

| CMS action | What happens in Typesense |
|---|---|
| Publish | The document is upserted |
| Publish with *Show in search* off | The document is deleted |
| Publish of a page without a URL | The document is deleted |
| Unpublish | The document is deleted |
| Page class listed in `excluded_classes` | Nothing |

To (re)build everything at once, run the sync task:

```shell
vendor/bin/sake dev/tasks/TypesenseSyncTaskHmk
```

The task creates missing collections on the Typesense server and imports all documents. It only runs from the CLI or on `localhost`. Elliot Sawyer's own `TypesenseSyncTask` is disabled by this module's config.

## Indexing your own page type differently

`getTypesenseDocument()` is defined on the `SiteTree` extension. To change what gets indexed for a specific page type, define `getTypesenseDocument()` on that class. If you add fields, also add them to the collection's `fields` in YAML.
