<?php

use App\Http\Controllers\Api\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/api-docs', [ApiDocsController::class, 'index'])->name('api.docs');
Route::get('/api-docs/spec', [ApiDocsController::class, 'spec'])->name('api.docs.spec');
