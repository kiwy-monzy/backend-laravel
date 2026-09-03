<?php

namespace App\Http\Controllers\Web;

use App\Support\Access;
use App\Support\ExportRegistry;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exports a dataset to Excel or CSV.
 *
 * **Export is its own permission, checked here for every source.** It is the
 * operation an organization most wants to hold back — a departing salesperson
 * walking off with the customer list is the exact risk — so a role without
 * `export` can read a list on screen but cannot pull it out of the building.
 * The source is also gated by module access, so this cannot become a way to
 * reach a module the member has no other route to.
 */
class ExportController extends AdminController
{
    public function index()
    {
        return view('export.index', [
            'sources' => $this->availableSources(),
            'canExport' => $this->me()->canInModule('export'),
        ]);
    }

    public function run(Request $request, string $source)
    {
        $spec = ExportRegistry::find($source);

        if (! $spec) {
            throw new NotFoundHttpException('No such export.');
        }

        $this->authorize($spec);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $rows = $this->rows($spec);
        $filename = $this->filename($source, $format);

        return $format === 'csv'
            ? $this->csv($spec, $rows, $filename)
            : $this->xlsx($spec, $rows, $filename);
    }

    /** The sources this member may actually export, for the index page. */
    private function availableSources(): array
    {
        $organization = $this->me()->organization;
        $canExport = $this->me()->canInModule('export');

        return array_filter(
            ExportRegistry::sources(),
            fn (array $spec) => $canExport
                && $organization
                && $organization->allowsModule($this->me()->orgRole(), $spec['module']),
        );
    }

    private function authorize(array $spec): void
    {
        if (! $this->me()->canInModule('export')) {
            throw new AccessDeniedHttpException(sprintf(
                '%s cannot export data.',
                Access::roleLabel($this->me()->orgRole()),
            ));
        }

        $organization = $this->me()->organization;

        if (! $organization || ! $organization->allowsModule($this->me()->orgRole(), $spec['module'])) {
            throw new AccessDeniedHttpException('Your role cannot reach that data.');
        }
    }

    /** @return \Illuminate\Support\Collection */
    private function rows(array $spec)
    {
        return $spec['model']::query()
            ->where('organization_id', $this->me()->organization_id)
            ->when($spec['with'] ?? null, fn ($q, $with) => $q->with($with))
            ->orderBy($spec['order'] ?? 'created_at', 'desc')
            ->get();
    }

    private function xlsx(array $spec, $rows, string $filename)
    {
        $sheet = (new Xlsx($spec['label']))->headers($spec['headers']);

        foreach ($rows as $row) {
            $sheet->row(($spec['row'])($row));
        }

        return $sheet->download($filename);
    }

    private function csv(array $spec, $rows, string $filename)
    {
        return response()->streamDownload(function () use ($spec, $rows) {
            $out = fopen('php://output', 'w');
            // The BOM is what makes Excel read a UTF-8 CSV as UTF-8 rather than
            // mangling every accented name.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $spec['headers']);
            foreach ($rows as $row) {
                fputcsv($out, ($spec['row'])($row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filename(string $source, string $format): string
    {
        $org = \Illuminate\Support\Str::slug($this->me()->organization?->name ?? 'export');

        return "{$org}-{$source}-" . now()->format('Y-m-d') . '.' . $format;
    }
}
