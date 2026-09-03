<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Contracts\Models\GccClause;

/**
 * Turns the GCC text into the clause library.
 *
 * The source is one long run of text with no line structure — the clauses are
 * marked only by "12. Contractor" style headings and the five part headings
 * between them. Splitting on those headings is therefore the whole job, and
 * doing it here (rather than by hand) means a corrected source can simply be
 * re-imported.
 */
class ImportGccClauses extends Command
{
    protected $signature = 'gcc:import
        {file=contract.txt : Path to the GCC text, relative to the project root}
        {--standard=mow-gcc : Which standard these clauses belong to}';

    protected $description = 'Load the General Conditions of Contract into the clause library.';

    /** The part headings, in the order they appear. */
    private const PARTS = [
        'A' => 'General',
        'B' => 'Time Control',
        'C' => 'Quality Control',
        'D' => 'Cost Control',
        'E' => 'Finishing the Contract',
    ];

    public function handle(): int
    {
        $path = base_path($this->argument('file'));

        if (! is_file($path)) {
            $this->error("Not found: $path");

            return self::FAILURE;
        }

        $text = preg_replace('/\s+/', ' ', file_get_contents($path));
        $standard = $this->option('standard');

        // Where each part begins, so a clause can be told which part it is in.
        $partAt = [];

        foreach (self::PARTS as $letter => $title) {
            if (preg_match('/\b' . $letter . '\.\s+' . preg_quote($title, '/') . '\b/', $text, $m, PREG_OFFSET_CAPTURE)) {
                $partAt[$m[0][1]] = [$letter, $title];
            }
        }

        ksort($partAt);

        // Clause headings: a number, a dot, then a Titled Phrase. The lookahead
        // keeps the split points out of the captured bodies.
        preg_match_all('/\b(\d{1,2})\.\s+([A-Z][A-Za-z\'\- ]{3,60}?)(?=\s+[0-9]{1,2}\.[0-9])/', $text, $heads, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if (! $heads) {
            $this->error('No clause headings matched — has the source format changed?');

            return self::FAILURE;
        }

        GccClause::where('standard', $standard)->delete();

        $written = 0;
        $seen = [];

        foreach ($heads as $i => $head) {
            $number = (int) $head[1][0];
            $title = trim($head[2][0]);
            $start = $head[0][1];
            $end = $heads[$i + 1][0][1] ?? strlen($text);

            // The source repeats some headings in its table of contents; the
            // first occurrence with a real body is the clause itself.
            if (isset($seen[$number])) {
                continue;
            }

            $body = trim(substr($text, $start, $end - $start));

            if (mb_strlen($body) < 40) {
                continue;
            }

            $part = null;
            $partTitle = null;

            foreach ($partAt as $offset => [$letter, $label]) {
                if ($offset <= $start) {
                    $part = $letter;
                    $partTitle = $label;
                }
            }

            GccClause::create([
                'standard' => $standard,
                'part' => $part,
                'part_title' => $partTitle,
                'number' => $number,
                'title' => $title,
                'body' => $body,
            ]);

            $seen[$number] = true;
            $written++;
        }

        $this->info("Imported {$written} clause(s) for '{$standard}'.");

        foreach (self::PARTS as $letter => $title) {
            $n = GccClause::where('standard', $standard)->where('part', $letter)->count();
            $this->line(sprintf('  %s. %-24s %d clause(s)', $letter, $title, $n));
        }

        return self::SUCCESS;
    }
}
