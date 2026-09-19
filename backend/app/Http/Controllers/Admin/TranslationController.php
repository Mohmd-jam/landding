<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;
use App\Services\TranslationService;

/**
 * UI strings ("Read more", "All projects", …). The React bundle ships built-in
 * defaults; the rows managed here override them at runtime, so every visible
 * label becomes editable without a deploy.
 */
final class TranslationController extends Controller
{
    public function index(Request $request): Response
    {
        $group = (string) $request->query('group', '');
        $search = trim((string) $request->query('search', ''));

        return Response::json([
            'data' => [
                'items' => TranslationService::grouped($group !== '' ? $group : null, $search !== '' ? $search : null),
                'groups' => TranslationService::groups(),
                'locales' => $this->locales()->publicList(),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $body = $request->body();

        $key = trim((string) ($body['key'] ?? ''));
        $group = trim((string) ($body['group'] ?? 'general'));
        $values = (array) ($body['values'] ?? []);

        if (!preg_match('/^[a-zA-Z0-9._-]{2,150}$/', $key)) {
            throw HttpException::validation('Use letters, digits, dots, dashes or underscores.', [
                'key' => ['The key format is invalid.'],
            ]);
        }

        if ($values === []) {
            throw HttpException::validation('Fill at least one language.', ['values' => ['No value provided.']]);
        }

        $clean = [];

        foreach ($values as $lang => $value) {
            if ($this->locales()->isSupported((string) $lang)) {
                $clean[(string) $lang] = mb_substr((string) $value, 0, 5000);
            }
        }

        $id = TranslationService::save($key, $group, $clean);

        AuditLogger::log(Auth::id(), 'translation.save', 'translations', $id, ['key' => $key]);

        return Response::json([
            'data' => ['id' => $id, 'key' => $key],
            'message' => 'String saved.',
        ], 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $row = \App\Core\Database::table('translations')->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('String not found.');
        }

        $body = $request->body();
        $values = (array) ($body['values'] ?? []);
        $clean = [];

        foreach ($values as $lang => $value) {
            if ($this->locales()->isSupported((string) $lang)) {
                $clean[(string) $lang] = mb_substr((string) $value, 0, 5000);
            }
        }

        TranslationService::save(
            (string) $row['key_name'],
            (string) ($body['group'] ?? $row['group_key']),
            $clean
        );

        AuditLogger::log(Auth::id(), 'translation.update', 'translations', $id, ['key' => $row['key_name']]);

        return Response::json(['message' => 'String updated.']);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $row = \App\Core\Database::table('translations')->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('String not found.');
        }

        TranslationService::delete((string) $row['key_name']);
        AuditLogger::log(Auth::id(), 'translation.delete', 'translations', $id, ['key' => $row['key_name']]);

        return Response::json(['message' => 'String deleted (the built-in default applies again).']);
    }
}
