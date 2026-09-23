<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Pure Core PHP Excel (.xlsx) Export Service
 * Produces genuine, schema-compliant ECMA-376 OpenXML (.xlsx) multi-sheet workbooks.
 *
 * Reliability Guarantees:
 * 1. Output Buffer Safety: Purges all active buffers before binary streaming.
 * 2. Error Suppression: Disables display_errors during streaming so PHP warnings never corrupt the ZIP payload.
 * 3. XML Cleanliness: Strips invalid XML 1.0 control characters and safely escapes entities.
 * 4. Dual Engine ZIP Creation:
 *    - Engine A: PHP ZipArchive (if available and writable).
 *    - Engine B: Built-in Pure PHP PK-Zip packager (using RFC 1951 gzdeflate / store mode).
 *    Guarantees a genuine .xlsx ZIP file is created even if php-zip extension is not installed.
 */
class ExcelExportService
{
    /**
     * Export organisations to a valid Excel (.xlsx) workbook
     */
    public function exportOrganisations(array $organizations, array $meta = []): void
    {
        $filename = 'secure360-organisations-' . date('Y-m-d_His') . '.xlsx';

        $files = [];
        $files['[Content_Types].xml'] = $this->getContentTypesXml(3);
        $files['_rels/.rels'] = $this->getRootRelsXml();
        $files['docProps/app.xml'] = $this->getAppPropsXml();
        $files['docProps/core.xml'] = $this->getCorePropsXml();
        $files['xl/workbook.xml'] = $this->getWorkbookXml([
            ['name' => 'Organisations', 'id' => 1, 'rId' => 'rId1'],
            ['name' => 'Organisation Admins', 'id' => 2, 'rId' => 'rId2'],
            ['name' => 'Export Information', 'id' => 3, 'rId' => 'rId3'],
        ]);
        $files['xl/_rels/workbook.xml.rels'] = $this->getWorkbookRelsXml(3);
        $files['xl/styles.xml'] = $this->getStylesXml();
        $files['xl/worksheets/sheet1.xml'] = $this->getOrganisationsSheetXml($organizations);
        $files['xl/worksheets/sheet2.xml'] = $this->getAdminsSheetXml($organizations);
        $files['xl/worksheets/sheet3.xml'] = $this->getMetaSheetXml($meta, count($organizations));

        $this->streamXlsx($filename, $files);
    }

    /**
     * Export subscriptions to a valid Excel (.xlsx) workbook
     */
    public function exportSubscriptions(array $subscriptions, array $meta = []): void
    {
        $filename = 'secure360-subscriptions-' . date('Y-m-d_His') . '.xlsx';

        $files = [];
        $files['[Content_Types].xml'] = $this->getContentTypesXml(2);
        $files['_rels/.rels'] = $this->getRootRelsXml();
        $files['docProps/app.xml'] = $this->getAppPropsXml();
        $files['docProps/core.xml'] = $this->getCorePropsXml();
        $files['xl/workbook.xml'] = $this->getWorkbookXml([
            ['name' => 'Subscriptions', 'id' => 1, 'rId' => 'rId1'],
            ['name' => 'Export Information', 'id' => 2, 'rId' => 'rId2'],
        ]);
        $files['xl/_rels/workbook.xml.rels'] = $this->getWorkbookRelsXml(2);
        $files['xl/styles.xml'] = $this->getStylesXml();
        $files['xl/worksheets/sheet1.xml'] = $this->getSubscriptionsSheetXml($subscriptions);
        $files['xl/worksheets/sheet2.xml'] = $this->getSubscriptionMetaSheetXml($meta, count($subscriptions));

        $this->streamXlsx($filename, $files);
    }

    /**
     * Export invoices to a valid Excel (.xlsx) workbook
     */
    public function exportInvoices(array $invoices, array $meta = []): void
    {
        $filename = 'secure360-invoices-' . date('Y-m-d_His') . '.xlsx';

        $files = [];
        $files['[Content_Types].xml'] = $this->getContentTypesXml(2);
        $files['_rels/.rels'] = $this->getRootRelsXml();
        $files['docProps/app.xml'] = $this->getAppPropsXml();
        $files['docProps/core.xml'] = $this->getCorePropsXml();
        $files['xl/workbook.xml'] = $this->getWorkbookXml([
            ['name' => 'Invoices', 'id' => 1, 'rId' => 'rId1'],
            ['name' => 'Export Information', 'id' => 2, 'rId' => 'rId2'],
        ]);
        $files['xl/_rels/workbook.xml.rels'] = $this->getWorkbookRelsXml(2);
        $files['xl/styles.xml'] = $this->getStylesXml();
        $files['xl/worksheets/sheet1.xml'] = $this->getInvoicesSheetXml($invoices);
        $files['xl/worksheets/sheet2.xml'] = $this->getInvoiceMetaSheetXml($meta, count($invoices));

        $this->streamXlsx($filename, $files);
    }

