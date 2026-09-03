<?php
/**
 * Minimal PDF text extractor.
 *
 * Enough to read a text-based PDF without poppler installed: inflate each
 * content stream and pull the strings out of the text-showing operators.
 * It is not a typesetter — it recovers words and their order, which is all a
 * reader (or a clause importer) needs.
 *
 * Usage: php tools/pdftext.php <input.pdf> [output.txt]
 */

$in = $argv[1] ?? null;
$out = $argv[2] ?? null;

if (! $in || ! is_file($in)) {
    fwrite(STDERR, "usage: php tools/pdftext.php <input.pdf> [output.txt]\n");
    exit(1);
}

$raw = file_get_contents($in);
$text = '';

if (preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $streams)) {
    foreach ($streams[1] as $stream) {
        $data = @gzuncompress($stream);

        if ($data === false) {
            $data = @gzinflate(substr($stream, 2));
        }

        if ($data === false || ! preg_match('/(TJ|Tj)/', $data)) {
            continue;
        }

        // Strings live in parentheses; TJ arrays hold several per operator.
        if (preg_match_all('/\((?:\\\\.|[^()\\\\])*\)/s', $data, $chunks)) {
            $line = '';

            foreach ($chunks[0] as $chunk) {
                $line .= stripcslashes(substr($chunk, 1, -1));
            }

            $text .= $line . "\n";
        }
    }
}

// Collapse the runs of spaces that per-glyph positioning leaves behind.
$text = preg_replace('/[ \t]{2,}/', ' ', $text);
$text = preg_replace('/\n{3,}/', "\n\n", $text);

if ($out) {
    file_put_contents($out, $text);
    fwrite(STDERR, 'wrote ' . strlen($text) . " chars to $out\n");
} else {
    echo $text;
}
