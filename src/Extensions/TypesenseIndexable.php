<?php

namespace Hamaka\Typesense\Extensions;

use Hamaka\Typesense\Service\TypesenseService;
use SilverStripe\Core\Injector\Injector;

/**
 * Trait to make a DataObject indexable by Typesense
 *
 * Apply this trait to any DataObject that should be indexed in Typesense
 */
trait TypesenseIndexable
{
    /**
     * Get the Typesense document representation of this object
     *
     * @return array|false The Typesense document or false if the object couldn't be rendered
     */
    public function getTypesenseDocument()
    {
        /** @var TypesenseService $service */
        $service = Injector::inst()->get(TypesenseService::class);
        return $service->getTypesenseDocument($this);
    }

    /**
     * Add additional fields to the Typesense document
     *
     * Override this method in your DataObject to add additional fields to the Typesense document
     *
     * @param array $document The Typesense document
     * @return array The modified Typesense document
     */
    public function updateTypesenseDocument($document)
    {
        return $document;
    }
}