    /**
     * Export operational report to a valid Excel (.xlsx) workbook
     */
    public function exportOperationalReport(string $reportType, array $records, array $meta = []): void
    {
        $reportTypeSlug = str_replace('_', '-', $reportType);
        $filename = 'secure360-' . $reportTypeSlug . '-report-' . date('Y-m-d_His') . '.xlsx';

        $files = [];
        $files['[Content_Types].xml'] = $this->getContentTypesXml(2);
        $files['_rels/.rels'] = $this->getRootRelsXml();
        $files['docProps/app.xml'] = $this->getAppPropsXml();
        $files['docProps/core.xml'] = $this->getCorePropsXml();
        $files['xl/workbook.xml'] = $this->getWorkbookXml([
            ['name' => 'Report Data', 'id' => 1, 'rId' => 'rId1'],
            ['name' => 'Export Information', 'id' => 2, 'rId' => 'rId2'],
        ]);
        $files['xl/_rels/workbook.xml.rels'] = $this->getWorkbookRelsXml(2);
        $files['xl/styles.xml'] = $this->getStylesXml();
        $files['xl/worksheets/sheet1.xml'] = $this->getOperationalReportSheetXml($reportType, $records);
        $files['xl/worksheets/sheet2.xml'] = $this->getOperationalMetaSheetXml($meta, count($records));

        $this->streamXlsx($filename, $files);
    }

    /**
     * Stream the assembled files as an OpenXML (.xlsx) attachment
     */
    private function streamXlsx(string $filename, array $files): void
    {
        // 1. Purge all output buffers to prevent preceding whitespace or notices from corrupting the ZIP header
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 2. Suppress display_errors to prevent any PHP notices from being injected into the binary stream
        ini_set('display_errors', '0');

        // 3. Build genuine ZIP binary package
        $binaryZip = $this->createZipPackage($files);

        // 4. Set accurate OpenXML headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binaryZip));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        echo $binaryZip;
        exit;
    }

    /**
     * Create ZIP archive using ZipArchive if available, or fall back to pure-PHP ZIP builder
     */
    private function createZipPackage(array $files): string
    {
        // Try Engine A: ZipArchive
        if (class_exists('ZipArchive')) {
            $tempFile = tempnam(sys_get_temp_dir(), 's360_xlsx_');
            if ($tempFile !== false) {
                // Delete the 0-byte file created by tempnam so ZipArchive creates a fresh clean archive
                @unlink($tempFile);
                $zip = new ZipArchive();
                if ($zip->open($tempFile, ZipArchive::CREATE) === true) {
                    foreach ($files as $name => $content) {
                        $zip->addFromString($name, $content);
                    }
                    $zip->close();
                    $data = @file_get_contents($tempFile);
                    @unlink($tempFile);

                    if (!empty($data) && str_starts_with($data, "PK\x03\x04")) {
                        return $data;
                    }
                }
            }
        }

        // Engine B: Pure PHP ZIP Packager (100% compliant with standard ZIP PK specification)
        return $this->buildPurePhpZip($files);
    }

