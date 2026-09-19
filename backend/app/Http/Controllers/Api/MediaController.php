<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Response;
use App\Core\Request;
use App\Http\Controllers\Controller;
use App\Services\MediaService;

/**
 * Serves resized uploads (`/media/thumb/480/uploads/2026/01/projects/x.jpg`).
 *
 * Derivatives are generated once with GD and cached under storage/cache/media,
 * so the browser gets a small file and the origin image is never re-encoded on
 * every request.
 */
final class MediaController extends Controller
{
    public function thumb(Request $request): Response
    {
        $width = (int) $request->routeParam('width');
        $path = (string) $request->routeParam('path');

        if ($width < 16 || $width > 2400) {
            throw \App\Core\HttpException::badRequest('Unsupported thumbnail width.');
        }

        // Block traversal before touching the filesystem.
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw \App\Core\HttpException::badRequest('Invalid media path.');
        }

        $media = new MediaService();
        $absolute = $media->absolutePath('uploads/' . ltrim($path, '/'));

        if (!is_file($absolute)) {
            throw \App\Core\HttpException::notFound('Image not found.');
        }

        $derivative = $media->derivative('uploads/' . ltrim($path, '/'), $width);

        if (!is_file($derivative['absolute'] ?? '')) {
            return Response::file($absolute, $this->mimeFor($absolute))->withCache(86400);
        }

        return Response::file((string) $derivative['absolute'], 'image/webp')->withCache(31536000);
    }

    private function mimeFor(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
