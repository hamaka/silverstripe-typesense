<?php

    namespace Hamaka\Typesense\Extensions;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use Hamaka\Typesense\Service\TypesenseService;
    use Psr\Log\LoggerInterface;
    use SilverStripe\Core\Extension;
    use SilverStripe\Core\Injector\Injector;
    use SilverStripe\ORM\DataObject;
    use SilverStripe\Versioned\Versioned;
    use Typesense\Exceptions\ObjectNotFound;
    use function in_array;
    use function strtolower;

    class SiteTreeExtension extends Extension
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
            if ((int)$this->owner->ShowInSearch === 0) {
                return false;
            }

            $oTypesenseService = TypesenseService::singleton();

            // Get page content as rendered for a non-member
            $pageContent = $oTypesenseService->renderPageAsNonMember($this->owner);

            if ( ! $pageContent) {
                return [];
            }

            // Initialize data array with basic fields
            $aData = $oTypesenseService->initializeTypesenseData($this->owner);

            // Add page content to data array
            $aData['FullPage'] = $oTypesenseService->convertHtmlToPlainText($pageContent);

            $sCurrentReadingMode = Versioned::get_reading_mode(); //Prevents stage url
            Versioned::set_reading_mode('Stage.Live'); //Prevents stage url

            $aData['URL'] = $this->owner->AbsoluteLink();

            Versioned::set_reading_mode($sCurrentReadingMode); //Prevents stage url

            return $aData;
        }

        public function onAfterPublish(&$original)
        {

            try {

                $oCollections = TypesenseService::findOrMakeAllCollections()->filter(['RecordClass' => $this->owner->getClassAncestry()]);

                if ($oCollections->count() > 0) {
                    foreach ($oCollections as $oCollection) {

                        $excludedClasses = json_decode($oCollection->ExcludedClasses, true) ?? [];
                        if (in_array(strtolower($this->owner->ClassName), $excludedClasses)) {
                            break;
                        }

                        $connection_timeout = (int)$oCollection->ConnectionTimeout ?: 2;
                        $client             = Typesense::client($connection_timeout);

                        if ((int)$this->owner->ShowInSearch === 0) {
                            $client->collections[$oCollection->Name]->documents[(string)$this->owner->ID]->delete();
                            break;
                        }

                        if ($this->owner->hasMethod('getTypesenseDocument')) {
                            $data = $this->owner->getTypesenseDocument();
                        }
                        else {
                            $fieldsArray = $oCollection->FieldsArray();
                            $data        = $oCollection->getTypesenseDocument($this->owner, $fieldsArray);
                        }

                        if ( ! $data['URL'] || $data['URL'] === "") {
                            $client->collections[$oCollection->Name]->documents[(string)$this->owner->ID]->delete();

                            break;
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

        /**
         * (NOT IN USE) Clean URL by removing 'stage=stage' parameter
         *
         * @param string $url The URL to clean
         *
         * @return string The cleaned URL
         */
        protected function cleanUrl($url)
        {
            // Remove stage=stage parameter
            $url = preg_replace('/\?stage=stage$/', '', $url); // If it's the only parameter
            $url = preg_replace('/\?stage=stage&/', '?', $url); // If it's the first parameter
            $url = preg_replace('/&stage=stage$/', '', $url); // If it's the last parameter
            $url = preg_replace('/&stage=stage&/', '&', $url); // If it's in the middle

            return $url;
        }
    }
