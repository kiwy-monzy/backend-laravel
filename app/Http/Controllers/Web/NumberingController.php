<?php

namespace App\Http\Controllers\Web;

use App\Support\Sequences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * How the organization's references are shaped.
 *
 * The numbers themselves are allocated automatically and never typed; what an
 * organization does get to decide is the prefix and the width — `EXP-000123`
 * against `FGE/EXP/123`. That is a standing decision about the organization's
 * own paperwork, so it sits with the owner and the system administrator rather
 * than with whoever happens to be raising the record.
 */
class NumberingController extends AdminController
{
    public function edit()
    {
        $this->assertMayConfigure();

        return view('numbering.edit', [
            'organization' => $this->me()->organization,
            'sequences' => Sequences::all($this->me()->organization_id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertMayConfigure();

        $organizationId = $this->me()->organization_id;

        foreach (Sequences::KINDS as $key => $spec) {
            $prefix = strtoupper(trim((string) $request->input("prefix.$key", '')));
            $padding = (int) $request->input("padding.$key", $spec['padding']);

            // A prefix is a label, not free text: anything but letters, digits
            // and a separator would end up in filenames and exports.
            $prefix = preg_replace('/[^A-Z0-9_\-\/]/', '', $prefix) ?? '';

            DB::table('sequences')->updateOrInsert(
                ['organization_id' => $organizationId, 'key' => $key],
                [
                    'prefix' => mb_substr($prefix, 0, 12),
                    'padding' => max(1, min(10, $padding)),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        return back()->with('status', __('Numbering updated.'));
    }

    /**
     * Only the people who answer for the organization's paperwork.
     *
     * Deliberately narrower than "may edit records": changing a prefix changes
     * every reference raised from here on, and that is not an editing decision.
     */
    private function assertMayConfigure(): void
    {
        if (! $this->me()->isOwner() && $this->me()->orgRole() !== 'admin') {
            throw new AccessDeniedHttpException('Only an owner or organization admin can change numbering.');
        }
    }
}
