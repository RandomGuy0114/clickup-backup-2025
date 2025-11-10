<?php

namespace App\Console\Commands;

use App\Services\ClickUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ClickUpSaveDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:docs';

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
        $logsFolderPath = $clickUp->logsFolderPath();
        $docs = $clickUp->getDocs(); // raw JSON string o array

        $docsPagesFolderPath = 'clickup_backups-'.now()->format('Y-m').'/doc_pages';
        Storage::makeDirectory($docsPagesFolderPath);

        foreach ($docs as $doc) {
            try {
                $docId = $doc['id'];

                $pages = $clickUp->getDocPages($docId);
                Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - Doc-pages saved for Doc {$docId}");

                $pageFilePath = $docsPagesFolderPath.'/'.$docId.'.json';
                Storage::put($pageFilePath, json_encode($pages, JSON_PRETTY_PRINT));

                dump("doc pages for: {$docId}");
                $attachments = $clickUp->extractAttachments($pages, 'doc pages');
                if (! empty($attachments)) {
                    Storage::put($docsPagesFolderPath."/docs_attachment/{$docId}.json", json_encode($attachments, JSON_PRETTY_PRINT));
                    $jsonPath = $docsPagesFolderPath."/docs_attachment/{$docId}.json";
                    $downloadDir = $docsPagesFolderPath.'/docs_attachment/downloads';

                    $clickUp->downloadAttachments($jsonPath, $downloadDir, 'doc pages');

                    continue;
                }

            } catch (\Exception $e) {
                $this->error("Failed to save pages for doc {$docId}: ".$e->getMessage());

                continue;
            }
        }

        Artisan::call('app:ts');

    }
}
