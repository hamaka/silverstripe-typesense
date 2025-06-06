# Silverstripe Typesense Integration

This module provides integration between Silverstripe CMS and Typesense search engine.

## Installation

```bash
composer require hamaka/silverstripe-typesense
```

After installing, run a dev/build to ensure all new classes are available.

```bash
vendor/bin/sake dev/build
```

## Configuration

You can configure the Typesense integration using YAML configuration. Create a file in your project's `_config` directory:

```yaml
# app/_config/typesense.yml
---
Name: app-typesense
After: 'typesense'
---
Hamaka\Typesense\Service\TypesenseService:
  enabled: true
  # Add your custom configuration options here
```

## Usage

### Making a DataObject indexable

To make a DataObject indexable by Typesense, use the `TypesenseIndexable` trait:

```php
use Hamaka\Typesense\Extensions\TypesenseIndexable;
use SilverStripe\ORM\DataObject;

class MyPage extends DataObject
{
    use TypesenseIndexable;
    
    // Your DataObject code...
    
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
