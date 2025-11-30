<?php

namespace App\Console\Commands;

use App\Services\ClickUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClickUpSaveTasksAndSubTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ClickUpService $clickUp)
    {
        $pageIndex = 0;
        $tasksSubTasksFolderPath = 'clickup_backups-'.now()->format('Y-m').'/tasks_and_sub_tasks';
        $logsFolderPath = $clickUp->logsFolderPath();

        Storage::makeDirectory($tasksSubTasksFolderPath);
        $tasksSubTasksItemCount = 0;

        // Getting all tasks and subtasks IDs and saving to a text file
        do {
            $tasksSubTasks = $clickUp->getTasksAndSubtasksId($pageIndex);

            if (empty($tasksSubTasks)) {
                break;
            }

            foreach ($tasksSubTasks as $task) {
                $taskId = $task['id'];
                $taskFilePath = $tasksSubTasksFolderPath.'/tasksSubstasksId.txt';
                Storage::append($taskFilePath, $taskId);
                dump("task/subtask: {$taskId}");
                $tasksSubTasksItemCount++;
            }
            dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')."Task and Subtasks page completed: {$pageIndex}, tasks/subtasks: {$tasksSubTasksItemCount}");
            Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Task and Subtasks page completed: {$pageIndex}, tasks/subtasks: {$tasksSubTasksItemCount}");
            $pageIndex++;
        } while (! empty($tasksSubTasks));

        $getFileTasksSubtasksIdTxt = Storage::get($taskFilePath);
        $ids = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $getFileTasksSubtasksIdTxt)));
       
        $batchSize = 3;
        while (! empty($ids)) {
            $currentBatch = array_slice($ids, 0, $batchSize); // kunin yung first 10

            foreach ($currentBatch as $id) {

                $taskSubtask = $clickUp->getTasksAndSubtasks($id);
                $taskSubtaskFilePath = $tasksSubTasksFolderPath.'/'.$id.'.json';
                Storage::put($taskSubtaskFilePath, json_encode($taskSubtask, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Saved task/subtask JSON: {$id}");
                Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Saved task/subtask JSON: {$id}");

                // task/subtask attachments extraction and download
                $attachments = $clickUp->extractAttachments($taskSubtask, 'task/subtask');

                if (! empty($attachments)) {
                    Storage::put($tasksSubTasksFolderPath."/attachments/{$id}.json", json_encode($attachments, JSON_PRETTY_PRINT));
                    $jsonPath = $tasksSubTasksFolderPath."/attachments/{$id}.json";
                    $downloadDir = $tasksSubTasksFolderPath.'/attachments/downloads';
                    $clickUp->downloadAttachments($jsonPath, $downloadDir, 'task/subtask');

                    continue;
                }
            }

            dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s').'✅ Batch tasks and subtasks have been saved as separate JSON files.');
            Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s').' - Batch tasks and subtasks have been saved as separate JSON files.');

            // Save the most recent 25 parent-comments for each task and subtask
            foreach ($currentBatch as $id) {

                $comments = $clickUp->getTaskAndSubtaskComments($id);

                if (! empty($comments)) {
                    $commentsFilePath = $tasksSubTasksFolderPath.'/comments/'.$id.'/comments_parent.json';
                    Storage::put($commentsFilePath, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    $commentParentIdTxt = $tasksSubTasksFolderPath.'/comments/'.$id.'/comment_replies/comments_parent_id.txt';

                    foreach ($comments as $comment) {
                        Storage::append($commentParentIdTxt, $comment['id']);
                    }
                } else {
                    Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." -⚠️ No comments found for task {$id}");
                }

                Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - comment/s saved for task {$id}");

            }

            foreach ($currentBatch as $id) {

                $commentParentIdTxt = $tasksSubTasksFolderPath.'/comments/'.$id.'/comment_replies/comments_parent_id.txt';
                $getFileCommentParentIdTxt = Storage::get($commentParentIdTxt);
                $commentParentIds = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $getFileCommentParentIdTxt)));

                foreach ($commentParentIds as $parentId) {

                    $commentReplies = $clickUp->getTaskAndSubtaskReplyComments($parentId);
                    if (! empty($commentReplies)) {
                        $commentRepliesFilePath = $tasksSubTasksFolderPath.'/comments/'.$id.'/comment_replies/'.$parentId.'.json';
                        Storage::put($commentRepliesFilePath, json_encode($commentReplies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Saved task/subtask comment replies JSON: {$id} - parent comment ID: {$parentId}");
                        Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Saved comment replies - parent comment ID: {$parentId}");

                    } else {
                        Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - ⚠️ No reply comments found for comment {$parentId}");
                        Storage::deleteDirectory($tasksSubTasksFolderPath.'/comments/'.$id.'/comment_replies/');
                    }

                }
                Storage::delete($commentParentIdTxt);
            }
            // Remove processed IDs
            $ids = array_slice($ids, $batchSize);

            // Save remaining IDs back to file 
            Storage::put($taskFilePath, implode(PHP_EOL, $ids));

        }

    }
}
