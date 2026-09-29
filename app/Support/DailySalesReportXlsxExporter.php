<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

class DailySalesReportXlsxExporter
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sales-report-');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary XLSX file.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open temporary XLSX file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($this->rows($report)));
        $zip->close();

        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read generated XLSX file.');
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, array{value: string|int|float|null, type?: string, style?: int}>>
     */
    private function rows(array $report): array
    {
        $summary = $report['summary'] ?? [];
        $payments = $report['payment_breakdown'] ?? [];
        $filters = $report['filters'] ?? [];
        $transactions = $report['transactions'] ?? [];
        $dateFrom = Carbon::parse($filters['date_from'] ?? now())->format('d M Y');
        $dateTo = Carbon::parse($filters['date_to'] ?? $filters['date_from'] ?? now())->format('d M Y');
        $dateLabel = $dateFrom === $dateTo ? $dateFrom : $dateFrom.' to '.$dateTo;
        $companyName = (string) app(SystemSettings::class)->get('company_name');

        $rows = [
            [['value' => $companyName, 'style' => 1]],
            [['value' => 'Daily Financial Summary', 'style' => 1]],
            [],
            [['value' => 'Date'], ['value' => $dateLabel]],
            [['value' => 'Total Gross Sales'], ['value' => (float) ($summary['total_revenue'] ?? 0), 'type' => 'number', 'style' => 2]],
            [['value' => 'Total Discount'], ['value' => (float) ($summary['discount_total'] ?? 0), 'type' => 'number', 'style' => 2]],
            [['value' => 'Net Daily Revenue'], ['value' => (float) ($summary['total_revenue'] ?? 0), 'type' => 'number', 'style' => 2]],
            [['value' => 'Transaction Count'], ['value' => (int) ($summary['transaction_count'] ?? 0), 'type' => 'number']],
            [],
            [['value' => 'Sales Breakdown', 'style' => 1]],
            [['value' => 'Category', 'style' => 1], ['value' => 'Amount (RM)', 'style' => 1]],
            [['value' => 'Membership Sales'], ['value' => (float) ($summary['membership_sales'] ?? 0), 'type' => 'number', 'style' => 2]],
            [['value' => 'Product Sales'], ['value' => (float) ($summary['product_sales'] ?? 0), 'type' => 'number', 'style' => 2]],
            [['value' => 'PT Sales'], ['value' => (float) ($summary['pt_sales'] ?? 0), 'type' => 'number', 'style' => 2]],
        ];

        if ((float) ($summary['other_sales'] ?? 0) > 0) {
            $rows[] = [['value' => 'Other Sales'], ['value' => (float) $summary['other_sales'], 'type' => 'number', 'style' => 2]];
        }

        $rows[] = [];
        $rows[] = [['value' => 'Payment Breakdown', 'style' => 1]];
        $rows[] = [['value' => 'Payment Method', 'style' => 1], ['value' => 'Amount (RM)', 'style' => 1]];

        foreach ($payments as $method => $amount) {
            $rows[] = [
                ['value' => PaymentMethod::labelFor((string) $method)],
                ['value' => (float) $amount, 'type' => 'number', 'style' => 2],
            ];
        }

        $rows[] = [];
        $rows[] = [['value' => 'Daily Transaction Breakdown', 'style' => 1]];
        $rows[] = [
            ['value' => 'Time', 'style' => 1],
            ['value' => 'Receipt No.', 'style' => 1],
            ['value' => 'Type', 'style' => 1],
            ['value' => 'Description', 'style' => 1],
            ['value' => 'Payment Method', 'style' => 1],
            ['value' => 'Amount (RM)', 'style' => 1],
            ['value' => 'Received By', 'style' => 1],
        ];

        foreach ($transactions as $transaction) {
            $rows[] = [
                ['value' => $transaction['time'] ?? ''],
                ['value' => $transaction['receipt_no'] ?? ''],
                ['value' => $transaction['type_label'] ?? str((string) ($transaction['type'] ?? ''))->headline()->toString()],
                ['value' => $transaction['description'] ?? ''],
                ['value' => PaymentMethod::labelFor((string) ($transaction['payment_method'] ?? ''))],
                ['value' => (float) ($transaction['amount'] ?? 0), 'type' => 'number', 'style' => 2],
                ['value' => $transaction['received_by'] ?? '-'],
            ];
        }

        if ($transactions === []) {
            $rows[] = [['value' => 'No transactions found.']];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, array{value: string|int|float|null, type?: string, style?: int}>>  $rows
     */
    private function worksheetXml(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $cells) {
            $cellXml = [];

            foreach ($cells as $columnIndex => $cell) {
                $cellXml[] = $this->cellXml($rowIndex + 1, $columnIndex + 1, $cell);
            }

            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cellXml).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="42" customWidth="1"/><col min="5" max="5" width="22" customWidth="1"/><col min="6" max="6" width="15" customWidth="1"/><col min="7" max="7" width="20" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  array{value: string|int|float|null, type?: string, style?: int}  $cell
     */
    private function cellXml(int $row, int $column, array $cell): string
    {
        $reference = $this->columnName($column).$row;
        $style = isset($cell['style']) ? ' s="'.$cell['style'].'"' : '';
        $value = $cell['value'] ?? '';

        if (($cell['type'] ?? null) === 'number') {
            return '<c r="'.$reference.'"'.$style.'><v>'.(float) $value.'</v></c>';
        }

        return '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t>'.$this->escape((string) $value).'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Daily Sales" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
