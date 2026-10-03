<?php

use App\Http\Controllers\GoldenTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/golden-tickets', [GoldenTicketController::class, 'show'])->name('tickets.show');
Route::post('/golden-tickets/claim-naively', [GoldenTicketController::class, 'claimNaively'])->name('claim.naively');
Route::post('/golden-tickets/claim-safely', [GoldenTicketController::class, 'claimSafely'])->name('claim.safely');
Route::post('/golden-tickets/reset', [GoldenTicketController::class, 'reset'])->name('tickets.reset');
