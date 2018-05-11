<?php

namespace App\Services;
use Mail;

class StatusNotificationService
{

    public function notifyDown($task)
    {
        $this->fetchTaskData($task);

        $this->sendNotificationMail('emails.alarm');
    }

    public function notifyUp($task)
    {
        $this->fetchTaskData($task);

        $this->sendNotificationMail('emails.alarm');
    }

    private function fetchTaskData($task)
    {
        $this->task = $task;
        $this->user = \App\User::findOrFail($task->user_id);
    }

    private function sendNotificationMail($template)
    {
        $templateVariables = ['user' => $this->user, 'task' => $this->task];
        $emailRecipientAddress = $this->user->email;
        $emailRecipientName = $this->user->name;
        $nodeName = $this->task->node->name;

        Mail::send($template, $templateVariables, function ($m) use ($emailRecipientAddress, $emailRecipientName, $nodeName) {
            $m->to($emailRecipientAddress, $emailRecipientName)->subject($nodeName . ' is Offline!');
        });
    }

    private function sendNotifcationSms($template) 
    {
        if ($this->task->smsalarm == 0 && !empty($this->user->mobilenumber)) {
        }
    }
}
