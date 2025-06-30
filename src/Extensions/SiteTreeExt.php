<?php

    namespace Hamaka\Typesense\Extensions;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use Hamaka\Typesense\Service\TypesenseService;
    use Psr\Log\LoggerInterface;
    use SilverStripe\Core\Extension;
    use SilverStripe\Core\Injector\Injector;
    use SilverStripe\ORM\DataObject;
    use Typesense\Exceptions\ObjectNotFound;

    class SiteTreeExt extends Extension
    {

        /**
         * Custom method to prepare data for Typesense indexing
         * Strips HTML tags from Content field to ensure plain text is indexed
         *
         * @param DataObject $object The object to convert to a Typesense document
         *
         * @return array|false The Typesense document or false if the object couldn't be rendered
         */
        public function getTypesenseDocument(): array
        {
            $oTypesenseService = TypesenseService::singleton();

            // Get page content as rendered for a non-member
            $pageContent = $oTypesenseService->renderPageAsNonMember($this->owner);

            if ( ! $pageContent) {
                return [];
            }

            // Initialize data array with basic fields
            $aData = $oTypesenseService->initializeTypesenseData($this->owner);

            // Add page content to data array
            $aData['FullPage'] = $oTypesenseService->convertHtmlToAccessibleText($pageContent);

            return $aData;
        }

        public function onAfterPublish(&$original)
        {
            try {
                $oCollections = Collection::get()->filter(['RecordClass' => $this->owner->getClassAncestry(), 'Enabled' => true]);

                if ($oCollections->count() > 0) {
                    foreach ($oCollections as $oCollection) {
                        $connection_timeout = (int)$oCollection->ConnectionTimeout ?: 2;
                        $client             = Typesense::client($connection_timeout);

                        if ($this->owner->hasMethod('getTypesenseDocument')) {
                            $data = $this->owner->getTypesenseDocument();
                        }
                        else {
                            $fieldsArray = $oCollection->FieldsArray();
                            $data        = $oCollection->getTypesenseDocument($this->owner, $fieldsArray);
                        }

                        $client->collections[$oCollection->Name]->documents->upsert($data);
                    }
                }
            }
            catch (ObjectNotFound $e) {
                Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
            }
        }

        public function onAfterUnpublish()
        {
            try {
                $oCollections = Collection::get()->filter(['RecordClass' => $this->owner->getClassAncestry(), 'Enabled' => true]);

                if ($oCollections->count() > 0) {
                    foreach ($oCollections as $oCollection) {
                        $connection_timeout = (int)$oCollection->ConnectionTimeout ?: 2;
                        $client             = Typesense::client($connection_timeout);

                        if ($oCollection->checkExistance()) {
                            $client->collections[$oCollection->Name]->documents[(string)$this->owner->ID]->delete();
                        }
                    }
                }
            }
            catch (ObjectNotFound $e) {
                Injector::inst()->get(LoggerInterface::class)->info($e->getMessage());
            }
        }
    }
