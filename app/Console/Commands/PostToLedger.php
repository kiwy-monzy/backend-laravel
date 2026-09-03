<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Support\Posting;
use Modules\Expenses\Models\Expense;
use Modules\Invoicing\Models\Document;
use Modules\Invoicing\Models\Payment;

/**
 * Post existing documents, payments and expenses into the books.
 *
 * New records post themselves as they are saved. This is for everything that
 * already existed before there was anywhere to post it to — and for putting the
 * books back after a bulk load, which writes rows straight into the database
 * and so bypasses the model events entirely.
 */
class PostToLedger extends Command
{
    protected $signature = 'ledger:post
        {--org= : Organization slug, or all organizations when omitted}
        {--fresh : Remove entries this command posted before re-posting}
        {--limit=0 : Stop after this many records of each kind (0 = no limit)}';

    protected $description = 'Post documents, payments and expenses into the journal.';

    public function handle(): int
    {
        $organizations = $this->option('org')
            ? \App\Models\Organization::where('slug', $this->option('org'))->get()
            : \App\Models\Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No matching organization.');

            return self::FAILURE;
        }

        foreach ($organizations as $organization) {
            $this->postFor($organization);
        }

        return self::SUCCESS;
    }

    private function postFor(\App\Models\Organization $organization): void
    {
        $this->line("<options=bold>{$organization->name}</>");

        if ($this->option('fresh')) {
            $removed = \Modules\Accounting\Models\JournalEntry::where('organization_id', $organization->id)
                ->whereIn('source_type', ['document', 'payment', 'expense'])
                ->get();

            foreach ($removed as $entry) {
                $entry->lines()->delete();
                $entry->delete();
            }

            $this->line('  cleared ' . $removed->count() . ' previously posted entr(ies)');
        }

        $limit = (int) $this->option('limit');

        $kinds = [
            'documents' => [
                Document::where('organization_id', $organization->id)
                    ->whereNotIn('doc_type', Posting::NEVER_POSTS)
                    ->whereNotIn('status', ['draft', 'void']),
                fn ($r) => Posting::document($r),
            ],
            'payments' => [
                Payment::where('organization_id', $organization->id),
                fn ($r) => Posting::payment($r),
            ],
            'expenses' => [
                Expense::where('organization_id', $organization->id)
                    ->whereNotIn('status', ['draft', 'rejected']),
                fn ($r) => Posting::expense($r),
            ],
        ];

        foreach ($kinds as $label => [$query, $post]) {
            $total = (clone $query)->count();

            if ($total === 0) {
                $this->line("  {$label}: none");

                continue;
            }

            $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
            $done = 0;
            $failed = 0;
            $firstError = null;

            // Chunked so a hundred thousand documents do not become a hundred
            // thousand models held at once.
            (clone $query)->chunkById(500, function ($records) use ($post, &$done, &$failed, &$firstError, $bar, $limit) {
                foreach ($records as $record) {
                    try {
                        $post($record);
                        $done++;
                    } catch (\Throwable $e) {
                        $failed++;
                        // Keep the first reason: a run that reports only a count
                        // tells you something is wrong and nothing about what.
                        $firstError ??= $e->getMessage();
                    }

                    $bar->advance();

                    if ($limit > 0 && $done + $failed >= $limit) {
                        return false;
                    }
                }

                return true;
            });

            $bar->finish();
            $this->newLine();
            $this->line("  {$label}: posted {$done}" . ($failed ? ", <fg=red>{$failed} refused</>" : ''));

            if ($firstError) {
                $this->line('    <fg=yellow>first refusal:</> ' . \Illuminate\Support\Str::limit($firstError, 160));
            }
        }
    }
}