    /**
     * Pure PHP ZIP file builder conforming strictly to PKZip 2.0 specification.
     * Uses gzdeflate (RFC 1951) if available, or uncompressed Store mode (method 0).
     */
    private function buildPurePhpZip(array $files): string
    {
        $hasDeflate = function_exists('gzdeflate');
        $zipData = '';
        $centralDir = '';
        $offset = 0;

        // Standard DOS timestamp for consistent, clean archives
        $dostime = (12 << 11) | (0 << 5) | (0 >> 1); // 12:00:00
        $dosdate = ((2026 - 1980) << 9) | (9 << 5) | 22; // 2026-09-22

        foreach ($files as $name => $content) {
            $uncompressedSize = strlen($content);
            $crc = crc32($content);

            if ($hasDeflate && $uncompressedSize > 0) {
                $compressedContent = gzdeflate($content);
                $compressionMethod = 8; // Deflate
                $compressedSize = strlen($compressedContent);
            } else {
                $compressedContent = $content;
                $compressionMethod = 0; // Stored
                $compressedSize = $uncompressedSize;
            }

            $nameLen = strlen($name);

            // Local File Header (0x04034b50)
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,       // Signature
                20,               // Version needed (2.0)
                0x0800,           // General purpose bit flag (UTF-8 filename)
                $compressionMethod,
                $dostime,
                $dosdate,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0                 // Extra field length
            ) . $name;

            $zipData .= $localHeader . $compressedContent;

            // Central Directory Header (0x02014b50)
            $centralDir .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,       // Signature
                20,               // Version made by
                20,               // Version needed
                0x0800,           // General purpose bit flag (UTF-8)
                $compressionMethod,
                $dostime,
                $dosdate,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0,                // Extra field length
                0,                // File comment length
                0,                // Disk number start
                0,                // Internal file attributes
                0x20,             // External file attributes (normal archive)
                $offset           // Relative offset of local header
            ) . $name;

