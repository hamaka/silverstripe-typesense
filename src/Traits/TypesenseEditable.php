<?php

    namespace Hamaka\Typesense\Traits;

    use Hamaka\Typesense\Service\TypesenseService;
    use SilverStripe\Core\Injector\Injector;
    use SilverStripe\ORM\DataObject;

    /**
     * Trait to customize DataObject data for Typesense
     *
     * Apply this trait to any DataObject to modify the data sent to the Typesense server
     */
    trait TypesenseEditable
    {
        /**
         * Get the Typesense document representation of this object
         *
         * @return array|false The Typesense document or false if the object couldn't be rendered
         */
        public function getTypesenseDocument(): array
        {
            /** @var TypesenseService $oService */
            $oService = Injector::inst()->get(TypesenseService::class);

            /** @var DataObject $this */
            $aData = $oService->getTypesenseDocument($this);

            $aData = $this->updateTypesenseDocument($aData);

            return $aData;
        }

        /**
         * Add additional fields to the Typesense document
         *
         * Override this method in your DataObject to add additional fields to the Typesense document
         *
         * @param array $aDocument The Typesense document
         *
         * @return array The modified Typesense document
         */
        public function updateTypesenseDocument(array $aDocument): array
        {
            return $aDocument;
        }
    }
