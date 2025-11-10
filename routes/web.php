<?php

use Illuminate\Support\Facades\Route;
use App\Services\ClickUpService;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clickup/spaces', function (ClickUpService $clickup) {

    $data = $clickup->getSpaces();
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/folders', function (ClickUpService $clickup) {

    $data = $clickup->getFolders(90162144436);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/folderless_lists', function (ClickUpService $clickup) {

    $data = $clickup->getFolderlessLists(90162144436);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/lists', function (ClickUpService $clickup) {

    $data = $clickup->getLists(90162916103);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/docs', function (ClickUpService $clickup) {

    $data = $clickup->getDocs(90162144436);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/docs_pages', function (ClickUpService $clickup) {

    $data = $clickup->getDocPages('8cpwd14-536');
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/tasks', function (ClickUpService $clickup) {

    $data = $clickup->getTasksAndSubtasksId(0);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/tasks_subtasks', function (ClickUpService $clickup) {

    $data = $clickup->getTasksAndSubtasks("86cwwj9zf");
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/comments', function (ClickUpService $clickup) {

    $data = $clickup->getTaskAndSubtaskComments("86cwwj9zf");
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});

Route::get('/clickup/comments_reply', function (ClickUpService $clickup) {

    $data = $clickup->getTaskAndSubtaskReplyComments(90160145112591);
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
});