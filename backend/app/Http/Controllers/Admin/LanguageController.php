<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Http\Controllers\Controller;

/**
 * Language management: add a locale, flip it on/off, change direction or pick a
 * new default. Adding a row here is all it takes — routing, hreflang, sitemap
 * and the admin editors pick the language up on the next request.
 */
final class LanguageController extends Controller
{
    public function index(Request $request): Response
    {
        $unused = $request;

        $languages = Database::table('languages')->orderBy('sort_order')->orderBy('id')->get();

        foreach ($languages as $index => $language) {
            $languages[$index]['id'] = (int) $language['id'];
            $languages[$index]['is_active'] = (bool) $language['is_active'];
            $languages[$index]['is_default'] = (bool) $language['is_default'];
            $languages[$index]['translated_entities'] = $this->translatedCount((string) $language['code']);
        }

        return Response::json([
            'data' => [
                'items' => $languages,
                'stats' => [
                    'total' => count($languages),
                    'active' => count(array_filter($languages, static fn (array $row): bool => (bool) $row['is_active'])),
                    'strings' => (int) Database::table('translations')->count(),
                ],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $input = Validator::make($request->body(), [
            'code' => ['required', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/', 'max' => 8, 'unique:languages,code'],
            'name' => ['required', 'string', 'max' => 80],
            'native_name' => ['required', 'string', 'max' => 80],
            'direction' => ['required', 'in:ltr,rtl'],
            'flag' => ['nullable', 'string', 'max:8'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'int', 'min' => 0],
        ])->validate();

        $now = now_utc();
        $id = Database::table('languages')->insertGetId([
            'code' => (string) $input['code'],
            'name' => (string) $input['name'],
            'native_name' => (string) $input['native_name'],
            'direction' => (string) $input['direction'],
            'flag' => $input['flag'] ?? null,
            'is_active' => (int) ($input['is_active'] ?? true),
            'is_default' => 0,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!empty($input['is_default'])) {
            $this->makeDefault($id);
        }

        $this->locales()->reset();
        AuditLogger::log(Auth::id(), 'language.create', 'languages', $id, ['code' => $input['code']]);

        return Response::json([
            'data' => Database::table('languages')->where('id', $id)->first(),
            'message' => 'Language added. Translate content in the relevant editors.',
        ], 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $language = Database::table('languages')->where('id', $id)->first();

        if ($language === null) {
            throw HttpException::notFound('Language not found.');
        }

        $input = Validator::make($request->body(), [
            'name' => ['sometimes', 'string', 'max:80'],
            'native_name' => ['sometimes', 'string', 'max:80'],
            'direction' => ['sometimes', 'in:ltr,rtl'],
            'flag' => ['nullable', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'int', 'min' => 0],
        ])->validate();

        $update = ['updated_at' => now_utc()];

        foreach (['name', 'native_name', 'direction', 'flag'] as $field) {
            if (array_key_exists($field, $input)) {
                $update[$field] = $input[$field];
            }
        }

        if (array_key_exists('is_active', $input)) {
            // The default language can never be disabled.
            $update['is_active'] = (bool) $language['is_default'] ? 1 : (int) (bool) $input['is_active'];
        }

        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }

        Database::table('languages')->where('id', $id)->update($update);

        if (!empty($request->body()['is_default'])) {
            $this->makeDefault($id);
        }

        $this->locales()->reset();
        AuditLogger::log(Auth::id(), 'language.update', 'languages', $id, $update);

        return Response::json([
            'data' => Database::table('languages')->where('id', $id)->first(),
            'message' => 'Language updated.',
        ]);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $language = Database::table('languages')->where('id', $id)->first();

        if ($language === null) {
            throw HttpException::notFound('Language not found.');
        }

        if ((bool) $language['is_default']) {
            throw HttpException::badRequest('The default language cannot be deleted. Choose another default first.');
        }

        $code = (string) $language['code'];

        $counts = [
            'project_translations' => (int) Database::table('project_translations')->where('lang', $code)->count(),
            'blog_post_translations' => (int) Database::table('blog_post_translations')->where('lang', $code)->count(),
            'page_translations' => (int) Database::table('page_translations')->where('lang', $code)->count(),
            'setting_translations' => (int) Database::table('setting_translations')->where('lang', $code)->count(),
        ];

        $hasContent = array_sum($counts) > 0;

        if ($hasContent && !$request->boolean('force')) {
            return Response::json([
                'data' => ['translations' => $counts],
                'message' => 'This language still has content. Confirm to delete the language and its translations.',
            ], 409);
        }

        Database::transaction(function () use ($code, $id): void {
            foreach ([
                'project_translations', 'blog_post_translations', 'page_translations', 'section_translations',
                'service_translations', 'skill_translations', 'skill_category_translations',
                'experience_translations', 'education_translations', 'certification_translations',
                'testimonial_translations', 'blog_category_translations', 'blog_tag_translations',
                'navigation_translations', 'social_link_translations', 'setting_translations',
                'media_translations', 'translation_values', 'project_category_translations',
            ] as $table) {
                try {
                    Database::table($table)->where('lang', $code)->delete();
                } catch (\Throwable) {
                    // table may not exist on an older install
                }
            }

            Database::table('languages')->where('id', $id)->delete();
        });

        $this->locales()->reset();
        AuditLogger::log(Auth::id(), 'language.delete', 'languages', $id, ['code' => $code]);

        return Response::json(['message' => 'Language and its translations were removed.']);
    }

    /** Count how many entities have a row in this language. */
    private function translatedCount(string $code): array
    {
        return [
            'projects' => (int) Database::table('project_translations')->where('lang', $code)->count(),
            'posts' => (int) Database::table('blog_post_translations')->where('lang', $code)->count(),
            'pages' => (int) Database::table('page_translations')->where('lang', $code)->count(),
        ];
    }

    private function makeDefault(int $id): void
    {
        Database::transaction(function () use ($id): void {
            Database::table('languages')->update(['is_default' => 0, 'updated_at' => now_utc()]);
            Database::table('languages')->where('id', $id)->update(['is_default' => 1, 'is_active' => 1, 'updated_at' => now_utc()]);
        });
    }
}
