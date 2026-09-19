<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Str;
use App\Core\ValidationException;

/**
 * Media library: validated storage, metadata, derivatives and safe deletion.
 *
 * Security stance — the client is never trusted:
 *  - the stored extension comes from the *sniffed* MIME type, never the name;
 *  - the stored filename is generated (sha1 + random), so hostile names cannot
 *    survive as a path, and double extensions are impossible;
 *  - the upload directory is hardened with a generated .htaccess that turns PHP
 *    off and blocks script extensions;
 *  - size and MIME are enforced server-side against config/uploads.php.
 */
final class MediaService
{
    /** @var array<string,string> sniffed mime => canonical extension */
    private array $mimeMap = [];

    /** @var array<int,string> */
    private array $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    public function __construct(private ?string $diskPath = null)
    {
        $this->diskPath ??= rtrim((string) Config::get('uploads.disk_path', public_path('uploads')), '/');

        foreach ((array) Config::get('uploads.allowed', []) as $extension => $mimes) {
            foreach ((array) $mimes as $mime) {
                $this->mimeMap[strtolower((string) $mime)] = strtolower((string) $extension);
            }
        }
    }

    public function diskPath(): string
    {
        return $this->diskPath;
    }

    /** @return array<int,string> */
    public function allowedExtensions(): array
    {
        return array_values(array_unique(array_values($this->mimeMap)));
    }

    public function maxSize(): int
    {
        return (int) Config::get('uploads.max_size', 5 * 1024 * 1024);
    }

