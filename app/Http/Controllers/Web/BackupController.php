<?php

namespace App\Http\Controllers\Web;

use App\Models\Organization;
use App\Models\StorageCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A one-file backup of an organization: its data and its files.
 *
 * **This is the payoff of per-organization storage.** Because everything a
 * tenant owns lives under one directory and every table carries its
 * `organization_id`, a backup is a walk of one folder and a filtered dump of a
 * known set of tables — not a fragile "export everything and hope". The result
 * is a zip an owner can keep, or hand to us if they leave.
 *
 * Restricted to admins and managers: a backup is the whole organization in one
 * file, which is not something an employee should be able to pull.
 */
class BackupController extends AdminController
{
    /** Tables whose rows are scoped by `organization_id` and belong in a backup. */
    private const SCOPED_TABLES = [
        'organization_members', 'module_access', 'organization_modules',
        'websites', 'content_sections', 'gallery_images', 'donations', 'volunteers',
        'messages', 'uploads', 'storage_collections',
        'crm_customers', 'crm_leads',
        'invoicing_items', 'invoicing_documents', 'invoicing_payments',
        'expenses_records', 'inventory_stock', 'projects_records', 'purchasing_orders',
        'fulfillment_shipments', 'billing_subscriptions', 'bookings_appointments',
        'procurement_requests', 'accounting_accounts', 'org_departments',
        'assets_records', 'support_tickets', 'cart_orders', 'workerly_shifts',
        'contracts', 'contract_activities',
    ];

    public function index()
    {
        $this->assertCanBackup();

        $org = $this->currentOrganization();

        return view('backup.index', [
            'organization' => $org,
            'storageBytes' => $org ? StorageCollection::organizationBytes($org->id) : 0,
            'tableCount' => count(self::SCOPED_TABLES),
        ]);
    }

    /**
     * Streams a zip of everything the organization owns.
     *
     * `include_files` is optional because on a large storage folder the data
     * dump alone is the fast, frequent backup and the files are the slow,
     * occasional one — forcing them together would make nobody take either.
     */
    public function download(Request $request)
    {
        $this->assertCanBackup();

        $org = $this->currentOrganization();

        if (! $org) {
            return back()->with('error', __('No organization to back up.'));
        }

        $includeFiles = $request->boolean('include_files');

        $tmp = tempnam(sys_get_temp_dir(), 'bak');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        // A manifest so a restore knows what it is looking at without guessing.
        $zip->addFromString('manifest.json', json_encode([
            'organization' => $org->only(['id', 'name', 'slug', 'plan']),
            'exported_at' => now()->toRfc3339String(),
            'exported_by' => $this->me()->username,
            'schema' => 'fge-backup-1',
            'includes_files' => $includeFiles,
        ], JSON_PRETTY_PRINT));

        $zip->addFromString('data/organization.json', json_encode(
            Organization::find($org->id)->toArray(), JSON_PRETTY_PRINT,
        ));

        foreach (self::SCOPED_TABLES as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)->where('organization_id', $org->id)->get();
            $zip->addFromString("data/$table.json", $rows->toJson(JSON_PRETTY_PRINT));
        }

        if ($includeFiles) {
            $disk = Storage::disk('public');
            $root = 'uploads/' . $org->id;
            foreach ($disk->allFiles($root) as $file) {
                // `getRealPath` streams from disk rather than loading every
                // file into memory, so a large storage folder does not blow the
                // request's memory limit.
                $zip->addFile($disk->path($file), 'files/' . ltrim(\Illuminate\Support\Str::after($file, $root . '/'), '/'));
            }
        }

        $zip->close();

        $name = \Illuminate\Support\Str::slug($org->name) . '-backup-' . now()->format('Y-m-d-His') . '.zip';

        return response()->download($tmp, $name)->deleteFileAfterSend(true);
    }

    private function assertCanBackup(): void
    {
        // Admins and managers only — a backup is the entire organization.
        if (! in_array($this->me()->orgRole(), ['admin', 'manager'], true)) {
            throw new AccessDeniedHttpException('Only an administrator or manager can back up the organization.');
        }
    }
}
