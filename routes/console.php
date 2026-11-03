<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::call(function () {
    $disk = Storage::disk('local');

    // Clean up abandoned final PDFs older than 1 day
    if ($disk->exists('secure_pdfs')) {
        $files = $disk->files('secure_pdfs');
        foreach ($files as $file) {
            if ($disk->lastModified($file) < Carbon::now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    // Clean up orphaned upload files (e.g. if the system crashed during upload)
    if ($disk->exists('uploads')) {
        $files = $disk->files('uploads');
        foreach ($files as $file) {
            if ($disk->lastModified($file) < Carbon::now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    // Clean up orphaned temp directories (e.g. if queue crashed and didn't trigger batch catch)
    $directories = $disk->directories('');
    foreach ($directories as $directory) {
        if (str_starts_with($directory, 'temp_renders_')) {
            $lastModified = $disk->lastModified($directory);
            if ($lastModified < Carbon::now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($directory);
            }
        }
    }
})->daily()->name('cleanup:orphaned-files');
