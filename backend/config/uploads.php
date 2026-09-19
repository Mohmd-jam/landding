<?php

/**
 * Media / upload policy.
 *
 * Uploads are validated on three independent axes (extension, sniffed MIME,
 * size) and renamed to random, extension-safe filenames. The upload directory
 * ships with an .htaccess that disables PHP execution.
 */

use App\Core\Env;

return [
    'driver' => Env::get('MEDIA_DRIVER', 'local'),
    'disk_path' => dirname(__DIR__, 2) . '/public/uploads',
    'url_prefix' => '/uploads',

    'max_size' => (int) Env::get('MEDIA_MAX_SIZE', 5 * 1024 * 1024), // 5 MB

    // Allowed extension => canonical mime types accepted for that extension
    'allowed' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
    ],

    // Image-only pipeline (thumbnails / dimension detection / webp conversion)
    'images' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

    'thumbnails' => [
        'thumb' => ['width' => 480, 'height' => null],
        'medium' => ['width' => 1024, 'height' => null],
    ],

    'convert_to_webp' => Env::bool('MEDIA_WEBP', true),
    'webp_quality' => 82,
    'jpeg_quality' => 85,

    'folders' => ['general', 'projects', 'blog', 'profile', 'resume', 'og', 'branding'],
];
