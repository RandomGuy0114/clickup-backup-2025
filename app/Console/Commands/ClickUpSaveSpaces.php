<?php

namespace App\Console\Commands;

use App\Services\ClickUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ClickUpSaveSpaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle(ClickUpService $clickUp)
    {
        $spaceResponse = $clickUp->getSpaces(); // raw JSON string o array
        $logsFolderPath = $clickUp->logsFolderPath();
        // 1. Siguraduhin na array
        if (is_string($spaceResponse)) {
            $spaces = json_decode($spaceResponse, true); // array na ngayon
        } else {
            $spaces = $spaceResponse;
        }

        // 2. Create monthly folder
        $spacesFolderPath = 'clickup_backups-'.now()->format('Y-m').'/spaces';
        $foldersFolderPath = 'clickup_backups-'.now()->format('Y-m').'/folders';
        $listFolderPath = 'clickup_backups-'.now()->format('Y-m').'/lists';
        Storage::makeDirectory($spacesFolderPath);
        Storage::makeDirectory($foldersFolderPath);
        Storage::makeDirectory($listFolderPath);

        // 3. Loop through each space
        foreach ($spaces as $space) {
            $spaceId = $space['id'];

            try {
                // 3a. Save space JSON
                $spaceFilePath = $spacesFolderPath.'/'.$spaceId.'.json';
                Storage::put($spaceFilePath, json_encode($space, JSON_PRETTY_PRINT));
                dump("space: {$spaceId}");
                Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - space: {$spaceId} saved");

                // 3b. Get folders for this space
                $foldersResponse = $clickUp->getFolders($spaceId);
                $folders = is_string($foldersResponse) ? json_decode($foldersResponse, true) : $foldersResponse;

                $spaceFoldersPath = $foldersFolderPath.'/'.$spaceId;
                Storage::makeDirectory($spaceFoldersPath);

                foreach ($folders as $folder) {
                    try {
                        $folderFilePath = $spaceFoldersPath.'/'.$folder['id'].'.json';
                        Storage::put($folderFilePath, json_encode($folder, JSON_PRETTY_PRINT));
                        dump("folder: {$folder['id']}");
                        Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - folder: {$folder['id']} saved for space {$spaceId}");

                        // Get lists per folder
                        $listsResponse = $clickUp->getLists($folder['id']);
                        $lists = is_string($listsResponse) ? json_decode($listsResponse, true) : $listsResponse;

                        foreach ($lists as $list) {
                            $listFilePath = $listFolderPath.'/'.$list['id'].'.json';
                            Storage::put($listFilePath, json_encode($list, JSON_PRETTY_PRINT));
                            Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - list: {$list['id']} saved for folder {$folder['id']}");

                            dump("list: {$list['id']}");
                        }

                    } catch (\Exception $eFolder) {
                        $this->error("Failed folder {$folder['id']} in space {$spaceId}: ".$eFolder->getMessage());

                        continue; // skip this folder
                    }
                }

                // Folderless lists
                try {
                    $folderlessLists = $clickUp->getFolderlessLists($spaceId);

                    foreach ($folderlessLists as $list) {
                        $listFilePath = $listFolderPath.'/'.$list['id'].'.json';
                        Storage::put($listFilePath, json_encode($list, JSON_PRETTY_PRINT));
                        Storage::append($logsFolderPath, now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')." - folderless list: {$list['id']} saved for space {$spaceId}");

                        dump("folderless list: {$list['id']}");
                    }

                } catch (\Exception $eList) {
                    $this->error("Failed folderless lists for space {$spaceId}: ".$eList->getMessage());
                }

            } catch (\Exception $eSpace) {
                $this->error("Failed space {$spaceId}: ".$eSpace->getMessage());

                continue; // next space
            }
        }

        dump('✅ All spaces have been as separate JSON files.');
        Artisan::call('app:docs');

    }
}
