# Configuration

## Environment variables

```bash
# Admin API key of your Typesense server.
# Generate one with: head /dev/urandom | shasum -a 256
TYPESENSE_API_KEY="your-admin-api-key"

# Typesense server, in scheme://host:port format
TYPESENSE_SERVER="https://typesense.example.com:443"

# Search-only API key, used by the frontend. Create it in the Typesense dashboard.
TYPESENSE_SEARCH_KEY="your-search-only-api-key"

# Set to true on your local machine, see "Local development" below
TYPESENSE_LOCAL=
```

`TYPESENSE_SERVER` is used by the CMS for indexing **and** is written into the overlay's HTML for the browser. It therefore has to be reachable from both the web server and your visitors.

Never put the admin key (`TYPESENSE_API_KEY`) in the frontend. The overlay only uses `TYPESENSE_SEARCH_KEY`.

## Collections

Collections are defined in YAML, with the format from [elliotsawyer/silverstripe-typesense](https://codeberg.org/0x/silverstripe-typesense). The module doesn't define a collection itself. Start from the example:

```shell
cp vendor/hamaka/silverstripe-typesense/_config/typesense.yml.example app/_config/typesense.yml
```

```yaml
---
Name: app-typesense
---
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    SilverStripe\CMS\Model\SiteTree:
      name: "example.com" # Must be unique per site on a shared Typesense server
      fields:
        - { name: Title, type: string, sort: true }
        - { name: FullPage, type: string, optional: true }
        - { name: URL, type: string, optional: true }
      import_limit: 10
      connection_timeout: 2
      excluded_classes:
        - SilverStripe\ErrorPage\ErrorPage
```

Keep `Title`, `FullPage` and `URL`: the overlay searches on `Title` and `FullPage`, and links to `URL`.

### Local development

Use a separate collection locally so you don't overwrite the production index. The frontend script automatically adds `_local` to the index name when the site runs on `localhost` or `127.0.0.1`. Match that in YAML:

```yaml
---
Only:
  envorconstant:
    TYPESENSE_LOCAL: true
---
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    SilverStripe\CMS\Model\SiteTree:
      name: "example.com_local"
```

## Cleaning up the page content

The indexed text comes from the rendered page (see [How it works](how-it-works.md)). Use these options on `TypesenseService` to leave out navigation, headers, footers and other repeated content:

```yaml
Hamaka\Typesense\Service\TypesenseService:
  # Extra tags to remove (added to the defaults)
  strip_dom_tags:
    - header
    - footer
    - nav

  # Remove elements whose class contains one of these values
  strip_dom_classes:
    - related-news
    - cookie-banner

  # Only index the content of the first element with this class
  target_dom_class: 'main-content'
```

| Option | Default | Notes |
|---|---|---|
| `strip_dom_tags` | `style`, `script`, `img`, `svg`, `noscript`, `iframe`, `object`, `embed`, `video`, `audio`, `canvas`, `picture`, `source`, `link`, `meta`, `head`, `template` | YAML arrays are merged, so your tags are added to the defaults |
| `strip_dom_classes` | none | Matched with `contains()`, so `news` also matches `related-news` |
| `target_dom_class` | none | If the class isn't found, the whole page is used |

After changing these options, run the sync task again to rebuild the index.
