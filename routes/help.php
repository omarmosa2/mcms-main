<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('help')->group(function () {
    Route::get('/', fn () => inertia('help/Index'))->name('help.index');
    Route::get('/{slug}', fn (string $slug) => inertia('help/Article', ['slug' => $slug]))->name('help.article');
});
