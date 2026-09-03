<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class BackupController extends ApiController
{
    public function now(): JsonResponse
    {
        $dbFile = database_path('database.sqlite');
        $uploadsDir = storage_path('app/public/uploads');
        $backupBase = base_path('backups');
        $timestamp = gmdate('Ymd\THis\Z');
        $dir = "$backupBase/fge-$timestamp";

        if (! file_exists($dbFile)) {
            return $this->fail('database file not found', 500);
        }

        try {
            if (! is_dir($backupBase)) {
                mkdir($backupBase, 0755, true);
            }
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            copy($dbFile, "$dir/database.sqlite");
            if (is_dir($uploadsDir)) {
                $this->copyDir($uploadsDir, "$dir/uploads");
            }
            $this->prune($backupBase);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }

        return $this->json(['success' => true, 'path' => str_replace('\\', '/', $dir)]);
    }

    private function copyDir(string $src, string $dst): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $entry) {
            $target = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
            if ($entry->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($entry->getPathname(), $target);
            }
        }
    }

    private function prune(string $backupBase): void
    {
        $dirs = glob("$backupBase/fge-*") ?: [];
        sort($dirs);
        $excess = count($dirs) - 20;
        if ($excess > 0) {
            foreach (array_slice($dirs, 0, $excess) as $old) {
                if (is_dir($old)) {
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($old, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($it as $entry) {
                        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                    }
                    rmdir($old);
                }
            }
        }
    }
}