    public function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, $value < 10 && $index > 0 ? 1 : 0) . ' ' . $units[$index];
    }

    /* --------------------------------------------------------------------- */
    /* Upload                                                                */
    /* --------------------------------------------------------------------- */

    /** @return array<string,mixed> the stored `media` row */
    public function storeUpload(array $file, ?int $adminId = null, ?string $folder = null, ?string $alt = null): array
    {
        $this->assertUploadOk($file);

        $temporary = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $originalName = (string) ($file['name'] ?? basename($temporary));

        if ($size <= 0) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is empty.']);
        }

        if ($size > $this->maxSize()) {
            throw ValidationException::withMessages([
                'file' => 'The file exceeds the ' . round($this->maxSize() / 1048576, 1) . ' MB limit.',
            ]);
        }

        $mime = $this->detectMime($temporary);

        if (!isset($this->mimeMap[$mime])) {
            throw ValidationException::withMessages([
                'file' => 'Unsupported file type (' . $mime . '). Allowed: ' . implode(', ', $this->allowedExtensions()) . '.',
            ]);
        }

        $extension = $this->mimeMap[$mime];

        if (!$this->extensionMatchesName($originalName, $extension) && !in_array($extension, ['jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([
                'file' => 'The file extension does not match its real content type.',
            ]);
        }

        $folder = $this->normaliseFolder($folder);
        $relativeDir = $this->targetDirectory($folder);
        $this->ensureDirectory($relativeDir);

        // De-duplicate identical re-uploads instead of growing the library.
        $existing = Database::table('media')
            ->where('original_name', $originalName)
            ->where('size', $size)
            ->where('mime_type', $mime)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $storedName = date('Ymd') . '-' . substr(sha1($originalName . $size . Str::random(8)), 0, 10) . '.' . $extension;
        $relativePath = $relativeDir . '/' . $storedName;
        $absolute = $this->diskPath . '/' . $relativePath;

        if (!@rename($temporary, $absolute)) {
            if (!@copy($temporary, $absolute)) {
                throw new \RuntimeException('Unable to store the uploaded file.');
            }
            @unlink($temporary);
        }

        @chmod($absolute, 0644);

        $dimensions = $this->dimensions($absolute, $mime);
        $now = now_utc();

        $row = [
            'filename' => $storedName,
            'original_name' => mb_substr($originalName, 0, 200),
            'path' => $relativePath,
            'url' => (string) Config::get('uploads.url_prefix', '/uploads') . '/' . $relativePath,
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'folder' => $folder,
            'alt_text' => $alt,
            'uploaded_by' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->writeHtaccess($this->diskPath);

        $id = Database::table('media')->insertGetId($row);

        if ($alt !== null && $alt !== '') {
            $this->saveTranslation($id, (string) Config::get('app.default_locale', 'fa'), ['alt_text' => $alt]);
        }

        return ['id' => $id] + $row;
    }

    /** Import a file that already exists on disk (seed assets, generated images). */
    public function storeFile(string $absolutePath, ?int $adminId = null, string $folder = 'general', ?string $alt = null): array
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Source file not found: ' . $absolutePath);
        }

        return $this->storeUpload([
            'name' => basename($absolutePath),
            'tmp_name' => $absolutePath,
            'size' => (int) filesize($absolutePath),
            'error' => UPLOAD_ERR_OK,
        ], $adminId, $folder, $alt);
    }

    /* --------------------------------------------------------------------- */
    /* Metadata                                                              */
    /* --------------------------------------------------------------------- */

    /** Store/replace the per-language alt text and caption of a file. */
    public function saveTranslation(int $mediaId, string $lang, array $values): void
    {
        $now = now_utc();
        $payload = [
            'media_id' => $mediaId,
            'lang' => $lang,
            'alt_text' => isset($values['alt_text']) && $values['alt_text'] !== '' ? (string) $values['alt_text'] : null,
            'caption' => isset($values['caption']) && $values['caption'] !== '' ? (string) $values['caption'] : null,
        ];

        $existing = Database::table('media_translations')->where('media_id', $mediaId)->where('lang', $lang)->first();

        if ($existing === null) {
            Database::table('media_translations')->insert($payload + ['created_at' => $now, 'updated_at' => $now]);
        } else {
            Database::table('media_translations')->where('id', (int) $existing['id'])->update([
                'alt_text' => $payload['alt_text'],
                'caption' => $payload['caption'],
                'updated_at' => $now,
            ]);
        }

        // Keep the base columns in sync for the default language.
        if ($lang === (string) Config::get('app.default_locale', 'fa')) {
            Database::table('media')->where('id', $mediaId)->update([
                'alt_text' => $payload['alt_text'],
                'updated_at' => $now,
            ]);
        }
    }

    public function update(int $mediaId, array $data, array $translations = []): array
    {
        $media = Database::table('media')->where('id', $mediaId)->first();

        if ($media === null) {
            throw ValidationException::withMessages(['id' => 'Media item not found.']);
        }

        $update = ['updated_at' => now_utc()];

        foreach (['alt_text', 'caption', 'folder'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field] === null || $data[$field] === '' ? null : (string) $data[$field];
            }
        }

        Database::table('media')->where('id', $mediaId)->update($update);

        foreach ($translations as $lang => $values) {
            if (is_array($values) && $lang !== '' && $lang !== 'id') {
                $this->saveTranslation($mediaId, (string) $lang, $values);
            }
        }

        return $this->find($mediaId) ?? $media;
    }

    /** One media row with translations + usage info, for the admin editor. */
    public function find(int $mediaId): ?array
    {
        $media = Database::table('media')->where('id', $mediaId)->first();

        if ($media === null) {
            return null;
        }

        $media['is_image'] = in_array((string) $media['mime_type'], $this->imageMimes, true);
        $media['size_human'] = $this->humanSize((int) $media['size']);
        $media['references'] = $this->references($mediaId);
        $media['translations'] = [];

        foreach (Database::select('SELECT lang, alt_text, caption FROM media_translations WHERE media_id = ?', [$mediaId]) as $row) {
            $media['translations'][(string) $row['lang']] = [
                'alt_text' => $row['alt_text'],
                'caption' => $row['caption'],
            ];
        }

        return $media;
    }

    /** @return array{items:array,total:int,page:int,per_page:int,total_pages:int,folders:array} */
    public function library(array $filters = [], int $perPage = 24, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $query = Database::table('media');

        if (!empty($filters['folder']) && $filters['folder'] !== 'all') {
            $query->where('folder', (string) $filters['folder']);
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'image') {
                $query->whereIn('mime_type', $this->imageMimes);
            } elseif ($filters['type'] === 'document') {
                $query->whereNotIn('mime_type', $this->imageMimes);
            }
        }

        if (!empty($filters['search'])) {
            $query->whereLikeAny(['original_name', 'alt_text', 'caption', 'filename'], (string) $filters['search']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'DESC')->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $translations = [];
        foreach (Database::select('SELECT media_id, lang, alt_text, caption FROM media_translations') as $row) {
            $translations[(int) $row['media_id']][(string) $row['lang']] = [
                'alt_text' => $row['alt_text'],
                'caption' => $row['caption'],
            ];
        }

        foreach ($items as $index => $item) {
            $items[$index]['is_image'] = in_array((string) $item['mime_type'], $this->imageMimes, true);
            $items[$index]['size_human'] = $this->humanSize((int) $item['size']);
            $items[$index]['translations'] = $translations[(int) $item['id']] ?? [];
            $items[$index]['thumb_url'] = $items[$index]['is_image']
                ? '/media/thumb/480/' . ltrim((string) $item['path'], '/')
                : null;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / $perPage)),
            'folders' => $this->folders(),
        ];
    }

    /** @return array<int,array{folder:string,count:int}> */
    public function folders(): array
    {
        return array_map(static fn (array $row): array => [
            'folder' => (string) $row['folder'],
            'count' => (int) $row['aggregate'],
        ], Database::select('SELECT folder, COUNT(*) AS aggregate FROM media GROUP BY folder ORDER BY folder'));
    }

    /* --------------------------------------------------------------------- */
    /* Deletion & relations                                                  */
    /* --------------------------------------------------------------------- */

    /**
     * Where a media row is referenced — used before deletion so an editor can
     * never silently break a page that renders the file.
     *
     * @return array<int,string>
     */
    public function references(int $mediaId): array
    {
        $checks = [
            'projects.cover' => 'projects|cover_media_id',
            'blog_posts.cover' => 'blog_posts|cover_media_id',
            'blog_posts.og' => 'blog_posts|og_image_media_id',
            'testimonials.avatar' => 'testimonials|avatar_media_id',
            'certifications.image' => 'certifications|image_media_id',
            'experiences.logo' => 'experiences|company_logo_media_id',
            'education.logo' => 'education|logo_media_id',
            'admins.avatar' => 'admins|avatar_media_id',
            'resumes.file' => 'resumes|file_media_id',
            'project_images' => 'project_images|media_id',
        ];

        $found = [];

        foreach ($checks as $label => [$table, $column]) {
            try {
                $count = (int) Database::scalar("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$mediaId]);

                if ($count > 0) {
                    $found[] = $label . ' ×' . $count;
                }
            } catch (\Throwable) {
                // Column not present in this schema version — ignore.
            }
        }

        return $found;
    }

    public function delete(int $mediaId, bool $force = false): bool
    {
        $media = Database::table('media')->where('id', $mediaId)->first();

        if ($media === null) {
            return false;
        }

        $references = $this->references($mediaId);

        if ($references !== [] && !$force) {
            throw ValidationException::withMessages([
                'id' => 'This file is still used by: ' . implode(', ', $references) . '.',
            ]);
        }

        $this->unlinkDerivatives((string) $media['path']);
        $absolute = $this->absolutePath((string) $media['path']);

        if (is_file($absolute)) {
            @unlink($absolute);
        }

        if ($force) {
            Database::table('project_images')->where('media_id', $mediaId)->delete();
        }

        return Database::table('media')->where('id', $mediaId)->delete() > 0;
    }

    public function absolutePath(string $relativePath): string
    {
        $safe = str_replace(['..', "\0"], '', ltrim($relativePath, '/'));

        return $this->diskPath . '/' . $safe;
    }

    /** Resolve a public `/uploads/...` URL (or bare path) to a disk path. */
    public function resolve(string $urlOrPath): ?string
    {
        $prefix = (string) Config::get('uploads.url_prefix', '/uploads');
        $path = str_starts_with($urlOrPath, $prefix) ? substr($urlOrPath, strlen($prefix)) : $urlOrPath;
        $path = ltrim((string) (parse_url($path, PHP_URL_PATH) ?? $path), '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $absolute = $this->diskPath . '/' . $path;

        return is_file($absolute) ? $absolute : null;
    }

    /* --------------------------------------------------------------------- */
    /* Gallery helpers                                                       */
    /* --------------------------------------------------------------------- */

    /** @param array<int,mixed> $mediaIds */
    public function attachToProject(int $projectId, array $mediaIds): int
    {
        $now = now_utc();
        $added = 0;
        $position = (int) Database::scalar('SELECT COALESCE(MAX(sort_order), 0) FROM project_images WHERE project_id = ?', [$projectId]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $mediaIds)))) as $index => $mediaId) {
            if (Database::table('project_images')->where('project_id', $projectId)->where('media_id', $mediaId)->exists()) {
                continue;
            }

            Database::table('project_images')->insert([
                'project_id' => $projectId,
                'media_id' => $mediaId,
                'sort_order' => $position + $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $added++;
        }

        return $added;
    }

    /** @param array<int,mixed> $mediaIds */
    public function syncProjectGallery(int $projectId, array $mediaIds, array $captions = []): void
    {
        Database::table('project_images')->where('project_id', $projectId)->delete();

        $now = now_utc();

        foreach (array_values(array_unique(array_filter(array_map('intval', $mediaIds)))) as $index => $mediaId) {
            Database::table('project_images')->insert([
                'project_id' => $projectId,
                'media_id' => $mediaId,
                'caption' => isset($captions[$mediaId]) && $captions[$mediaId] !== '' ? (string) $captions[$mediaId] : null,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Image derivatives (served by MediaController)                         */
    /* --------------------------------------------------------------------- */

    /**
     * Resize/crop an uploaded image into the cache directory and return the
     * cached file path. Progressive sizes keep mobile payloads small without
     * any external image service.
     *
     * @return array{path:string,mime:string,width:int,height:int}
     */
    public function derivative(string $relativePath, int $width, ?int $height = null): array
    {
        $width = max(16, min($width, 2400));
        $source = $this->absolutePath($relativePath);

        if (!is_file($source)) {
            throw new \RuntimeException('Source image not found.');
        }

        $mime = $this->detectMime($source);

        if (!in_array($mime, $this->imageMimes, true)) {
            throw new \RuntimeException('Not a raster image.');
        }

        $info = @getimagesize($source);

        if (!is_array($info)) {
            throw new \RuntimeException('Unreadable image.');
        }

        [$sourceWidth, $sourceHeight] = [(int) $info[0], (int) $info[1]];
        $targetHeight = $height !== null && $height > 0
            ? max(16, min($height, 2400))
            : (int) round($sourceHeight * ($width / $sourceWidth));

        $cacheDir = rtrim((string) Config::get('app.storage_path', storage_path()), '/') . '/cache/media';
        $cacheName = $width . 'x' . $targetHeight . '-' . sha1($relativePath . filemtime($source)) . '.webp';
        $cachePath = $cacheDir . '/' . $cacheName;

        if (is_file($cachePath)) {
            return ['path' => $cachePath, 'mime' => 'image/webp', 'width' => $width, 'height' => $targetHeight];
        }

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new \RuntimeException('Unable to create the image cache directory.');
        }

        $image = $this->createImage($source, $mime);

        if ($image === null) {
            throw new \RuntimeException('Image decoding failed.');
        }

        $canvas = imagecreatetruecolor($width, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $targetHeight, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $targetHeight, $sourceWidth, $sourceHeight);

        imagewebp($canvas, $cachePath, (int) Config::get('uploads.webp_quality', 82));

        imagedestroy($canvas);
        imagedestroy($image);

        return ['path' => $cachePath, 'mime' => 'image/webp', 'width' => $width, 'height' => $targetHeight];
    }

    private function createImage(string $path, string $mime): ?\GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    private function unlinkDerivatives(string $relativePath): void
    {
        $cacheDir = rtrim((string) Config::get('app.storage_path', storage_path()), '/') . '/cache/media';

        if (!is_dir($cacheDir)) {
            return;
        }

        // Derivative names embed sha1(relativePath + mtime); the prefix is the size.
        foreach (glob($cacheDir . '/*-' . sha1($relativePath . '*') . '.webp') ?: [] as $file) {
            @unlink($file);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Internals                                                             */
    /* --------------------------------------------------------------------- */

    private function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            if (!is_file((string) ($file['tmp_name'] ?? ''))) {
                throw ValidationException::withMessages(['file' => 'Uploaded file is missing.']);
            }

            return;
        }

        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
        ];

        throw ValidationException::withMessages(['file' => $messages[$error] ?? 'Upload failed.']);
    }

    private function detectMime(string $path): string
    {
        $mime = '';

        if (function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($path);
        }

        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = (string) (mime_content_type($path) ?: $mime);
        }

        $mime = strtolower($mime);

        if (str_contains($mime, 'svg')) {
            return 'image/svg+xml';
        }

        // Browsers occasionally report the ICO variants differently.
        if (in_array($mime, ['image/vnd.microsoft.icon', 'image/x-icon'], true)) {
            return 'image/vnd.microsoft.icon';
        }

        return $mime;
    }

    private function extensionMatchesName(string $name, string $extension): bool
    {
        $nameExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($nameExtension === '') {
            return true;
        }

        $aliases = ['jpeg' => 'jpg', 'jpg' => 'jpeg', 'ico' => 'icon', 'icon' => 'ico'];

        return $nameExtension === $extension || ($aliases[$nameExtension] ?? null) === $extension;
    }

    private function normaliseFolder(?string $folder): string
    {
        $allowed = (array) Config::get('uploads.folders', ['general']);
        $folder = strtolower(trim((string) $folder));

        if ($folder === '' || !in_array($folder, array_map('strtolower', $allowed), true)) {
            return 'general';
        }

        return $folder;
    }

    private function targetDirectory(string $folder): string
    {
        return date('Y/m') . '/' . $folder;
    }

    private function ensureDirectory(string $relative): void
    {
        $absolute = $this->diskPath . '/' . $relative;

        if (!is_dir($absolute) && !mkdir($absolute, 0755, true) && !is_dir($absolute)) {
            throw new \RuntimeException('Unable to create the upload directory: ' . $relative);
        }
    }

    /** @return array{width:?int,height:?int} */
    private function dimensions(string $absolute, string $mime): array
    {
        if (!in_array($mime, $this->imageMimes, true)) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($absolute);

        return is_array($size)
            ? ['width' => (int) $size[0], 'height' => (int) $size[1]]
            : ['width' => null, 'height' => null];
    }

    /** Apache/LiteSpeed rule set that makes the upload directory inert. */
    public function writeHtaccess(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $target = rtrim($directory, '/') . '/.htaccess';

        if (is_file($target) && filesize($target) > 0) {
            return;
        }

        $rules = <<<'HTACCESS'
        # Media library: serve files, never execute them.
        php_flag engine off
        AddType text/plain .php .php3 .php4 .php5 .php7 .phtml .phar .pl .py .cgi .asp .aspx .jsp .sh .exe
        RemoveHandler .php .phtml .phar .pl .py .cgi .asp .aspx .jsp .sh
        Options -ExecCGI -Indexes

        <FilesMatch "\.(php|php[0-9]|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|exe|so|dll|htaccess)$">
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Deny from all
            </IfModule>
        </FilesMatch>

        <IfModule mod_headers.c>
            Header set X-Content-Type-Options "nosniff"
            Header set Content-Disposition "inline"
        </IfModule>
        HTACCESS;

        @file_put_contents($target, str_replace("\n        ", "\n", $rules) . "\n");
    }
}
