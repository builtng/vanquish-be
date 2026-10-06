<?php

use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    // return view('welcome');
});

Route::get('/templates/{filename}', function ($filename) {
    $cleanFilename = basename($filename);
    $path = storage_path('app/templates/' . $cleanFilename);
    if (!file_exists($path)) {
        $path = public_path('templates/' . $cleanFilename);
    }
    if (!file_exists($path)) {
        $path = storage_path('app/public/shared_documents/' . $cleanFilename);
    }
    if (!file_exists($path)) {
        abort(404, 'Template not found');
    }
    return response()->download($path, $cleanFilename);
})->where('filename', '[A-Za-z0-9\-_.]+');
