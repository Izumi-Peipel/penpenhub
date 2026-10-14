<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TicketController;
use App\Livewire\QuickTicket;
use Illuminate\Support\Facades\Route;

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard', ['user' => auth()->user()->load('memberships.company')]);
    })->name('dashboard');

    Route::post('/context', [ContextController::class, 'update'])->name('context.update');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/new', QuickTicket::class)->name('tickets.new');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('tickets.status');
    Route::post('/tickets/{ticket}/priority', [TicketController::class, 'priority'])->name('tickets.priority');

    // Tautan lampiran: bertanda tangan + berumur pendek + dicek Policy.
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->middleware('signed')
        ->name('attachments.show');
});
