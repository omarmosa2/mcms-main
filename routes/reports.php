<?php

use App\Http\Controllers\Reports\AuditReportController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->middleware('permission:reports.view,reports.financial')->name('index');
    Route::get('/export/excel', [ReportController::class, 'exportExcel'])->middleware('permission:reports.view,reports.financial')->name('export.excel');
    Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->middleware('permission:reports.view,reports.financial')->name('export.pdf');
    Route::get('/audit', [AuditReportController::class, 'index'])->middleware('permission:reports.view')->name('audit');
    Route::get('/audit/export', [AuditReportController::class, 'export'])->middleware('permission:reports.view')->name('audit.export');
});
