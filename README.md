# Silverstripe Typesense Integration

This module provides integration between Silverstripe CMS and Typesense search engine.

## Installation

Before installing the hamaka/silverstripe-typesense package, you have to add the path to your composer.json

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "https://[YOURBITBUCKETUSERNAME]@bitbucket.org/hamaka/silverstripe-typesense.git"
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
```

### Making a collection for Typesense

You can configure the Typesense integration using YAML configuration. Create a file in your project's `_config` directory:

```yaml
# app/_config/typesense.yml
ElliotSawyer\SilverstripeTypesense\Typesense:
  collections:
    SilverStripe\CMS\Model\SiteTree:
      name: "Pages"
      fields:
        - { name: Title, type: string, sort: true }
        - { name: FullPage, type: string, optional: true }
      default_sorting_field: # 'exampleField'
      token_separators: # '-'
      symbols_to_index: # '+'
      import_limit: 10
      connection_timeout: 2
      excluded_classes:
        - SilverStripe\ErrorPage\ErrorPage
```

## Usage

### Customize the data for an DataObject

To customize the data from a DataObject indexable by Typesense, use the `TypesenseEditable` trait and use the function `updateTypesenseDocument` to modify the output to the typesense server:

```php
use Hamaka\Typesense\Traits\TypesenseEditable;
use SilverStripe\ORM\DataObject;

class MyPage extends DataObject
{
    use TypesenseEditable;
    
    /**
     * Customize the Typesense document
     */
    public function updateTypesenseDocument($document)
    {
        // Add custom fields or modify the document
        $document['CustomField'] = $this->CustomValue;
        
        return $document;
    }
}
```

### Using the TypesenseService directly

You can also use the TypesenseService directly:

```php
use Hamaka\Typesense\Service\TypesenseService;
use SilverStripe\Core\Injector\Injector;

// Get the service
$typesenseService = Injector::inst()->get(TypesenseService::class);

// Convert HTML to accessible text
$accessibleText = $typesenseService->convertHtmlToAccessibleText($htmlContent);

// Get a Typesense document for a DataObject
$document = $typesenseService->getTypesenseDocument($myDataObject);
```

## Available Methods

The TypesenseService provides several methods for processing HTML content:

- `getTypesenseDocument(DataObject $object)`: Converts a DataObject to a Typesense document
- `renderPageAsNonMember(DataObject $object)`: Renders a page as a non-member
- `convertHtmlToAccessibleText($html)`: Converts HTML to accessible text
- `addStrippedHtmlFields($aData, DataObject $object, array $fields)`: Adds fields with HTML converted to accessible text

## License

MIT
