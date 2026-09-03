<?php

namespace App\Services;

use App\Models\ContentSection;
use App\Models\GalleryImage;
use App\Models\Message;
use App\Models\User;
use App\Models\Website;
use Carbon\Carbon;

class LegacyImporter
{
    public function __construct(private string $dbPath)
    {
    }

    public function import(): array
    {
        if (! is_file($this->dbPath)) {
            throw new \RuntimeException("Database file not found: $this->dbPath");
        }

        $legacy = new \SQLite3($this->dbPath);
        $stats = [
            'users' => $this->importUsers($legacy),
            'content_sections' => $this->importContent($legacy),
            'gallery_images' => $this->importGallery($legacy),
            'messages' => $this->importMessages($legacy),
        ];
        $legacy->close();

        return $stats;
    }

    private function importUsers(\SQLite3 $legacy): int
    {
        $q = $legacy->query('SELECT * FROM users');
        $count = 0;
        while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
            User::where('username', $row['username'])->where('id', '!=', $row['id'])->delete();

            $existing = User::find($row['id']);

            // **Never let the legacy role win.** The old server had one role
            // column doing the job the three tiers now split up, so importing
            // it verbatim demoted the installation's system admin back to an
            // organization owner — which is exactly what happened the first
            // time this ran. An account that already exists keeps the role it
            // has; only a genuinely new one is assigned from the legacy value,
            // and then only into the new vocabulary.
            $role = $existing?->role ?? match (strtolower(trim((string) $row['role']))) {
                'owner', 'superadmin', 'super_admin' => 'owner',
                default => 'member',
            };

            User::updateOrCreate(['id' => $row['id']], [
                'username' => $row['username'],
                'email' => $row['email'],
                'password_hash' => $row['password_hash'],
                'role' => $role,
                'active' => (bool) $row['active'],
                'profile_image' => null,
                'website_id' => Website::FGE_WEBSITE_ID,
                'created_at' => $this->date($row['created_at']),
                'updated_at' => $this->date($row['updated_at']),
            ]);
            $count++;
        }
        return $count;
    }

    private function importContent(\SQLite3 $legacy): int
    {
        $q = $legacy->query('SELECT section, data FROM site_content');
        $count = 0;
        while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
            $data = json_decode($row['data'], true);
            if (! is_array($data)) {
                continue;
            }
            ContentSection::updateOrCreate(
                ['website_id' => Website::FGE_WEBSITE_ID, 'section' => $row['section']],
                ['data' => $this->rewriteUploadPaths($data)]
            );
            $count++;
        }
        return $count;
    }

    private function importGallery(\SQLite3 $legacy): int
    {
        $q = $legacy->query("SELECT data FROM site_content WHERE section = 'gallery'");
        $row = $q->fetchArray(SQLITE3_ASSOC);
        if (! $row) {
            return 0;
        }
        $gallery = json_decode($row['data'], true);
        $images = $gallery['images'] ?? [];
        $count = 0;
        foreach ($images as $i => $img) {
            if (empty($img['id']) || empty($img['url'])) {
                continue;
            }
            GalleryImage::updateOrCreate(['id' => $img['id']], [
                'website_id' => Website::FGE_WEBSITE_ID,
                'url' => $this->rewriteUploadPaths($img['url']),
                'caption' => $img['caption'] ?? '',
                'disabled' => (bool) ($img['disabled'] ?? false),
                'created_at' => now()->addMinutes($i),
                'updated_at' => now()->addMinutes($i),
            ]);
            $count++;
        }
        return $count;
    }

    private function importMessages(\SQLite3 $legacy): int
    {
        $q = $legacy->query('SELECT * FROM messages');
        $count = 0;
        while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
            Message::updateOrCreate(['id' => $row['id']], [
                'website_id' => $row['website_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'] ?? '',
                'subject' => $row['subject'] ?? '',
                'message' => $row['message'],
                'status' => $row['status'] ?: 'pending',
                'is_read' => in_array($row['status'], ['read', 'archived'], true),
                'created_at' => $this->date($row['created_at']),
            ]);
            $count++;
        }
        return $count;
    }

    private function rewriteUploadPaths(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace('/uploads/', '/storage/uploads/', $value);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->rewriteUploadPaths($v), $value);
        }
        return $value;
    }

    private function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}