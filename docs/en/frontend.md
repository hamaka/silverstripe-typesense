# Frontend search

The module includes a search overlay that shows results while the visitor types, built with [InstantSearch.js](https://www.algolia.com/doc/guides/building-search-ui/what-is-instantsearch/js/) and the [Typesense InstantSearch adapter](https://github.com/typesense/typesense-instantsearch-adapter).

![Search overlay with instant results](images/overlay.png)

## Features

- Results appear as you type, with highlighted matches in the title and a two-line snippet from the page
- "Load more" button (infinite hits) and a result count
- The search term is kept in the URL (`?q=...`), so searches can be shared and the back button works
- Opening a URL with `?q=...` opens the overlay with those results
- The page title changes to *Zoekresultaten voor "..."* while searching
- Each search is sent to your analytics as a virtual pageview (see below)
- Closes with the close button or `Esc`

## Adding the overlay

Include the template and add one or more buttons that open it:

```html
<% include TypesenseSearchOverlay %>

<button class="js-search-open">Search</button>
```

The overlay only renders when `TYPESENSE_SEARCH_KEY` and `TYPESENSE_SERVER` are set. It has a close button; add more with the class `js-search-close`.

To change the markup, copy `templates/TypesenseSearchOverlay.ss` to your theme. Keep the class `js-global-search-container`, its `data-` attributes and the elements `#global-search-input`, `#global-stats` and `#global-hits`.

## Loading the script

The bundle in `client/dist/javascript/bundle.js` is a UMD module that exposes `TypesenseInstantSearch`. Pass the collection name as `indexName`.

**Script tag**

```html
<script src="$resourceURL('hamaka/silverstripe-typesense:client/dist/javascript/bundle.js')"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    new TypesenseInstantSearch({ indexName: 'your-collection' });
  });
</script>
```

**ES module (webpack or another bundler)**

```javascript
import TypesenseInstantSearch from 'path/to/bundle.js';

document.addEventListener('DOMContentLoaded', () => {
  new TypesenseInstantSearch({ indexName: 'your-collection' });
});
```

**CommonJS**

```javascript
const TypesenseInstantSearch = require('path/to/bundle.js');
```

On `localhost` and `127.0.0.1`, `_local` is added to `indexName`. See [Local development](configuration.md#local-development).

## Analytics

Every time the search term changes (and on back/forward navigation), the overlay sends:

| Setup on the page | Event |
|---|---|
| Google Tag Manager | `dataLayer.push({ event: 'view_search_results', search_term })` |
| gtag.js without Tag Manager | `page_view` with the new URL, and `view_search_results` with `search_term` |
| Matomo / Piwik (`_paq`) | `setCustomUrl`, `setDocumentTitle` and `trackPageView` |

In Tag Manager, create a trigger on the custom event `view_search_results` and a data layer variable `search_term`.

## Known limitations

- The overlay uses **jQuery** to scroll to the top when it opens. jQuery must be loaded on the page.
- Showing and hiding uses the **Bootstrap** class `d-none`. Without Bootstrap, add `.d-none { display: none !important; }` to your CSS.
- **No CSS is included.** Style the `search-overlay__*` classes in your theme.
- The interface texts are in **Dutch** ("Vul een zoekterm in...", "resultaten", "Meer laden") and are not translatable yet.
- The close button has a fixed colour (`#E83A47`). Override the template to change it.
