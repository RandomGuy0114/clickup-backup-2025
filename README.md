ClickUp Backup Script Guide

These scripts will back up your ClickUp data, including spaces, lists, folderless lists, tasks, subtasks, subtask comments and replies, doc pages, and attachments on subtasks and doc pages.

1. Setup .env File

Create a .env file in your project and add the following configurations:
CLICKUP_API_TOKEN= (Log in to ClickUp → Settings → Apps to get your token)
CLICKUP_TEAM_ID=123456789 (Example: https://app.clickup.com/123456789/home
 — copy the team ID from your ClickUp URL)
CLICKUP_API_URL_V2=https://api.clickup.com/api/v2

CLICKUP_API_URL_V3=https://api.clickup.com/api/v3

2. Configure ClickUp Service

In app\Services\ClickUpService.php, you can optionally enable extra security by setting 'verify' to true:
$response = Http::withOptions(['verify' => false])->withHeaders(['Authorization' => $this->token])->get($url);

3. Run the Backup

Open your terminal and execute the following command to start the backup process:
php artisan app:run

4. Backup Location

All backups will be saved in:
storage\app\private\clickup_backups-yyyy-mm

-------------------------------------------------
The core script files are located here:
- app\Console\Commands
- app\Services\ClickUpService.php
-------------------------------------------------

Backup Folder Structure

The backup folder is named clickup_backups-yyyy-mm and contains the following structure:

1. doc_pages

    docs_attachment

        downloads → Contains all downloaded file attachments for doc pages.

        {doc_id}.json → Stores the data of the doc page attachment, including page ID, attachment URL, and downloaded file path.

    {doc_id}.json → Each JSON file is named after the doc ID and contains the data of the doc pages.

2. folders

    {space_id}

        {folder_id}.json → Contains folder data for each folder in the space.

3. lists

    {list_id}.json → Contains data of each list.

4. spaces

    {space_id}.json → Contains data of each space.

5. tasks_and_sub_tasks

    attachments

        downloads → Contains downloaded file attachments for subtasks/tasks. Note: if a comment or reply has a file, it will automatically be saved here as a sub/task attachment.

        {subtask_id}.json → Stores sub/task ID, attachment URL, and the path of the downloaded file.

    comments

        {subtask_id}

            comment_replies → Contains replies to parent comments. If this folder is missing, it means the sub/task has no replies.

                {comment_parent_id}.json → Contains all replies to the parent comment.

            comments_parent.json → Contains all parent comments of the sub/task.

    {subtask_id}.json → Contains all sub/task data.

6. logs.txt → Records the backup process logs.

---------------------------------------------------------------------
References
api - https://developer.clickup.com/reference/getaccesstoken 

laravel - https://laravel.com/docs/12.x