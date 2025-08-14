# Silverstripe Typesense Integration

This module provides integration between Silverstripe CMS and Typesense search engine.

## Installation

Before installing the hamaka/silverstripe-typesense package, you have to add the path to your composer.json

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@bitbucket.org:hamaka/silverstripe-typesense.git"
        }
    ]
}



```

After installing, run a dev/build to ensure all new classes are available.

```bash
yarn run dev/build
```

## Configuration

### Enviorment variables for Typesense

```bash
#'head /dev/urandom | shasum -a 256' works great for this
TYPESENSE_API_KEY=5f02d0b58c49a99e8e8f65b74ebb9fdc5b1a2dfe56639bdc8551650900435f33

# this is your INTERNALLY accessible typesense server, not the one that appears in frontend
TYPESENSE_SERVER="https://typesense.hamakaserver.nl:443"

# this is obtained from the Typesense dashboard, after you've started it up
TYPESENSE_SEARCH_KEY=""

# Set this to true on localhost
TYPESENSE_LOCAL=
```
### Configuring the FullPage DOM Extract Service

Use this configuration to clean scraped HTML content before indexing it in Typesense. You can remove unwanted DOM tags and optionally target a specific element by CSS class to extract the main content for the FullPage field.

By default, the service removes non-content HTML tags (e.g. `<style>`, `<script>`, `<img>`). See _config/typesense.yml. 

```yaml
Hamaka\Typesense\Service\TypesenseService:
  # Tags to strip from the HTML DOM (e.g. script, style, img)
  strip_dom_tags:
    - h1
    - header
    - footer

  # Remove elements based on CSS class names (e.g. related-news)
  strip_dom_classes: [ ]

  # Extract content from a specific DOM element by class (e.g. 'main-content')
  target_dom_class: 'article'
```

### Making a collection for Typesense

You can configure the Typesense integration using YAML configuration. Create a file in your project's `_config` directory:

```yaml
# app/_config/typesense.yml
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    SilverStripe\CMS\Model\SiteTree:
      name: "your-collection" # Use a unique name to prevent merging with other collections 
      fields:
        - { name: Title, type: string, sort: true }
        - { name: FullPage, type: string, optional: true }
        - { name: URL, type: string, optional: true }
      default_sorting_field: # 'exampleField'
      token_separators: # '-'
      symbols_to_index: # '+'
      import_limit: 10
      connection_timeout: 2
      excluded_classes:
        - SilverStripe\ErrorPage\ErrorPage
---
Only:
  envorconstant:
    TYPESENSE_LOCAL: true
---
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    SilverStripe\CMS\Model\SiteTree:
      name: "your-collection_local" #Make sure to add the suffix "_local" when using the front-end search
```
## Usage
### Building the Typesense index
Run the build task `dev/tasks/TypesenseSyncTaskHmk` to create the collections and upload them to your Typesense server.
### Building a front-end search

#### Building the JavaScript bundle
This package includes a JavaScript component for implementing Typesense instant search in your frontend.

#### Using the TypesenseInstantSearch class in your JavaScript

The TypesenseInstantSearch class is exposed as a UMD module, which means you can use it in various ways:

1. Direct script tag:
```html
<script src="vendor/hamaka/silverstripe-typesense/client/dist/javascript/bundle.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const search = new TypesenseInstantSearch({
      indexName: 'your-collection-name'
    });
  });
</script>
```

2. ES6 import (if you're using a module bundler like webpack):
```javascript
import TypesenseInstantSearch from 'path/to/bundle.js';

document.addEventListener('DOMContentLoaded', function() {
  const search = new TypesenseInstantSearch({
    indexName: 'your-collection-name'
  });
});
```

3. CommonJS require:
```javascript
const TypesenseInstantSearch = require('path/to/bundle.js');

document.addEventListener('DOMContentLoaded', function() {
  const search = new TypesenseInstantSearch({
    indexName: 'your-collection-name'
  });
});
```

Make sure your HTML includes the necessary elements for the search interface:

```html
<div class="js-global-search-container" data-typesense-search-key="your-search-key" data-typesense-server="your-typesense-server">
  <button class="js-search-open">Open Search</button>
  <button class="js-search-close">Close Search</button>
  <div id="global-search-input"></div>
  <div id="global-stats"></div>
  <div id="global-hits"></div>
</div>
```


## License

MIT