            $offset = strlen($zipData);
        }

        $centralDirSize = strlen($centralDir);
        $totalEntries = count($files);

        // End of Central Directory Record (0x06054b50)
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,       // Signature
            0,                // Disk number
            0,                // Disk where central directory starts
            $totalEntries,    // Number of entries on this disk
            $totalEntries,    // Total number of entries
            $centralDirSize,  // Size of central directory
            $offset,          // Offset of central directory
            0                 // Comment length
        );

        return $zipData . $centralDir . $eocd;
    }

    // =========================================================================
    // OpenXML Package Part Builders
    // =========================================================================

    private function getContentTypesXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n";
        $xml .= '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n";
        $xml .= '  <Default Extension="xml" ContentType="application/xml"/>' . "\n";
        $xml .= '  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . "\n";
        $xml .= '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n";
        $xml .= '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n";
        $xml .= '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n";
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '  <Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
        }
        $xml .= '</Types>';
        return $xml;
    }

    private function getRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n"
            . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' . "\n"
            . '  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>' . "\n"
            . '</Relationships>';
    }

    private function getAppPropsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">' . "\n"
            . '  <Application>Secure360</Application>' . "\n"
            . '</Properties>';
    }

    private function getCorePropsXml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n"
            . '  <dc:creator>Secure360</dc:creator>' . "\n"
            . '  <cp:lastModifiedBy>Secure360</cp:lastModifiedBy>' . "\n"
            . '  <dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>' . "\n"
            . '  <dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>' . "\n"
            . '</cp:coreProperties>';
    }

    private function getWorkbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <bookViews><workbookView xWindow="0" yWindow="0" windowWidth="20480" windowHeight="10240"/></bookViews>' . "\n";
        $xml .= '  <sheets>' . "\n";
        foreach ($sheets as $s) {
            $xml .= '    <sheet name="' . $this->cleanXml($s['name']) . '" sheetId="' . (int)$s['id'] . '" r:id="' . $this->cleanXml($s['rId']) . '"/>' . "\n";
        }
        $xml .= '  </sheets>' . "\n";
        $xml .= '</workbook>';
        return $xml;
    }

    private function getWorkbookRelsXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '  <Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>' . "\n";
        }
        $stylesRId = $sheetCount + 1;
        $xml .= '  <Relationship Id="rId' . $stylesRId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n";
        $xml .= '</Relationships>';
        return $xml;
    }

    /**
     * Stylesheet with strictly ordered schema elements required by ECMA-376
     */
    private function getStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
            . '  <fonts count="3">' . "\n"
            . '    <font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>' . "\n"
            . '    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>' . "\n"
            . '    <font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>' . "\n"
            . '  </fonts>' . "\n"
            . '  <fills count="4">' . "\n"
            . '    <fill><patternFill patternType="none"/></fill>' . "\n"
            . '    <fill><patternFill patternType="gray125"/></fill>' . "\n"
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>' . "\n"
            . '    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>' . "\n"
            . '  </fills>' . "\n"
            . '  <borders count="2">' . "\n"
            . '    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\n"
            . '    <border>' . "\n"
            . '      <left style="thin"><color rgb="FFE2E8F0"/></left>' . "\n"
            . '      <right style="thin"><color rgb="FFE2E8F0"/></right>' . "\n"
            . '      <top style="thin"><color rgb="FFE2E8F0"/></top>' . "\n"
            . '      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>' . "\n"
            . '    </border>' . "\n"
            . '  </borders>' . "\n"
            . '  <cellStyleXfs count="1">' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\n"
            . '  </cellStyleXfs>' . "\n"
            . '  <cellXfs count="4">' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>' . "\n"
            . '    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n"
            . '    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' . "\n"
            . '    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' . "\n"
            . '  </cellXfs>' . "\n"
            . '  <cellStyles count="1">' . "\n"
            . '    <cellStyle name="Normal" xfId="0" builtinId="0"/>' . "\n"
            . '  </cellStyles>' . "\n"
            . '  <dxfs count="0"/>' . "\n"
            . '  <tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/>' . "\n"
            . '</styleSheet>';
    }

    // =========================================================================
    // Worksheet XML Generators
    // =========================================================================

    /**
     * Sheet 1: Organisations List (aligned with Part 6 specification)
     */
    private function getOrganisationsSheetXml(array $organizations): string
    {
        $headers = [
            'Organisation Name',
            'Organisation Code',
            'Email',
            'Phone',
            'Address',
            'Status',
            'Admin',
            'Created Date',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        // Row 1: Headers (style 1 = blue header)
        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xml .= '      <c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . $this->cleanXml($h) . '</t></is></c>' . "\n";
        }
        $xml .= '    </row>' . "\n";

        // Data Rows
        $rowNum = 2;
        foreach ($organizations as $org) {
            $status = (int)($org['status'] ?? 0) === 0 ? 'Active' : 'Suspended';
            $admins = $org['admins'] ?? [];
            $primaryAdmin = !empty($admins) ? $admins[0] : null;
            $adminText = $primaryAdmin ? (string)($primaryAdmin['full_name'] ?? '') : '—';
            $createdDate = !empty($org['created_at']) ? date('d-M-y', strtotime($org['created_at'])) : '—';

            $rowValues = [
                (string)($org['name'] ?? ''),
                (string)($org['organization_code'] ?? ''),
                (string)($org['email'] ?? ''),
                (string)($org['phone'] ?? ''),
                (string)($org['address'] ?? ''),
                $status,
                $adminText,
                $createdDate,
            ];

            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $xml .= '      <c r="' . $colLetter . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($val) . '</t></is></c>' . "\n";
            }
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 2: Organisation Administrators detail
     */
    private function getAdminsSheetXml(array $organizations): string
    {
        $headers = [
            'Organisation Name',
            'Organisation Code',
            'Admin Full Name',
            'Employee Code',
            'Login Email',
            'Contact Phone',
            'Status',
            'Created Date',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="0" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xml .= '      <c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . $this->cleanXml($h) . '</t></is></c>' . "\n";
        }
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($organizations as $org) {
            $orgName = (string)($org['name'] ?? '');
            $orgCode = (string)($org['organization_code'] ?? '');
            $admins = $org['admins'] ?? [];

            foreach ($admins as $adm) {
                $status = (int)($adm['status'] ?? 0) === 0 ? 'Active' : 'Suspended';
                $created = !empty($adm['created_at']) ? date('d-M-y', strtotime($adm['created_at'])) : '—';

                $rowValues = [
                    $orgName,
                    $orgCode,
                    (string)($adm['full_name'] ?? ''),
                    (string)($adm['employee_code'] ?? 'ADM'),
                    (string)($adm['email'] ?? ''),
                    (string)($adm['phone'] ?? ''),
                    $status,
                    $created,
                ];

                $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
                foreach ($rowValues as $colIdx => $val) {
                    $colLetter = $this->getColumnLetter($colIdx + 1);
                    $xml .= '      <c r="' . $colLetter . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($val) . '</t></is></c>' . "\n";
                }
                $xml .= '    </row>' . "\n";
                $rowNum++;
            }
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 3: Organisations Export Metadata
     */
    private function getMetaSheetXml(array $meta, int $totalCount): string
    {
        $info = [
            ['Report Title', 'Secure360 - Organisations Report'],
            ['Generated At', date('d-M-y h:i A')],
            ['Status Filter Applied', $meta['status_filter'] ?? 'All'],
            ['Search Query Applied', !empty($meta['search']) ? $meta['search'] : 'None'],
            ['Total Organisations Exported', (string)$totalCount],
            ['Generated By', $meta['generated_by'] ?? 'Superadmin'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="0" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        $xml .= '      <c r="A1" t="inlineStr" s="1"><is><t>Property</t></is></c>' . "\n";
        $xml .= '      <c r="B1" t="inlineStr" s="1"><is><t>Export Metadata Information</t></is></c>' . "\n";
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($info as $item) {
            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            $xml .= '      <c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>' . $this->cleanXml($item[0]) . '</t></is></c>' . "\n";
            $xml .= '      <c r="B' . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($item[1]) . '</t></is></c>' . "\n";
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 1: Subscriptions List (aligned with Part 8 specification)
     */
    private function getSubscriptionsSheetXml(array $subscriptions): string
    {
        $headers = [
            'Organisation',
            'Organisation Code',
            'Plan / Type',
            'Guard Limit',
            'Active Guards',
            'Start Date',
            'Expiry Date',
            'Status',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xml .= '      <c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . $this->cleanXml($h) . '</t></is></c>' . "\n";
        }
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($subscriptions as $s) {
            $statusKey = $s['calculated_status'] ?? 'active';
            $statusLabel = match ($statusKey) {
                'active' => 'Active',
                'expiring_soon' => 'Expiring Soon',
                'expired' => 'Expired',
                'suspended' => 'Suspended',
                default => ucfirst((string)$statusKey),
            };

            $planLabel = 'Standard Annual Plan';
            if (!empty($s['notes']) && strlen($s['notes']) <= 25 && !str_contains(strtolower($s['notes']), 'invoice')) {
                $planLabel = $s['notes'];
            }

            $startDate = !empty($s['start_date']) ? date('d-M-y', strtotime($s['start_date'])) : '—';
            $endDate = !empty($s['end_date']) ? date('d-M-y', strtotime($s['end_date'])) : '—';

            $rowValues = [
                (string)($s['organization_name'] ?? ''),
                (string)($s['organization_code'] ?? ''),
                $planLabel,
                (string)($s['guard_limit'] ?? '0'),
                (string)($s['active_guards'] ?? '0'),
                $startDate,
                $endDate,
                $statusLabel,
            ];

            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $xml .= '      <c r="' . $colLetter . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($val) . '</t></is></c>' . "\n";
            }
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 2: Subscriptions Export Metadata
     */
    private function getSubscriptionMetaSheetXml(array $meta, int $totalCount): string
    {
        $info = [
            ['Report Title', 'Secure360 - Subscriptions Report'],
            ['Generated At', date('d-M-y h:i A')],
            ['Status Filter Applied', $meta['status_filter'] ?? 'All'],
            ['Search Query Applied', !empty($meta['search']) ? $meta['search'] : 'None'],
            ['Total Subscriptions Exported', (string)$totalCount],
            ['Generated By', $meta['generated_by'] ?? 'Superadmin'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="0" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        $xml .= '      <c r="A1" t="inlineStr" s="1"><is><t>Property</t></is></c>' . "\n";
        $xml .= '      <c r="B1" t="inlineStr" s="1"><is><t>Export Metadata Information</t></is></c>' . "\n";
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($info as $item) {
            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            $xml .= '      <c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>' . $this->cleanXml($item[0]) . '</t></is></c>' . "\n";
            $xml .= '      <c r="B' . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($item[1]) . '</t></is></c>' . "\n";
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 1: Invoices List
     */
    private function getInvoicesSheetXml(array $invoices): string
    {
        $headers = [
            'Invoice Number',
            'Organisation',
            'Organisation Code',
            'Subscription',
            'Invoice Type',
            'Invoice Date',
            'Billing Period',
            'Guards',
            'Rate (Rs.)',
            'Total Amount (Rs.)',
            'Status',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($headers as $colIdx => $h) {
            $colLetter = $this->getColumnLetter($colIdx + 1);
            $xml .= '      <c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . $this->cleanXml($h) . '</t></is></c>' . "\n";
        }
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($invoices as $inv) {
            $invDate = !empty($inv['invoice_date']) ? date('d-M-y', strtotime($inv['invoice_date'])) : '—';
            $startDate = !empty($inv['billing_start_date']) ? date('d-M-y', strtotime($inv['billing_start_date'])) : '—';
            $endDate = !empty($inv['billing_end_date']) ? date('d-M-y', strtotime($inv['billing_end_date'])) : '—';
            $billingPeriod = "{$startDate} - {$endDate}";
            $status = strtoupper((string)($inv['status'] ?? 'PAID'));

            $rowValues = [
                (string)($inv['invoice_number'] ?? ''),
                (string)($inv['organization_name'] ?? ''),
                (string)($inv['organization_code'] ?? ''),
                '#SUB-' . (int)($inv['subscription_id'] ?? 0),
                (string)($inv['invoice_type'] ?? 'Initial Subscription'),
                $invDate,
                $billingPeriod,
                (string)($inv['guard_quantity'] ?? 0),
                number_format((float)($inv['price_per_guard'] ?? 0), 2, '.', ''),
                number_format((float)($inv['total_amount'] ?? 0), 2, '.', ''),
                $status,
            ];

            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $xml .= '      <c r="' . $colLetter . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($val) . '</t></is></c>' . "\n";
            }
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 2: Invoices Export Metadata
     */
    private function getInvoiceMetaSheetXml(array $meta, int $totalCount): string
    {
        $info = [
            ['Report Title', 'Secure360 - Invoices Report'],
            ['Generated At', date('d-M-y h:i A')],
            ['Status Filter Applied', $meta['status_filter'] ?? 'All'],
            ['Search Query Applied', !empty($meta['search']) ? $meta['search'] : 'None'],
            ['Total Invoices Exported', (string)$totalCount],
            ['Generated By', $meta['generated_by'] ?? 'Superadmin'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="0" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";
        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        $xml .= '      <c r="A1" t="inlineStr" s="1"><is><t>Property</t></is></c>' . "\n";
        $xml .= '      <c r="B1" t="inlineStr" s="1"><is><t>Export Metadata Information</t></is></c>' . "\n";
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($info as $item) {
            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            $xml .= '      <c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>' . $this->cleanXml($item[0]) . '</t></is></c>' . "\n";
            $xml .= '      <c r="B' . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($item[1]) . '</t></is></c>' . "\n";
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 1: Operational Report Data
     */
    private function getOperationalReportSheetXml(string $reportType, array $records): string
    {
        // Define columns and widths based on report type
        switch ($reportType) {
            case 'contracts':
                $columns = [
                    ['title' => 'Contract Code', 'width' => 16],
                    ['title' => 'Client Name', 'width' => 26],
                    ['title' => 'Duty Site', 'width' => 26],
                    ['title' => 'Start Date', 'width' => 14],
                    ['title' => 'End Date', 'width' => 14],
                    ['title' => 'Guard Limit', 'width' => 14],
                    ['title' => 'Assigned Guards', 'width' => 16],
                    ['title' => 'Status', 'width' => 16],
                ];
                break;

            case 'shifts':
                $columns = [
                    ['title' => 'Date', 'width' => 14],
                    ['title' => 'Shift Name', 'width' => 20],
                    ['title' => 'Client Name', 'width' => 24],
                    ['title' => 'Duty Site', 'width' => 24],
                    ['title' => 'Scheduled Time', 'width' => 18],
                    ['title' => 'Guard Name', 'width' => 22],
                    ['title' => 'Check-In', 'width' => 12],
                    ['title' => 'Check-Out', 'width' => 12],
                    ['title' => 'Shift Status', 'width' => 16],
                ];
                break;

            case 'sites_clients':
                $columns = [
                    ['title' => 'Date', 'width' => 14],
                    ['title' => 'Client Name', 'width' => 24],
                    ['title' => 'Duty Site', 'width' => 24],
                    ['title' => 'Site Status', 'width' => 14],
                    ['title' => 'Guard Name', 'width' => 22],
                    ['title' => 'Badge ID', 'width' => 14],
                    ['title' => 'Shift', 'width' => 18],
                    ['title' => 'Check-In', 'width' => 12],
                    ['title' => 'Check-Out', 'width' => 12],
                    ['title' => 'Attendance Status', 'width' => 18],
                ];
                break;

            case 'guards':
                $columns = [
                    ['title' => 'Date', 'width' => 14],
                    ['title' => 'Guard Name', 'width' => 22],
                    ['title' => 'Badge ID', 'width' => 14],
                    ['title' => 'Duty Site', 'width' => 24],
                    ['title' => 'Client Name', 'width' => 24],
                    ['title' => 'Shift', 'width' => 18],
                    ['title' => 'Assignment', 'width' => 16],
                    ['title' => 'Check-In', 'width' => 12],
                    ['title' => 'Check-Out', 'width' => 12],
                    ['title' => 'Attendance Status', 'width' => 18],
                ];
                break;

            case 'attendance':
                $columns = [
                    ['title' => 'Date', 'width' => 14],
                    ['title' => 'Guard Name', 'width' => 22],
                    ['title' => 'Badge ID', 'width' => 14],
                    ['title' => 'Client Name', 'width' => 24],
                    ['title' => 'Duty Site', 'width' => 24],
                    ['title' => 'Shift', 'width' => 18],
                    ['title' => 'Scheduled Time', 'width' => 18],
                    ['title' => 'Check-In', 'width' => 12],
                    ['title' => 'Check-Out', 'width' => 12],
                    ['title' => 'Attendance Status', 'width' => 18],
                    ['title' => 'GPS Status', 'width' => 20],
                ];
                break;

            case 'all_operations':
            default:
                $columns = [
                    ['title' => 'Date', 'width' => 14],
                    ['title' => 'Guard Name', 'width' => 22],
                    ['title' => 'Badge ID', 'width' => 14],
                    ['title' => 'Client Name', 'width' => 24],
                    ['title' => 'Duty Site', 'width' => 24],
                    ['title' => 'Shift', 'width' => 18],
                    ['title' => 'Scheduled Time', 'width' => 18],
                    ['title' => 'Check-In', 'width' => 12],
                    ['title' => 'Check-Out', 'width' => 12],
                    ['title' => 'GPS Verification', 'width' => 20],
                    ['title' => 'Attendance Status', 'width' => 18],
                    ['title' => 'Shift Status', 'width' => 16],
                ];
                break;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";

        // Column widths
        $xml .= '  <cols>' . "\n";
        foreach ($columns as $idx => $col) {
            $colNum = $idx + 1;
            $xml .= '    <col min="' . $colNum . '" max="' . $colNum . '" width="' . $col['width'] . '" customWidth="1"/>' . "\n";
        }
        $xml .= '  </cols>' . "\n";

        $xml .= '  <sheetData>' . "\n";

        // Header Row (Row 1, style 1)
        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        foreach ($columns as $idx => $col) {
            $colLetter = $this->getColumnLetter($idx + 1);
            $xml .= '      <c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . $this->cleanXml($col['title']) . '</t></is></c>' . "\n";
        }
        $xml .= '    </row>' . "\n";

        // Data Rows
        $rowNum = 2;
        foreach ($records as $row) {
            switch ($reportType) {
                case 'contracts':
                    $rowValues = [
                        (string)($row['contract_code'] ?? ''),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['site_name'] ?? 'Multiple Sites'),
                        (string)($row['start_date'] ?? ''),
                        (string)($row['end_date'] ?? 'Ongoing'),
                        (string)($row['guard_limit'] ?? 0),
                        (string)($row['assigned_guards'] ?? 0),
                        (string)($row['status_label'] ?? 'Active'),
                    ];
                    break;

                case 'shifts':
                    $rowValues = [
                        (string)($row['date'] ?? '—'),
                        (string)($row['shift_name'] ?? '—'),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['site_name'] ?? '—'),
                        (string)($row['scheduled_time'] ?? '—'),
                        (string)($row['guard_name'] ?? '—'),
                        (string)($row['check_in'] ?? '—'),
                        (string)($row['check_out'] ?? '—'),
                        (string)($row['shift_status'] ?? '—'),
                    ];
                    break;

                case 'sites_clients':
                    $rowValues = [
                        (string)($row['date'] ?? '—'),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['site_name'] ?? '—'),
                        (string)($row['site_status_label'] ?? 'Active'),
                        (string)($row['guard_name'] ?? '—'),
                        (string)($row['guard_badge'] ?? '—'),
                        (string)($row['shift_name'] ?? '—'),
                        (string)($row['check_in'] ?? '—'),
                        (string)($row['check_out'] ?? '—'),
                        (string)($row['attendance_status'] ?? '—'),
                    ];
                    break;

                case 'guards':
                    $rowValues = [
                        (string)($row['date'] ?? '—'),
                        (string)($row['guard_name'] ?? '—'),
                        (string)($row['guard_badge'] ?? '—'),
                        (string)($row['site_name'] ?? '—'),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['shift_name'] ?? '—'),
                        (string)($row['assignment_status_label'] ?? 'Assigned'),
                        (string)($row['check_in'] ?? '—'),
                        (string)($row['check_out'] ?? '—'),
                        (string)($row['attendance_status'] ?? '—'),
                    ];
                    break;

                case 'attendance':
                    $rowValues = [
                        (string)($row['date'] ?? '—'),
                        (string)($row['guard_name'] ?? '—'),
                        (string)($row['guard_badge'] ?? '—'),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['site_name'] ?? '—'),
                        (string)($row['shift_name'] ?? '—'),
                        (string)($row['scheduled_time'] ?? '—'),
                        (string)($row['check_in'] ?? '—'),
                        (string)($row['check_out'] ?? '—'),
                        (string)($row['attendance_status'] ?? '—'),
                        (string)($row['gps_status'] ?? '—'),
                    ];
                    break;

                case 'all_operations':
                default:
                    $rowValues = [
                        (string)($row['date'] ?? '—'),
                        (string)($row['guard_name'] ?? '—'),
                        (string)($row['guard_badge'] ?? '—'),
                        (string)($row['customer_name'] ?? '—'),
                        (string)($row['site_name'] ?? '—'),
                        (string)($row['shift_name'] ?? '—'),
                        (string)($row['scheduled_time'] ?? '—'),
                        (string)($row['check_in'] ?? '—'),
                        (string)($row['check_out'] ?? '—'),
                        (string)($row['gps_status'] ?? '—'),
                        (string)($row['attendance_status'] ?? '—'),
                        (string)($row['shift_status'] ?? '—'),
                    ];
                    break;
            }

            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            foreach ($rowValues as $colIdx => $val) {
                $colLetter = $this->getColumnLetter($colIdx + 1);
                $xml .= '      <c r="' . $colLetter . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($val) . '</t></is></c>' . "\n";
            }
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Sheet 2: Operational Report Metadata Information
     */
    private function getOperationalMetaSheetXml(array $meta, int $totalCount): string
    {
        $info = [
            ['Report Title', $meta['report_title'] ?? 'Secure360 - Operational Report'],
            ['Organisation', $meta['organization_name'] ?? 'Apex Security'],
            ['Report Type', $meta['report_type_label'] ?? 'All Operations'],
            ['Date Preset Applied', $meta['preset_label'] ?? 'This Month'],
            ['Date Range', ($meta['from_date'] ?? '—') . ' to ' . ($meta['to_date'] ?? '—')],
            ['Client Filter', $meta['client_name'] ?? 'All Clients'],
            ['Site Filter', $meta['site_name'] ?? 'All Sites'],
            ['Guard Filter', $meta['guard_name'] ?? 'All Guards'],
            ['Status Filter', $meta['status_label'] ?? 'All'],
            ['Search Query Applied', !empty($meta['search']) ? $meta['search'] : 'None'],
            ['Total Records Exported', (string)$totalCount],
            ['Generated At', date('d-M-y h:i A')],
            ['Generated By', $meta['generated_by'] ?? 'Admin'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '  <sheetViews><sheetView tabSelected="0" workbookViewId="0"/></sheetViews>' . "\n";
        $xml .= '  <sheetFormatPr defaultRowHeight="18"/>' . "\n";

        $xml .= '  <cols>' . "\n";
        $xml .= '    <col min="1" max="1" width="28" customWidth="1"/>' . "\n";
        $xml .= '    <col min="2" max="2" width="45" customWidth="1"/>' . "\n";
        $xml .= '  </cols>' . "\n";

        $xml .= '  <sheetData>' . "\n";

        $xml .= '    <row r="1" ht="26" customHeight="1">' . "\n";
        $xml .= '      <c r="A1" t="inlineStr" s="1"><is><t>Property</t></is></c>' . "\n";
        $xml .= '      <c r="B1" t="inlineStr" s="1"><is><t>Filter &amp; Audit Metadata Information</t></is></c>' . "\n";
        $xml .= '    </row>' . "\n";

        $rowNum = 2;
        foreach ($info as $item) {
            $xml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            $xml .= '      <c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>' . $this->cleanXml($item[0]) . '</t></is></c>' . "\n";
            $xml .= '      <c r="B' . $rowNum . '" t="inlineStr" s="0"><is><t>' . $this->cleanXml($item[1]) . '</t></is></c>' . "\n";
            $xml .= '    </row>' . "\n";
            $rowNum++;
        }

        $xml .= '  </sheetData>' . "\n";
        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Clean string for safe XML 1.0 embedding: strips invalid control characters and escapes entities
     */
    private function cleanXml(mixed $value): string
    {
        $str = (string)($value ?? '');
        // Strip XML 1.0 invalid control characters: 0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F, 0x7F
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        return htmlspecialchars($clean ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * Convert 1-based column number to Excel column letter (e.g. 1 -> A, 27 -> AA)
     */
    private function getColumnLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $p = ($colNumber - 1) % 26;
            $letter = chr(65 + $p) . $letter;
            $colNumber = (int)(($colNumber - $p) / 26);
        }
        return $letter;
    }
}
