<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\StatusNotificationService;

class checkTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkTasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all Tasks and Trigger the in a Intervall';

    private $statusNotificationService;

    public function __construct(StatusNotificationService $statusNotificationService)
    {
        parent::__construct();
        $this->statusNotificationService = $statusNotificationService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tasks = \App\Task::where(['active' => 0])->get();

        $now = \Carbon\Carbon::now();

        foreach($tasks as $task) {
            $this->task = $task;
            $this->checkTask();
        }
    }

    private function checkTask()
    {
        Log::debug('Running task #' . $this->task->id . ' for node ' . $this->task->node->name);
        $this->nodestat = $this->task->nodestat;

        if (!$this->nodestat) {
            Log::warning('No node stat found for node ' . $this->task->node->name);
            return;
        }

        if ($this->nodestat->isonline) {
            $this->checkOnlineNode();
        } else {
            $this->checkOfflineNode();
        }

        $this->task->save();
    }

    private function checkOnlineNode()
    {
        Log::debug('Node is online');
        $this->markTaskAsOnline();

        if ($this->isTaskMarkedAsOffline()) {
            // TODO: avoid sending notification when node was not offline since at least check interval
            $this->statusNotificationService->notifyUp($this->task);
        }

        $this->setTaskLastRunTimeToNow();
    }

    private function checkOfflineNode()
    {
        Log::debug('Node is offline');

        if ($this->hasTaskAlertBeenSentForCurrentOfflinePeriod()) {
            Log::debug('Node is offline and alert has already been sent');
            return;
        }

        $this->markTaskAsOfflineSinceNow();

        if ($this->isTaskOfflineSinceAtLeastCheckInterval()) {
            $this->statusNotificationService->notifyDown($this->task);
            $this->addOfflineAlertToDatabase();
        }

        $this->setTaskLastRunTimeToNow();
    }

    private function addOfflineAlertToDatabase()
    {
        \App\Alert::insert(['task_id' => $this->task->id]);
        $this->setTaskLastAlertTimeToNow();
    }

    public function markTaskAsOnline()
    {
        $this->task->offlinesince = null;
    }

    /**
     * Mark this task as being offline since now.
     *
     * If the task is already offline, this function does nothing.
     * In particular, it will not overwrite the time since when the
     * node is offline.
     */
    public function markTaskAsOfflineSinceNow() 
    {
        if ($this->isTaskMarkedAsOffline()) {
            Log::debug('markTaskAsOfflineSinceNow: Node is already marked as offline');
            return;
        }

        $this->task->offlinesince = \Carbon\Carbon::now();
    }

    public function isTaskMarkedAsOffline()
    {
        return !empty($this->task->offlinesince);
    }

    public function isTaskOfflineSinceAtLeastCheckInterval()
    {
        if (!$this->task->offlinesince) {
            return;
        }

        $now = \Carbon\Carbon::now();

        $checkdate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $this->task->offlinesince);
        $intervall = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $this->task->intervall);
        $checkdate->addHours($intervall->hour)->addMinutes($intervall->minute);

        return $checkdate->lte($now);
    }

    public function hasTaskAlertBeenSentForCurrentOfflinePeriod()
    {
        if ($this->task->lastalert == null) {
            return false;
        }

        return $this->task->lastrun == $this->task->lastalert;
    }

    public function setTaskLastAlertTimeToNow()
    {
        $this->task->lastalert = \Carbon\Carbon::now();
    }

    public function setTaskLastRunTimeToNow()
    {
        $this->task->lastrun = \Carbon\Carbon::now();
    }

}
