<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;
use App\Services\MediaService;

/**
 * Media library API: upload, search, paginate, edit alt text/caption per
 * language, inspect usage and delete (with a guard when a file is in use).
 */
final class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $media = new MediaService();

        $result = $media->library([
            'search' => trim((string) $request->query('search', '')),
            'folder' => trim((string) $request->query('folder', '')),
            'type' => trim((string) $request->query('type', '')),
            'sort' => (string) $request->query('sort', 'latest'),
        ], $this->perPage($request, 24, 96), $this->page($request));

        return Response::json([
            'data' => [
                'items' => $result['items'],
                'meta' => [
                    'page' => (int) ($result['page'] ?? 1),
                    'per_page' => (int) ($result['per_page'] ?? 24),
                    'total' => (int) ($result['total'] ?? 0),
                    'total_pages' => (int) ($result['total_pages'] ?? 1),
                ],
                'folders' => $media->folders(),
                'quota' => [
                    'max_size' => $media->maxSize(),
                    'human_max_size' => $media->humanSize($media->maxSize()),
                    'allowed' => $media->allowedExtensions(),
                ],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $files = $request->fileList('files') ?: array_filter([$request->file('file')]);
        $folder = (string) $request->input('folder', 'general');
        $alt = (string) $request->input('alt_text', '');

        if ($files === []) {
            throw HttpException::validation('Please choose at least one file.', ['files' => ['No file was uploaded.']]);
        }

        $media = new MediaService();
        $stored = [];

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $item = $media->storeUpload($file, Auth::id(), $folder, $alt !== '' ? $alt : null);
            $stored[] = $item;

            AuditLogger::log(Auth::id(), 'media.upload', 'media', (int) $item['id'], [
                'filename' => $item['filename'] ?? null,
                'size' => $item['size'] ?? null,
            ]);
        }

        return Response::json([
            'data' => $stored,
            'message' => count($stored) . ' file(s) uploaded.',
        ], 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $media = new MediaService();
        $payload = $request->body();

        $item = $media->update(
            $id,
            [
                'alt_text' => $payload['alt_text'] ?? null,
                'caption' => $payload['caption'] ?? null,
                'folder' => $payload['folder'] ?? null,
            ],
            (array) ($payload['translations'] ?? [])
        );

        AuditLogger::log(Auth::id(), 'media.update', 'media', $id);

        return Response::json(['data' => $item, 'message' => 'Media updated.']);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $force = $request->boolean('force');
        $media = new MediaService();

        $usage = $media->references($id);

        if ($usage !== [] && !$force) {
            return Response::json([
                'data' => ['references' => $usage],
                'message' => 'This file is still used elsewhere. Confirm to delete it anyway.',
            ], 409);
        }

        $deleted = $media->delete($id, $force);

        if (!$deleted) {
            throw HttpException::notFound('Media item not found.');
        }

        AuditLogger::log(Auth::id(), 'media.delete', 'media', $id, ['force' => $force]);

        return Response::json(['data' => ['id' => $id], 'message' => 'File deleted.']);
    }

    /** Bulk delete/rename from the library selection toolbar. */
    public function bulk(Request $request): Response
    {
        $input = $request->validate([
            'ids' => ['required', 'array'],
            'action' => ['required', 'in:delete,folder'],
        ]);

        $ids = array_values(array_filter(array_map('intval', (array) $input['ids']), static fn (int $id): bool => $id > 0));
        $media = new MediaService();

        if ((string) $input['action'] === 'folder') {
            $folder = (string) $request->input('folder', 'general');

            foreach ($ids as $id) {
                $media->update($id, ['folder' => $folder]);
            }

            return Response::json(['message' => count($ids) . ' file(s) moved.']);
        }

        $deleted = 0;
        $blocked = [];

        foreach ($ids as $id) {
            if ($media->references($id) !== []) {
                $blocked[] = $id;
                continue;
            }

            if ($media->delete($id)) {
                $deleted++;
            }
        }

        AuditLogger::log(Auth::id(), 'media.bulk.delete', 'media', null, ['ids' => $ids, 'deleted' => $deleted]);

        return Response::json([
            'data' => ['deleted' => $deleted, 'blocked' => $blocked],
            'message' => $deleted . ' file(s) deleted' . ($blocked === [] ? '.' : '; ' . count($blocked) . ' still in use.'),
        ]);
    }
}
