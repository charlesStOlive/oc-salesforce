<?php
/**
 * Copyright (c) 2018 Viamage Limited
 * All Rights Reserved
 */

namespace Waka\SalesForce\Jobs;

use Waka\Wakajob\Classes\JobManager;
use Waka\Wakajob\Classes\RequestSender;
use Waka\Wakajob\Contracts\WakajobQueueJob;
use Winter\Storm\Database\Model;
use Viamage\CallbackManager\Models\Rate;
use Waka\SalesForce\Classes\SalesForceImport;
use Waka\Utils\Classes\DataSource;

/**
 * Class SendRequestJob
 *
 * Sends POST requests with given data to multiple target urls. Example of Wakajob Job.
 *
 * @package Waka\Wakajob\Jobs
 */
class ImportSf implements WakajobQueueJob
{
    /**
     * @var int
     */
    public $jobId;

    /**
     * @var JobManager
     */
    public $jobManager;

    /**
     * @var array
     */
    private $data;

    /**
     * @var bool
     */
    private $updateExisting;

    /**
     * @var int
     */
    private $chunk;

    /**
     * @var string
     */
    private $table;

    /**
     * @var int
     */
    private $loop;

    /**
     * @var int
     */
    private $JobManager;

    /**
     * @param int $id
     */
    public function assignJobId(int $id)
    {
        $this->jobId = $id;
    }

    /**
     * SendRequestJob constructor.
     *
     * We provide array with stuff to send with post and array of urls to which we want to send
     *
     * @param array  $data
     * @param string $model
     * @param bool   $updateExisting
     * @param int    $chunk
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->updateExisting = true;
        
    }

    /**
     * Job handler. This will be done in background.
     *
     * @param JobManager $jobManager
     */
    public function handle(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
        /**
         * travail preparatoire sur les donnes
         */
        $sfCode = $this->data['productorId'];
        $mainDate = $this->data['options']['main_date'] ?? null;
        $sfImporter = SalesForceImport::find($sfCode)->changeMainDate($mainDate);
        $firstQuery = $sfImporter->executeQuery();
        $totalSize = $firstQuery['totalSize'];
        //trace_log($totalSize);
        $this->chunk = ceil($totalSize / 2000);
        //trace_log($this->chunk);

        /**
         * We initialize database job. It has been assigned ID on dispatching,
         * so we pass it together with number of all elements to proceed (max_progress)
         */
        $this->loop = 1;
        $this->jobManager->startJob($this->jobId, $this->chunk);
        $send = 0;
        $scopeError = 0;
        $skipped = 0;
        try {
            $sfImporter->handleRequest($firstQuery['records']);
            //trace_log($this->loop);
            $this->jobManager->updateJobState($this->jobId, $this->loop);
            $next = $firstQuery['next'];
            if($next) {
                $this->launchNext($sfImporter, $next);
            } else {
                $this->closejob($sfImporter);
                
                
                
            }
       } catch (\Exception $ex) {
            /**/trace_log($ex->getMessage());
            $this->jobManager->failJob($this->jobId, ['error' => $ex->getMessage()]);  
        }
    }

    private function launchNext($sfImporter, $next) {
        $newNext = null;
        if ($this->jobManager->checkIfCanceled($this->jobId)) {
            $this->jobManager->failJob($this->jobId);
        } else {
            $newNext = $sfImporter->sendNextQuery($next);
            $this->loop++;
            //trace_log($this->loop);
            $this->jobManager->updateJobState($this->jobId, $this->loop);
            if($newNext) {
                $this->launchNext($sfImporter, $newNext);
            } else {
                $this->closejob($sfImporter);
            }
        }
    }

    private function closeJob($sfImporter) {
        trace_log('fin du job');
        $closingEvent = $sfImporter->getConfig('closingEvent');
        trace_log('closingEvent : ' .$sfImporter->getConfig('closingEvent'));
        if($closingEvent) {
            \Event::fire($closingEvent, [$sfImporter]);
        }
        
        $sfImporter->updateAndCloseLog();
        $this->jobManager->completeJob(
            $this->jobId,
            [
            'Message' => \Lang::get('waka.salesforce::lang.job.title'),
            ]
        );
        
    }
}
