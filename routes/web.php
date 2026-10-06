<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    // return view('welcome');
});

Route::get('/run-migrations-secret', function (\Illuminate\Http\Request $request) {
    if ($request->query('token') !== 'vqt_secret_migrate_2026') {
        return response('Unauthorized', 401);
    }
    try {
        Artisan::call('migrate', ['--force' => true]);
        return 'Migrations run successfully: <br><pre>' . Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Error running migrations: ' . $e->getMessage();
    }
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
