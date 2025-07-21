<?php
    /**
     * Silverstripe Typesense module
     * @license   LGPL3 With Attribution
     * @copyright Copyright (C) 2024 Elliot Sawyer
     */

    namespace Hamaka\Typesense\Tasks;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use SilverStripe\Dev\BuildTask;
    use SilverStripe\ORM\DB;
    use Symbiote\QueuedJobs\Services\QueuedJobService;
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
            // Display copyright information
            $copyright = (new Typesense())->CopyrightStatement();
            DB::alteration_message($copyright);

            // Find or create all collections
            $collections = $this->findOrMakeAllCollections();

            if (!$collections || $collections->count() === 0) {
                DB::alteration_message("No collections found to process");
                return;
            }

            // Create and queue the job
            $job = new TypesenseSyncJob($collections->toArray());

            // Queue the job with a 1-minute delay to ensure the web request completes
            $jobID = QueuedJobService::singleton()->queueJob($job);

            // Provide feedback
            DB::alteration_message(
                "Typesense sync job has been queued (Job ID: {$jobID}). The synchronization will run in the background."
            );
            DB::alteration_message(
                "This approach avoids the 30-second timeout issue that can occur when running the sync directly."
            );
            DB::alteration_message(
                "Total collections to process: " . $collections->count()
            );

            // List the collections that will be processed
            DB::alteration_message("Collections to be processed:");
            foreach ($collections as $collection) {
                DB::alteration_message("- {$collection->Name}");
            }
        }

        private function findOrMakeAllCollections()
        {
            $ymlIndexes = Typesense::config()->get('collections') ?? [];
            foreach ($ymlIndexes as $recordClass => $collection) {
                $collectionName = $collection['name'] ?? null;
                if (!$collectionName) {
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
