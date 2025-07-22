<?php
    /**
     * Silverstripe Typesense module
     * @license   LGPL3 With Attribution
     * @copyright Copyright (C) 2024 Elliot Sawyer
     */

    namespace Hamaka\Typesense\Tasks;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use SilverStripe\Control\Director;
    use SilverStripe\Dev\BuildTask;
    use SilverStripe\ORM\DB;
    use function _t;

    class TypesenseSyncTask extends BuildTask
    {
        private static $segment = 'TypesenseSyncTaskHmk';

        public function getTitle()
        {
            return _t(\ElliotSawyer\SilverstripeTypesense\TypesenseSyncTask::class . '.TITLE', 'Typesense sync task') . " HAMAKA";
        }

        public function getDescription()
        {
            return _t(TypesenseSyncTask::class . '.DESCRIPTION', 'Creates and indexes your Typesense collections');
        }

        public function run($request = null)
        {
            // Allow execution on localhost or via CLI
            $isLocalhost = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);

            if (Director::is_cli() !== true && !$isLocalhost) {
                DB::alteration_message("This task can only be executed by CLI or on localhost.");
                DB::alteration_message("<p>php ./vendor/silverstripe/framework/cli-script.php /dev/tasks/TypesenseSyncTaskHmk</p>");
                return false;
            }

            $copyright = (new Typesense())->CopyrightStatement();
            DB::alteration_message($copyright);
            $client = Typesense::client();
            $this->extend('onBeforeBuildAllCollections');
            $collections = $this->findOrMakeAllCollections();
            $this->extend('onAfterBuildAllCollections', $collections);
            if ( ! $collections) {
                return;
            }

            $this->extend('onBeforeImportDocuments');
            foreach ($collections as $collection) {
                if (empty($client->collections[$collection->Name])) {
                    DB::alteration_message($collection->syncWithTypesenseServer());
                }
                $collection->import();
            }
            $this->extend('onAfterImportDocuments', $collections);
            $this->extend('onEndOfSyncTask');
        }

        private function findOrMakeAllCollections()
        {
            $ymlIndexes = Typesense::config()->get('collections') ?? [];
            foreach ($ymlIndexes as $recordClass => $collection) {
                $collectionName = $collection['name'] ?? null;
                if ( ! $collectionName) {
                    continue;
                }

                if (isset($_SERVER['HTTP_HOST'])) {
                    if (
                        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
                    ) {
                        $collectionName = $collectionName . '_local';
                    }
                }

                $dbCollection = Collection::find_or_make($collectionName, $recordClass, $collection);
            }
            $indexes = Collection::get()
                                 ->sort('Sort ASC')
                                 ->filter([
                                     'Enabled' => true
                                 ]);

            return $indexes;
        }
    }
