<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ClickUpService
{
    protected $apiUrlV2;

    protected $apiUrlV3;

    protected $token;

    protected $teamId;

    public function __construct()
    {
        $this->apiUrlV2 = config('services.clickup.api_url', env('CLICKUP_API_URL_V2'));
        $this->apiUrlV3 = config('services.clickup.api_url', env('CLICKUP_API_URL_V3'));
        $this->token = env('CLICKUP_API_TOKEN');
        $this->teamId = env('CLICKUP_TEAM_ID');
    }

    public function logsFolderPath()
    {
        return 'clickup_backups-'.now()->format('Y-m').'/logs.txt';
    }

    protected function getWithDelay($url)
    {
        sleep(1); // 1-second delay
        $response = Http::withOptions(['verify' => false])
            ->withHeaders(['Authorization' => $this->token])
            ->get($url);

        if ($response->failed()) {
            throw new \Exception("Failed request to {$url}: ".$response->body());
        }

        return $response->json();
    }

    public function getSpaces()
    {
        return $this->getWithDelay("{$this->apiUrlV2}/team/{$this->teamId}/space")['spaces'];
    }

    public function getFolders($spaceId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/space/{$spaceId}/folder")['folders'];
    }

    public function getFolderlessLists($spaceId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/space/{$spaceId}/list")['lists'];
    }

    public function getLists($folderId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/folder/{$folderId}/list")['lists'];
    }

    public function getTasksAndSubtasksId($pageIndex)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/team/{$this->teamId}/task?page={$pageIndex}&order_by=created&reverse=true&subtasks=true&include_closed=true")['tasks'];

    }

    public function getTasksAndSubtasks($taskSubtaskId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/task/{$taskSubtaskId}");

    }

    public function getTaskAndSubtaskComments($taskSubtaskId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/task/{$taskSubtaskId}/comment")['comments'];
    }

    public function getTaskAndSubtaskReplyComments($commentId)
    {
        return $this->getWithDelay("{$this->apiUrlV2}/comment/{$commentId}/reply")['comments'];
    }

    public function getDocs()
    {
        $allDocs = [];
        $limit = 100; // maximum per request
        $page = 0;

        do {
            $url = "{$this->apiUrlV3}/workspaces/{$this->teamId}/docs?limit={$limit}&page={$page}";
            $response = $this->getWithDelay($url);

            if (! isset($response['docs'])) {
                break; // no docs returned
            }

            $docs = $response['docs'];
            $allDocs = array_merge($allDocs, $docs);
            if ($page === 0) {
                $docsitem = count($docs) * 1;
            } else {
                $docsitem = count($docs) * $page;
            }

            $page++;
            dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." -  {$docsitem} Docs saved - {$page} page(s).");
            $logsFolderPath = $this->logsFolderPath();
            Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." -  {$docsitem} Docs saved - {$page} page(s).");

        } while (count($docs) === $limit); // loop until less than $limit docs returned

        return $allDocs;

    }

    public function getDocPages($doc_id)
    {
        return $this->getWithDelay("{$this->apiUrlV3}/workspaces/{$this->teamId}/docs/{$doc_id}/pages");

    }

    public function downloadAttachments($jsonPath, $downloadDir, $itemDescription)
    {
        if (! Storage::exists($jsonPath)) {

            throw new \Exception("JSON file not found: {$jsonPath}");
        }

        $data = json_decode(Storage::get($jsonPath), true);

        if (! is_array($data)) {
            throw new \Exception("Invalid JSON structure in {$jsonPath}");
        }

        Storage::makeDirectory($downloadDir);

        foreach ($data as &$item) {
            if (empty($item['attachments'])) {
                continue;
            }

            foreach ($item['attachments'] as &$attachment) {
                try {
                    $url = $attachment['url'] ?? null;
                    if (! $url) {
                        continue;
                    }

                    $filename = uniqid().'-'.basename(parse_url($url, PHP_URL_PATH));
                    $filePath = "{$downloadDir}/{$filename}";

                    $content = @file_get_contents($url);
                    if ($content === false) {
                        throw new \Exception("Failed to download: {$url}");
                    }

                    // save via Storage
                    Storage::put($filePath, $content);
                    $attachment['location'] = $filePath;

                } catch (\Exception $e) {
                    $attachment['location'] = null;
                    \Log::error("Error downloading {$url}: ".$e->getMessage());

                    continue;
                }

                sleep(1); // wait per download
            }
        }

        $logsFolderPath = $this->logsFolderPath();
        Storage::put($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        dump(now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Downloaded attachments for {$itemDescription} in {$jsonPath}");
        Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Attachments for the {$itemDescription} have been downloaded to  {$jsonPath}");

    }

    public function extractAttachments(array $data, $itemType, &$results = [])
    {
        if ($itemType === 'doc pages') {
            foreach ($data as $page) {
                $pageId = $page['id'];
                $content = $page['content'] ?? '';

                preg_match_all('/https:\/\/\w+\.p\.clickup\-attachments\.com[^\)\s"]+/', $content, $matches);

                if (! empty($matches[0])) {
                    $results[] = [
                        'id' => $pageId,
                        'attachments' => array_map(fn ($url) => [
                            'url' => str_replace('\/', '/', $url),
                            'location' => '',
                        ], $matches[0] ?? []),
                    ];

                    if (! empty($page['pages'])) {
                        $this->extractAttachments($data['pages'], 'doc pages', $results);
                    }
                }

            }
        }

        if ($itemType === 'task/subtask') {

            if (! empty($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    $url = $attachment['url'];
                    $results[] = [
                        'id' => $data['id'],
                        'attachments' => [[
                            'url' => str_replace('\/', '/', $url),
                            'location' => '',
                        ]],
                    ];
                }
            }

        }

        return $results;
    }
}
