<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exports\DatabaseBackup;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Download every shop's data as INSERT statements, to paste into the SQL
     * editor of a database that has already been migrated.
     *
     * Streamed line by line, so the size of the file never depends on memory.
     */
    public function __invoke(DatabaseBackup $backup): StreamedResponse
    {
        $filename = $backup->filename();

        return response()->streamDownload(function () use ($backup): void {
            $handle = fopen('php://output', 'w');

            foreach ($backup->lines() as $line) {
                fwrite($handle, $line."\n");
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
