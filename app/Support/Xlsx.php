<?php

namespace App\Support;

/**
 * A minimal, dependency-free XLSX writer.
 *
 * **A real `.xlsx` is a zip of XML parts, and PHP already has `zip`.** Pulling
 * in PhpSpreadsheet for what the export needs — a header row and some data
 * rows — would be a megabyte of dependency for a few kilobytes of use. This
 * writes the six parts Excel requires and nothing more.
 *
 * Strings are written as inline (`inlineStr`) rather than through a shared
 * string table: it makes the file marginally larger but removes the one part
 * that is fiddly to get right, and for an export nobody re-opens to hand-edit
 * that is the right trade.
 */
final class Xlsx
{
    /** @var array<int,array<int,mixed>> */
    private array $rows = [];

    private array $headers = [];

    public function __construct(private string $sheetName = 'Sheet1')
    {
        // Excel rejects sheet names over 31 chars or with certain characters.
        $this->sheetName = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName), 0, 31) ?: 'Sheet1';
    }

    /** @param array<int,string> $headers */
    public function headers(array $headers): self
    {
        $this->headers = array_values($headers);

        return $this;
    }

    /** @param array<int,mixed> $row */
    public function row(array $row): self
    {
        $this->rows[] = array_values($row);

        return $this;
    }

    /** Streams the file to the browser as a download. */
    public function download(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $binary = $this->build();

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** The complete xlsx as a binary string. */
    public function build(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());

        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return $binary;
    }

    private function sheet(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $r = 1;

        if ($this->headers !== []) {
            $xml .= $this->rowXml($this->headers, $r++, true);
        }

        foreach ($this->rows as $row) {
            $xml .= $this->rowXml($row, $r++, false);
        }

        return $xml . '</sheetData></worksheet>';
    }

    private function rowXml(array $cells, int $rowNumber, bool $bold): string
    {
        $xml = '<row r="' . $rowNumber . '">';

        foreach (array_values($cells) as $i => $value) {
            $ref = $this->columnLetter($i) . $rowNumber;

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="' . $ref . '"' . ($bold ? ' s="1"' : '') . '><v>' . $value . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . ($bold ? ' s="1"' : '')
                    . '><is><t xml:space="preserve">' . $this->esc((string) $value) . '</t></is></c>';
            }
        }

        return $xml . '</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - $mod, 26);
        }

        return $letter;
    }

    private function esc(string $value): string
    {
        // Control characters make Excel refuse to open the file, so they are
        // stripped rather than escaped.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->esc($this->sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** One style: bold, used for the header row (`s="1"`). */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
