<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /** Rute ini wajib 'auth' + 'signed'; Policy tetap dicek agar tautan bocor tidak cukup. */
    public function show(Attachment $attachment)
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        return $attachment->isInline()
            ? $disk->response($attachment->path, $attachment->original_name, $headers, 'inline')
            : $disk->download($attachment->path, $attachment->original_name, $headers);
    }
}
