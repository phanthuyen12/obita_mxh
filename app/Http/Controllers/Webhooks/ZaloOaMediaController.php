<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ZaloOaMediaController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse|Response
    {
        if (! str_starts_with($path, 'omnichat/zalo/') || str_contains($path, '..')) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
