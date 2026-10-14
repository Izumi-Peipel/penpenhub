<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class AttachmentStore
{
    /** @return array<int, string> */
    public static function fileRules(): array
    {
        return [
            'file',
            'max:'.config('penpenhub.attachments.max_kb'),
            'mimes:'.implode(',', config('penpenhub.attachments.mimes')),
        ];
    }

    /**
     * @param  UploadedFile[]  $files
     * @return Collection<int, Attachment>
     */
    public function store(array $files, Model $attachable, User $by, int $companyId): Collection
    {
        $disk = config('penpenhub.attachments.disk', 'local');

        return collect($files)->map(function (UploadedFile $file) use ($attachable, $by, $companyId, $disk) {
            return Attachment::create([
                'company_id' => $companyId,
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'disk' => $disk,
                'path' => $file->store("attachments/{$companyId}", $disk),
                'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $by->getKey(),
            ]);
        });
    }
}
