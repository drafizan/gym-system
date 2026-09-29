<?php

namespace App\Http\Controllers;

use App\Enums\SaleType;
use App\Models\User;
use App\Support\DailySalesReport;
use App\Support\DailySalesReportXlsxExporter;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DailySalesReportController extends Controller
{
    public function index(Request $request, DailySalesReport $report, SystemSettings $settings): View
    {
        return view('reports.daily-sales', [
            'report' => $report->generate($request->user(), $request->query()),
            'cashiers' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'paymentMethods' => $settings->paymentMethods(),
            'saleTypes' => SaleType::cases(),
        ]);
    }

    public function export(Request $request, DailySalesReport $report, DailySalesReportXlsxExporter $exporter): Response
    {
        $data = $report->generate($request->user(), $request->query());
        $dateFrom = str_replace('-', '', (string) $data['filters']['date_from']);
        $dateTo = str_replace('-', '', (string) $data['filters']['date_to']);
        $suffix = $dateFrom === $dateTo ? $dateFrom : $dateFrom.'-'.$dateTo;

        return response($exporter->export($data), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="daily-sales-report-'.$suffix.'.xlsx"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
