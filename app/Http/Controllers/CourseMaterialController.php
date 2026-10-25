<?php

namespace App\Http\Controllers;

use App\Models\CourseMaterial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CourseMaterialController extends Controller
{
    public function file(CourseMaterial $material)
    {
        $material->loadMissing(['offering', 'groups']);
        Gate::authorize('view', $material);
        abort_unless($material->file_disk && $material->file_path, 404);
        abort_unless(Storage::disk($material->file_disk)->exists($material->file_path), 404);

        return Storage::disk($material->file_disk)->response(
            $material->file_path,
            basename((string) $material->original_name),
            [
                'Content-Type' => $material->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            ],
            'inline',
        );
    }
}
