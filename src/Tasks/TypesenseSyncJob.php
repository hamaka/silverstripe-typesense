<?php
    /**
     * Silverstripe Typesense module
     * @license   LGPL3 With Attribution
     * @copyright Copyright (C) 2024 Elliot Sawyer
     */

    namespace Hamaka\Typesense\Tasks;

    use ElliotSawyer\SilverstripeTypesense\Collection;
    use ElliotSawyer\SilverstripeTypesense\Typesense;
    use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
    use Symbiote\QueuedJobs\Services\QueuedJob;
    use function _t;

    class TypesenseSyncJob extends AbstractQueuedJob
    {
        /**
         * @var array
         */
        private $collections = [];

        /**
         * @var int
         */
        private $currentCollectionIndex = 0;

        /**
         * @var int
         */
        private $recordOffset = 0;

        /**
         * @var int
         */
        private $totalCollections = 0;

        /**
         * @var Collection|null
         */
        private $currentCollection = null;

        /**
         * Constructor for the job
         *
         * @param array $collections Optional array of collections to process
         */
        public function __construct($collections = [])
        {
            // If collections are provided, use them, otherwise they'll be loaded in process()
            if ( ! empty($collections)) {
                $this->collections      = $collections;
                $this->totalCollections = count($collections);
            }

            $this->currentCollectionIndex = 0;
            $this->recordOffset           = 0;
        }

        /**
         * Get the job type
         *
         * @return string
         */
        public function getJobType()
        {
            return QueuedJob::QUEUED;
        }

        /**
         * Get the job title
         *
         * @return string
         */
        public function getTitle()
        {
            return _t(\ElliotSawyer\SilverstripeTypesense\TypesenseSyncTask::class . '.TITLE', 'Typesense sync task') . " HAMAKA";
        }

        /**
         * Setup the job
         */
        public function setup()
        {
            // Initialize collections if not already done
            if (empty($this->collections)) {
                $this->collections      = $this->findOrMakeAllCollections();
                $this->totalCollections = count($this->collections);
            }

            // Set initial progress values
            $this->totalSteps  = $this->totalCollections * 100; // Each collection counts as 100 steps
            $this->currentStep = 0;
        }

        /**
         * Process the job
         */
        public function process(): void
        {
            // Check if we have collections to process
            if (empty($this->collections)) {
                $this->addMessage("No collections found to process");
                $this->isComplete = true;

                return;
            }

            // If we're just starting, output the copyright
            if ($this->currentCollectionIndex === 0 && $this->recordOffset === 0) {
                $copyright = (new Typesense())->CopyrightStatement();
                $this->addMessage($copyright);
            }

            // Get the current collection
            if ( ! $this->currentCollection && isset($this->collections[$this->currentCollectionIndex])) {
                $this->currentCollection = $this->collections[$this->currentCollectionIndex];
                $client                  = Typesense::client();

                // Create collection if it doesn't exist
                if (empty($client->collections[$this->currentCollection->Name])) {
                    $this->addMessage($this->currentCollection->syncWithTypesenseServer());
                }
            }

            // Process the current collection
            if ($this->currentCollection) {
                try {
                    $this->processCollection($this->currentCollection);
                }
                catch (\Exception $e) {
                    // Log the error and move to the next collection
                    $this->addMessage("Error processing collection {$this->currentCollection->Name}: " . $e->getMessage());

                    // Move to the next collection
                    $this->currentCollectionIndex++;
                    $this->recordOffset      = 0;
                    $this->currentCollection = null;
                }
            }

            // Check if we've completed all collections
            if ($this->currentCollectionIndex >= $this->totalCollections) {
                $this->addMessage("Typesense sync completed");
                $this->isComplete = true;
            }
        }

        /**
         * Process a single collection
         *
         * @param Collection $collection The collection to process
         */
        private function processCollection($collection)
        {
            $limit              = (int)($collection->ImportLimit ?: 10000);
            $connection_timeout = (int)$collection->ConnectionTimeout ?: 2;
            $client             = Typesense::client($connection_timeout);

            $records     = null;
            $recordClass = $collection->RecordClass;
            if (class_exists('SilverStripe\Subsites\Model\Subsite')) {
                \SilverStripe\Subsites\Model\Subsite::disable_subsite_filter();
            }
            if (class_exists('SilverStripe\Versioned\Versioned')) {
                $records = \SilverStripe\Versioned\Versioned::get_by_stage($recordClass, \SilverStripe\Versioned\Versioned::LIVE);
            }
            else {
                $records = $recordClass::get();
            }

            if ($collection->ExcludedClasses) {
                $excludedClasses = json_decode($collection->ExcludedClasses, true);
                if ($excludedClasses) {
                    $records = $records->exclude('ClassName', array_values($excludedClasses));
                }
            }

            if ($recordClass::singleton()->hasField('ShowInSearch')) {
                $records = $records->exclude('ShowInSearch', 0);
            }

            // Get the total count if we haven't already
            if ($this->recordOffset === 0) {
                $this->addMessage(
                    _t(Collection::class . '.IMPORT_Indexing', "Indexing {name}, (Limit: {limit}, Timeout: {timeout})",
                        ['name' => $collection->Name, 'limit' => $limit, 'timeout' => $connection_timeout])
                );

                $count = $records->count();
                if ($count === 0) {
                    $this->addMessage(
                        '...' . _t(Collection::class . '.IMPORT_NoDocumentsFound', 'no documents found!')
                    );

                    // Move to the next collection
                    $this->currentCollectionIndex++;
                    $this->recordOffset      = 0;
                    $this->currentCollection = null;

                    return;
                }
            }

            // Get a batch of records
            $records    = $records->limit($limit, $this->recordOffset);
            $limitCount = $records->count();

            if ($limitCount == 0) {
                // Move to the next collection
                $this->currentCollectionIndex++;
                $this->recordOffset      = 0;
                $this->currentCollection = null;

                return;
            }

            $docs        = [];
            $fieldsArray = $collection->FieldsArray();

            foreach ($records as $record) {
                $data = [];
                if ($record->hasMethod('getTypesenseDocument')) {
                    $data = $record->getTypesenseDocument();
                }
                else {
                    $data = $collection->getTypesenseDocument($record, $fieldsArray);
                }
                if ($data) {
                    $docs[] = $data;
                }
            }

            $client->collections[$collection->Name]->documents->import($docs, ['action' => 'emplace']);

            $count = $records->count();
            $this->addMessage(
                '...' . _t(Collection::class . '.IMPORT_AddedDocumentsToCollection',
                    'added [{limitcount} / {count}] documents to {name}',
                    ['limitcount' => $this->recordOffset + $limitCount, 'count' => $count, 'name' => $collection->Name])
            );

            // Update the offset for the next batch
            $this->recordOffset += $limit;

            // Update job progress
            $this->currentStep = $this->currentCollectionIndex * 100 + ($this->recordOffset / $count * 100);

            // Check if we've processed all records in this collection
            if ($this->recordOffset >= $count) {
                // Move to the next collection
                $this->currentCollectionIndex++;
                $this->recordOffset      = 0;
                $this->currentCollection = null;
            }
        }

        /**
         * Find or create all collections
         *
         * @return array Array of Collection objects
         */
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
