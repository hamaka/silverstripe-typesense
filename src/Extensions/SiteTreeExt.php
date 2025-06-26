<?php

    namespace Hamaka\Typesense\Extensions;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use Psr\Log\LoggerInterface;
    use SilverStripe\Core\Extension;
    use SilverStripe\Core\Injector\Injector;
    use Typesense\Exceptions\ObjectNotFound;

    class SiteTreeExt extends Extension
    {
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
