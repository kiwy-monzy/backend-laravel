<?php
/**
 * Read a .docx as an outline: heading level, then text.
 *
 * Enough to see how a document is structured — its sections, tables and lists —
 * without a Word install. Paragraph styles carry the outline, so each paragraph
 * is reported with the style it was written in.
 *
 * Usage: php tools/docxtext.php <file.docx> [--outline]
 */

$file = $argv[1] ?? null;
$outlineOnly = in_array('--outline', $argv, true);

if (! $file || ! is_file($file)) {
    fwrite(STDERR, "usage: php tools/docxtext.php <file.docx> [--outline]\n");
    exit(1);
}

$zip = new ZipArchive;

if ($zip->open($file) !== true) {
    fwrite(STDERR, "cannot open $file\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

$dom = new DOMDocument;
$dom->loadXML($xml, LIBXML_NOWARNING | LIBXML_NOERROR);

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

foreach ($xpath->query('//w:body/*') as $node) {
    $name = $node->localName;

    if ($name === 'tbl') {
        $rows = $xpath->query('.//w:tr', $node)->length;
        $cols = $xpath->query('.//w:tr[1]/w:tc', $node)->length;
        echo "[TABLE {$rows}×{$cols}]\n";

        if (! $outlineOnly) {
            foreach ($xpath->query('.//w:tr', $node) as $tr) {
                $cells = [];
                foreach ($xpath->query('.//w:tc', $tr) as $tc) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $tc->textContent));
                }
                echo '   | ' . implode(' | ', $cells) . "\n";
            }
        }

        continue;
    }

    if ($name !== 'p') {
        continue;
    }

    $style = $xpath->query('.//w:pStyle/@w:val', $node)->item(0)?->nodeValue ?? '';
    $text = trim(preg_replace('/\s+/', ' ', $node->textContent));

    if ($text === '') {
        continue;
    }

    // Headings carry the outline; everything else is body or list text.
    if (preg_match('/^Heading(\d)$/i', $style, $m)) {
        echo str_repeat('  ', (int) $m[1] - 1) . 'H' . $m[1] . ': ' . $text . "\n";
    } elseif ($style === 'Title') {
        echo "TITLE: $text\n";
    } elseif (! $outlineOnly) {
        echo '     ' . mb_substr($text, 0, 200) . (mb_strlen($text) > 200 ? '…' : '') . "\n";
    }
}
