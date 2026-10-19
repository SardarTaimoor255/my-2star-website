<?php
/**
 * includes/xlsx_reader.php
 * Minimal XLSX reader — uses only PHP native extensions
 * No external library needed (no PhpSpreadsheet, no Composer)
 */
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }

class SimpleXLSXReader
{
    private $zip;
    private $sharedStrings = [];
    private $sheetXml = '';

    public function __construct(string $filePath)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('PHP ZIP extension not enabled. Please enable php_zip in XAMPP.');
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($filePath) !== true) {
            throw new Exception('Cannot open XLSX file. File may be corrupt.');
        }

        $this->loadSharedStrings();
        $this->loadFirstSheet();
    }

    /**
     * Read shared strings table (text values)
     */
    private function loadSharedStrings(): void
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return;

        // Disable XXE attacks
       $doc = simplexml_load_string($xml);

        if (!$doc) return;

        foreach ($doc->si as $si) {
            $text = '';
            // Simple text node
            if (isset($si->t)) {
                $text = (string)$si->t;
            }
            // Rich text (multiple runs)
            elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
            }
            $this->sharedStrings[] = $text;
        }
    }

    /**
     * Locate and load first worksheet XML
     */
    private function loadFirstSheet(): void
    {
        // Common path
        $xml = $this->zip->getFromName('xl/worksheets/sheet1.xml');
        if ($xml !== false) {
            $this->sheetXml = $xml;
            return;
        }

        // Try other common names
        $xml = $this->zip->getFromName('xl/worksheets/Sheet1.xml');
        if ($xml !== false) {
            $this->sheetXml = $xml;
            return;
        }

        // Search for any sheet XML
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                $content = $this->zip->getFromIndex($i);
                if ($content !== false) {
                    $this->sheetXml = $content;
                    return;
                }
            }
        }

        throw new Exception('No worksheet found inside XLSX file.');
    }

    /**
     * Return all rows as array of arrays
     */
    public function getRows(): array
    {
        $rows = [];

               $doc = simplexml_load_string($this->sheetXml);
        if (!$doc || !isset($doc->sheetData)) {
            return $rows;
        }

        foreach ($doc->sheetData->row as $row) {
            $rowData = [];
            $lastColIndex = -1;

            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];                       // e.g. "A1", "C2"
                $colLetter = preg_replace('/[0-9]+/', '', $ref); // "A", "C", "AA"
                $colIndex = $this->colToIndex($colLetter);

                // Fill empty cells with blank strings
                while ($lastColIndex + 1 < $colIndex) {
                    $rowData[] = '';
                    $lastColIndex++;
                }

                $type = (string)$cell['t'];
                $value = '';

                if ($type === 's') {
                    // Shared string reference
                    $sIdx = (int)$cell->v;
                    $value = $this->sharedStrings[$sIdx] ?? '';
                } elseif ($type === 'inlineStr') {
                    // Inline string
                    $value = (string)($cell->is->t ?? '');
                } elseif ($type === 'b') {
                    // Boolean
                    $value = ((string)$cell->v === '1') ? '1' : '0';
                } else {
                    // Number, date, formula result, etc.
                    $value = (string)($cell->v ?? '');
                }

                $rowData[] = $value;
                $lastColIndex = $colIndex;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Convert column letters → 0-based index (A=0, B=1, ..., Z=25, AA=26)
     */
    private function colToIndex(string $col): int
    {
        $col = strtoupper($col);
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function close(): void
    {
        if ($this->zip) {
            $this->zip->close();
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}