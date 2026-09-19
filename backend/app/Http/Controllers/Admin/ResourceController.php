<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Json;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Validator;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResourceRegistry;
use App\Services\MediaService;
use App\Services\SlugService;

/**
 * The data-driven CRUD engine behind 21 admin resources.
 *
 * One controller serves every resource described by `AdminResourceRegistry`:
 * listing with search/filter/sort/pagination, detail, create, update, delete,
 * reorder, toggle and media relations. Because the field definitions live in the
 * registry, the server is the single source of truth — the React forms are
 * generated from `GET /api/admin/schema`, and no rule exists only in the browser.
 */
final class ResourceController extends Controller
{
    /* --------------------------------------------------------------------- */
    /* Schema                                                                */
    /* --------------------------------------------------------------------- */

    /** Field definitions, labels, options and locales for the admin UI. */
    public function schema(Request $request): Response
    {
        $unused = $request;

        return Response::json([
            'data' => [
                'resources' => AdminResourceRegistry::schema(),
                'locales' => $this->locales()->publicList(),
                'default_locale' => $this->locales()->defaultCode(),
                'fallback_locale' => $this->locales()->fallbackCode(),
                'form_types' => AdminResourceRegistry::FORM_TYPES,
                'capabilities' => array_values(array_filter(
                    ['content', 'media', 'messages', 'seo'],
                    static fn (string $capability): bool => Auth::can($capability)
                )),
            ],
        ])->withCache(300);
    }

    /* --------------------------------------------------------------------- */
    /* Read                                                                  */
    /* --------------------------------------------------------------------- */

    public function index(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource);
        $locale = $this->adminLocale($request);
        $fallback = $this->locales()->fallbackCode();

        $titleColumn = $this->titleColumn($resource);
        $slugColumn = $this->slugColumn($resource);
        $foreignKey = (string) ($resource['foreign_key'] ?? '');

        $select = 't.*';

        if ($titleColumn !== null) {
            $select .= ', COALESCE(tr.' . $titleColumn . ', fb.' . $titleColumn . ') AS _title';
        }

        if ($slugColumn !== null) {
            $select .= ', COALESCE(tr.' . $slugColumn . ', fb.' . $slugColumn . ') AS _slug';
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $resource['table'] . ' t';
        $bindings = [];

        if (!empty($resource['translatable']) && $foreignKey !== '') {
            $translationTable = (string) $resource['translation_table'];
            $sql .= ' LEFT JOIN ' . $translationTable . ' tr ON tr.' . $foreignKey . ' = t.id AND tr.lang = ?'
                . ' LEFT JOIN ' . $translationTable . ' fb ON fb.' . $foreignKey . ' = t.id AND fb.lang = ?';
            $bindings[] = $locale;
            $bindings[] = $fallback;
        }

        $where = [];
        $whereBindings = [];
        $searchTerm = trim((string) $request->query('search', ''));

        if ($searchTerm !== '' && $titleColumn !== null && $foreignKey !== '') {
            $like = '%' . $searchTerm . '%';
            $where[] = '(COALESCE(tr.' . $titleColumn . ', fb.' . $titleColumn . ') LIKE ?'
                . ($slugColumn !== null ? ' OR COALESCE(tr.' . $slugColumn . ', fb.' . $slugColumn . ') LIKE ?' : '') . ')';
            $whereBindings[] = $like;

            if ($slugColumn !== null) {
                $whereBindings[] = $like;
            }
        } elseif ($searchTerm !== '' && in_array('email', $this->baseFieldNames($resource), true)) {
            $where[] = 't.email LIKE ?';
            $whereBindings[] = '%' . $searchTerm . '%';
        }

        foreach (($resource['filters'] ?? []) as $filter) {
            $value = $request->query($filter);

            if ($value === null || $value === '') {
                continue;
            }

            if ($value === 'null') {
                $where[] = 't.' . $filter . ' IS NULL';
                continue;
            }

            if (in_array($value, ['0', '1'], true) && $this->isBooleanColumn($resource, (string) $filter)) {
                $where[] = 't.' . $filter . ' = ?';
                $whereBindings[] = (int) $value;
                continue;
            }

            $where[] = 't.' . $filter . ' = ?';
            $whereBindings[] = is_numeric($value) ? (int) $value : (string) $value;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
            $bindings = array_merge($bindings, $whereBindings);
        }

        // The count reuses the same joins and predicates, without LIMIT/OFFSET.
        $countSql = 'SELECT COUNT(*) AS aggregate FROM ' . $resource['table'] . ' t';
        $countBindings = [];

        if (!empty($resource['translatable']) && $foreignKey !== '') {
            $countSql .= ' LEFT JOIN ' . $resource['translation_table'] . ' tr ON tr.' . $foreignKey . ' = t.id AND tr.lang = ?'
                . ' LEFT JOIN ' . $resource['translation_table'] . ' fb ON fb.' . $foreignKey . ' = t.id AND fb.lang = ?';
            $countBindings[] = $locale;
            $countBindings[] = $fallback;
        }

        if ($where !== []) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
            $countBindings = array_merge($countBindings, $whereBindings);
        }

        $total = (int) Database::scalar($countSql, $countBindings);

        // Sorting: only columns the resource actually exposes.
        $allowed = array_merge(
            $resource['list']['columns'] ?? ['id'],
            $resource['list']['order'] ?? ['id'],
            ['id', 'created_at', 'updated_at']
        );
        $sort = (string) $request->query('sort', '');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        if ($sort !== '' && in_array($sort, $allowed, true)) {
            $orderBy = 't.' . $sort . ' ' . $direction . ', t.id DESC';
        } else {
            $order = $resource['list']['order'] ?? ['sort_order', 'id'];
            $parts = [];

            foreach ($order as $column) {
                $parts[] = 't.' . $column . (in_array($column, ['sort_order', 'id'], true) ? ' ASC' : ' DESC');
            }

            $orderBy = implode(', ', $parts);
        }

        $perPage = $this->perPage($request, (int) Config::get('app.admin_per_page', 15), 100);
        $page = $this->page($request);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select($sql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $bindings);

        return Response::json([
            'data' => [
                'items' => array_map(fn (array $row): array => $this->decorateRow($resource, $row, $locale), $rows),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) max(1, ceil($total / $perPage)),
                ],
                'options' => $this->optionsFor($resource, $locale),
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource);
        $id = $this->id($request);
        $row = Database::table($resource['table'])->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('This record no longer exists.');
        }

        return Response::json(['data' => $this->entity($resource, $row, $this->adminLocale($request))]);
    }

    /* --------------------------------------------------------------------- */
    /* Write                                                                 */
    /* --------------------------------------------------------------------- */

    public function store(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource, 'create');
        $this->rejectReadonly($resource);

        $locale = $this->adminLocale($request);
        [$base, $translations, $relations, $seo] = $this->splitInput($resource, $request);

        $base = array_merge($base, [
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ]);

        $id = Database::table($resource['table'])->insertGetId($base);

        $this->syncTranslations($resource, $id, $translations);
        $this->syncRelations($resource, $id, $relations);
        $this->syncSeo($resource, $id, $seo);

        AuditLogger::log(Auth::id(), 'create', $resource['table'], $id, ['key' => $resource['key']]);

        $row = Database::table($resource['table'])->where('id', $id)->first() ?? [];

        return Response::json([
            'data' => $this->entity($resource, $row, $locale),
            'message' => $this->label($resource) . ' created.',
        ], 201);
    }

    public function update(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource, 'update');
        $this->rejectReadonly($resource);

        $id = $this->id($request);
        $existing = Database::table($resource['table'])->where('id', $id)->first();

        if ($existing === null) {
            throw HttpException::notFound('This record no longer exists.');
        }

        $locale = $this->adminLocale($request);
        [$base, $translations, $relations, $seo] = $this->splitInput($resource, $request, $id);

        $base['updated_at'] = now_utc();
        Database::table($resource['table'])->where('id', $id)->update($base);

        $this->syncTranslations($resource, $id, $translations);
        $this->syncRelations($resource, $id, $relations);
        $this->syncSeo($resource, $id, $seo);

        AuditLogger::log(Auth::id(), 'update', $resource['table'], $id, ['key' => $resource['key']]);

        return Response::json([
            'data' => $this->entity($resource, Database::table($resource['table'])->where('id', $id)->first() ?? $existing, $locale),
            'message' => $this->label($resource) . ' saved.',
        ]);
    }

    public function destroy(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource, 'delete');
        $this->rejectReadonly($resource);

        $id = $this->id($request);

        if ($resource['key'] === 'admins' && $id === Auth::id()) {
            throw HttpException::badRequest('You cannot delete the account you are signed in with.');
        }

        $row = Database::table($resource['table'])->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('This record no longer exists.');
        }

        // Deleting a gallery project should not orphan its files permanently,
        // but the media library keeps the assets (they may be reused).
        Database::table($resource['table'])->where('id', $id)->delete();

        AuditLogger::log(Auth::id(), 'delete', $resource['table'], $id, ['key' => $resource['key']]);

        return Response::json([
            'data' => ['id' => $id],
            'message' => $this->label($resource) . ' deleted.',
        ]);
    }

    /** Flip a boolean column (activate/deactivate, feature/unfeature). */
    public function toggle(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource, 'update');
        $this->rejectReadonly($resource);

        $id = $this->id($request);
        $field = (string) $request->input('field', 'is_active');
        $allowed = $this->booleanFields($resource);

        if (!in_array($field, $allowed, true)) {
            throw HttpException::badRequest('That field cannot be toggled.');
        }

        $row = Database::table($resource['table'])->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('This record no longer exists.');
        }

        $value = (int) !((bool) $row[$field]);
        Database::table($resource['table'])->where('id', $id)->update([
            $field => $value,
            'updated_at' => now_utc(),
        ]);

        AuditLogger::log(Auth::id(), 'toggle', $resource['table'], $id, ['field' => $field, 'value' => $value]);

        return Response::json([
            'data' => ['id' => $id, 'field' => $field, 'value' => $value],
            'message' => 'Updated.',
        ]);
    }

    /** Persist a new manual order (array of ids in display order). */
    public function reorder(Request $request): Response
    {
        $resource = $this->resource($request);
        $this->authorize($resource, 'update');
        $this->rejectReadonly($resource);

        $ids = $request->array('ids');
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            throw HttpException::badRequest('No rows were sent to reorder.');
        }

        if (!in_array('sort_order', $this->baseFieldNames($resource), true)) {
            throw HttpException::badRequest('This resource has no manual order.');
        }

        Database::transaction(function () use ($resource, $ids): void {
            foreach ($ids as $index => $id) {
                Database::table($resource['table'])->where('id', $id)->update([
                    'sort_order' => $index + 1,
                    'updated_at' => now_utc(),
                ]);
            }
        });

        AuditLogger::log(Auth::id(), 'reorder', $resource['table'], null, ['ids' => $ids]);

        return Response::json(['message' => 'Order saved.', 'data' => ['ids' => $ids]]);
    }

    /* --------------------------------------------------------------------- */
    /* Résumé files                                                          */
    /* --------------------------------------------------------------------- */

    public function uploadResumeFile(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $resume = Database::table('resumes')->where('id', $id)->first();

        if ($resume === null) {
            throw HttpException::notFound('Résumé not found.');
        }

        $file = $request->file('file');

        if ($file === null) {
            throw HttpException::validation('Please choose a PDF file.', ['file' => ['No file was uploaded.']]);
        }

        $media = new MediaService();
        $stored = $media->storeUpload($file, Auth::id(), 'resumes', 'Résumé ' . $resume['lang']);

        Database::table('resumes')->where('id', $id)->update([
            'file_media_id' => $stored['id'],
            'file_path' => $stored['path'] ?? null,
            'file_size' => $stored['size'] ?? null,
            'updated_at' => now_utc(),
        ]);

        AuditLogger::log(Auth::id(), 'resume.upload', 'resumes', $id, ['media_id' => $stored['id']]);

        return Response::json(['data' => $stored, 'message' => 'Résumé file uploaded.']);
    }

    public function deleteResumeFile(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $resume = Database::table('resumes')->where('id', $id)->first();

        if ($resume === null) {
            throw HttpException::notFound('Résumé not found.');
        }

        Database::table('resumes')->where('id', $id)->update([
            'file_media_id' => null,
            'file_path' => null,
            'updated_at' => now_utc(),
        ]);

        AuditLogger::log(Auth::id(), 'resume.detach', 'resumes', $id);

        return Response::json(['message' => 'Résumé file removed.']);
    }

    /* --------------------------------------------------------------------- */
    /* Input handling                                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Accepts either a structured payload
     *   { base: {...}, translations: { fa: {...} }, relations: {...}, seo: {...} }
     * or a flat one
     *   { title: '…', _translations: { fa: {…} }, technology_ids: [1,2] }
     * and returns [base, translations, relations, seo] — all validated.
     */
    private function splitInput(array $resource, Request $request, ?int $id = null): array
    {
        $payload = $request->body();

        $translationsInput = $payload['translations']
            ?? $payload['translated']
            ?? $payload['_translations']
            ?? [];

        $relationsInput = (array) ($payload['relations'] ?? []);

        foreach ($this->relationNames($resource) as $name) {
            if (isset($payload[$name])) {
                $relationsInput[$name] = $payload[$name];
            }
        }

        $baseInput = (array) ($payload['base'] ?? array_diff_key($payload, array_flip([
            'translations', 'translated', '_translations', 'relations', 'seo', 'id', 'created_at', 'updated_at',
        ] + $this->relationNames($resource))));

        $baseFields = $this->baseFieldNames($resource);
        $base = [];

        foreach ($baseFields as $field) {
            if (!array_key_exists($field, $baseInput)) {
                continue;
            }

            $definition = $resource['fields'][$field] ?? [];
            $base[$field] = $this->castField($field, $definition, $baseInput[$field]);
        }

        $this->validateFields($resource, $this->baseRules($resource), $baseInput, true);

        // Admin accounts: never store a plain password.
        if ($resource['key'] === 'admins' && !empty($payload['password'])) {
            $password = (string) $payload['password'];
            $problems = Security::passwordIssues($password);

            if ($problems !== []) {
                throw HttpException::validation('Please review the highlighted fields.', [
                    'password' => array_map(static fn (string $problem): string => ucfirst($problem) . '.', $problems),
                ]);
            }

            $base['password_hash'] = Security::hashPassword($password);
            $base['must_change_password'] = 0;
        }

        $translations = [];
        $defaultLocale = $this->locales()->defaultCode();

        foreach ($translationsInput as $lang => $values) {
            $lang = (string) $lang;

            if (!$this->locales()->isSupported($lang) || !is_array($values)) {
                continue;
            }

            $clean = [];

            foreach (array_keys($this->translatableFields($resource)) as $field) {
                if (!array_key_exists($field, $values)) {
                    continue;
                }

                $definition = $resource['fields'][$field] ?? [];
                $clean[$field] = $this->castField($field, $definition, $values[$field]);
            }

            // Auto-slug from the title when the editor left it empty.
            foreach ($this->translatableFields($resource) as $field => $definition) {
                if (($definition['type'] ?? '') !== 'slug') {
                    continue;
                }

                $source = (string) ($definition['auto_from'] ?? 'title');

                if (empty($clean[$field]) && !empty($clean[$source])) {
                    // make() slugifies the source text and hands the result to
                    // unique(), which resolves `-2`, `-3` … within this language.
                    $clean[$field] = (new SlugService())->make(
                        (string) $clean[$source],
                        $lang,
                        $id === null ? null : (string) $id,
                        $this->slugType($resource)
                    );
                }
            }

            if ($clean === []) {
                continue;
            }

            $this->validateFields(
                $resource,
                $this->translationRules($resource, $lang === $defaultLocale),
                $values,
                $lang === $defaultLocale
            );

            $translations[$lang] = $clean;
        }

        $relations = [];

        foreach ($this->relationNames($resource) as $name) {
            if (!array_key_exists($name, $relationsInput)) {
                continue;
            }

            $relations[$name] = array_values(array_filter(
                array_map('intval', (array) $relationsInput[$name]),
                static fn (int $value): bool => $value > 0
            ));
        }

        return [$base, $translations, $relations, (array) ($payload['seo'] ?? [])];
    }

    /** @param array<string,mixed> $input */
    private function validateFields(array $resource, array $rules, array $input, bool $strict = true): void
    {
        if ($rules === []) {
            return;
        }

        $relevant = array_intersect_key($input, $rules);

        if (!$strict) {
            // Non-default languages may be partially filled.
            foreach ($rules as $field => $fieldRules) {
                if (!array_key_exists($field, $relevant)) {
                    continue;
                }

                $rules[$field] = array_values(array_filter(
                    $fieldRules,
                    static fn (string $rule): bool => $rule !== 'required'
                ));
                $rules[$field][] = 'sometimes';
            }
        }

        Validator::make($relevant, $rules)->validate();
    }

    /** Rules for the base (non-translated) columns. */
    private function baseRules(array $resource): array
    {
        $rules = [];

        foreach ($resource['fields'] as $field => $definition) {
            if (!empty($definition['translated'])) {
                continue;
            }

            if (($definition['type'] ?? '') === 'password') {
                continue;
            }

            $rules[$field] = array_merge(['sometimes'], $this->normaliseRules($definition['rules'] ?? []));

            if (empty($rules[$field]) || count($rules[$field]) === 1) {
                $rules[$field] = ['sometimes', 'nullable'];
            }
        }

        return $rules;
    }

    /** Rules for the translated columns of one language. */
    private function translationRules(array $resource, bool $requireMandatory): array
    {
        $rules = [];

        foreach ($resource['fields'] as $field => $definition) {
            if (empty($definition['translated'])) {
                continue;
            }

            $fieldRules = $this->normaliseRules($definition['rules'] ?? []);
            $fieldRules = array_values(array_filter($fieldRules, static fn (string $rule): bool => $rule !== 'nullable'));

            if (!$requireMandatory) {
                $fieldRules = array_values(array_filter($fieldRules, static fn (string $rule): bool => $rule !== 'required'));
                $fieldRules[] = 'sometimes';
            } else {
                $fieldRules[] = 'sometimes';
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Registry rules are written as PHP-friendly arrays (['slug', 'max' => 200]);
     * the Validator speaks "rule:parameter" strings.
     *
     * @param array<int|string,mixed> $rules
     * @return array<int,string>
     */
    private function normaliseRules(array $rules): array
    {
        $out = [];

        foreach ($rules as $key => $rule) {
            if (is_string($key)) {
                if (is_array($rule)) {
                    $rule = implode(',', array_map('strval', $rule));
                }

                $out[] = $key . ':' . $rule;
                continue;
            }

            if (is_string($rule) && $rule !== '') {
                $out[] = $rule;
            }
        }

        return $out;
    }

    private function castField(string $field, array $definition, mixed $value): mixed
    {
        $type = (string) ($definition['type'] ?? 'text');

        if ($value === '' && in_array($type, ['number', 'date', 'datetime', 'media', 'select', 'range', 'color'], true)) {
            return null;
        }

        return match ($type) {
            'boolean' => (int) (bool) $value,
            'number' => is_numeric($value) ? (str_contains((string) $value, '.') ? (float) $value : (int) $value) : null,
            'range' => is_numeric($value) ? (int) $value : null,
            'media', 'select' => $value === null ? null : (int) $value,
            'json' => is_array($value) ? Json::encode($value) : (is_string($value) ? $value : null),
            'tags', 'multiselect' => is_array($value) ? Json::encode(array_values($value)) : null,
            'date' => $value === null ? null : substr((string) $value, 0, 10),
            default => $value === null ? null : (is_scalar($value) ? (string) $value : Json::encode($value)),
        };
    }

    /* --------------------------------------------------------------------- */
    /* Relations & translations                                              */
    /* --------------------------------------------------------------------- */

    private function syncTranslations(array $resource, int $id, array $translations): void
    {
        if (empty($resource['translatable']) || $translations === []) {
            return;
        }

        $table = (string) $resource['translation_table'];
        $foreignKey = (string) $resource['foreign_key'];
        $now = now_utc();

        foreach ($translations as $lang => $values) {
            $existing = Database::table($table)->where($foreignKey, $id)->where('lang', (string) $lang)->first();

            if ($existing === null) {
                Database::table($table)->insert($values + [
                    $foreignKey => $id,
                    'lang' => (string) $lang,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            Database::table($table)->where('id', (int) $existing['id'])->update($values + ['updated_at' => $now]);
        }
    }

    private function syncRelations(array $resource, int $id, array $relations): void
    {
        foreach (($resource['relations'] ?? []) as $name => $definition) {
            if (!array_key_exists($name, $relations)) {
                continue;
            }

            $values = $relations[$name];
            $foreignKey = (string) $definition['foreign_key'];
            $relatedKey = (string) $definition['related_key'];
            $orderColumn = $definition['order'] ?? null;
            $now = now_utc();

            Database::table($definition['table'])->where($foreignKey, $id)->delete();

            foreach ($values as $index => $relatedId) {
                $row = [
                    $foreignKey => $id,
                    $relatedKey => $relatedId,
                ];

                if ($orderColumn !== null) {
                    $row[(string) $orderColumn] = $index + 1;
                }

                if ($this->pivotHasTimestamps((string) $definition['table'])) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }

                Database::table($definition['table'])->insert($row);
            }
        }
    }

    /** Mirror seo_title / seo_description (and explicit overrides) into seo_metadata. */
    private function syncSeo(array $resource, int $id, array $seo): void
    {
        $entityType = $this->entityType($resource);
        $now = now_utc();

        $perLanguage = $seo;

        if ($perLanguage === [] && !empty($resource['translatable'])) {
            foreach (Database::table($resource['translation_table'])->where($resource['foreign_key'], $id)->get() as $translation) {
                $perLanguage[(string) $translation['lang']] = [
                    'seo_title' => $translation['seo_title'] ?? null,
                    'seo_description' => $translation['seo_description'] ?? null,
                    'keywords' => $translation['keywords'] ?? null,
                ];
            }
        }

        foreach ($perLanguage as $lang => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $lang = (string) $lang;
            $title = $fields['seo_title'] ?? $fields['title'] ?? null;
            $description = $fields['seo_description'] ?? $fields['description'] ?? null;

            if (($title === null || $title === '') && ($description === null || $description === '')) {
                continue;
            }

            $existing = Database::table('seo_metadata')
                ->where('entity_type', $entityType)
                ->where('entity_id', $id)
                ->where('lang', $lang)
                ->first();

            $payload = [
                'seo_title' => $title,
                'meta_description' => $description,
                'keywords' => $fields['keywords'] ?? null,
                'canonical_url' => $fields['canonical_url'] ?? null,
                'og_title' => $fields['og_title'] ?? $title,
                'og_description' => $fields['og_description'] ?? $description,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                Database::table('seo_metadata')->insert($payload + [
                    'entity_type' => $entityType,
                    'entity_id' => $id,
                    'lang' => $lang,
                    'robots' => 'index,follow',
                    'twitter_card' => 'summary_large_image',
                    'created_at' => $now,
                ]);

                continue;
            }

            Database::table('seo_metadata')->where('id', (int) $existing['id'])->update($payload);
        }
    }

    /** Pivot tables (project_technologies, blog_post_tags) may omit timestamps. */
    private function pivotHasTimestamps(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $cache[$table] = false;

        try {
            $row = Database::table($table)->limit(1)->get();
            $cache[$table] = $row !== [] && array_key_exists('created_at', $row[0]);
        } catch (\Throwable) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /* --------------------------------------------------------------------- */
    /* Presentation helpers                                                  */
    /* --------------------------------------------------------------------- */

    /** List row: base columns + resolved title/slug + relation counts. */
    private function decorateRow(array $resource, array $row, string $locale): array
    {
        $row['id'] = (int) $row['id'];

        foreach (['is_active', 'is_featured', 'is_read', 'is_starred', 'is_archived', 'is_current', 'is_default', 'is_primary'] as $flag) {
            if (array_key_exists($flag, $row)) {
                $row[$flag] = (bool) $row[$flag];
            }
        }

        foreach (['views', 'sort_order', 'sort_order', 'level', 'rating', 'download_count', 'size'] as $numeric) {
            if (array_key_exists($numeric, $row) && $row[$numeric] !== null) {
                $row[$numeric] = (int) $row[$numeric];
            }
        }

        $row['title'] = (string) ($row['_title'] ?? $row['title'] ?? $row['name'] ?? $row['key_name'] ?? $row['email'] ?? ('#' . $row['id']));
        $row['slug'] = $row['_slug'] ?? null;
        $row['edit_url'] = null;

        unset($row['_title'], $row['_slug']);

        if (array_key_exists('password_hash', $row)) {
            unset($row['password_hash']);
        }

        foreach (($resource['relations'] ?? []) as $name => $definition) {
            $row[$name] = Database::table($definition['table'])
                ->where($definition['foreign_key'], (int) $row['id'])
                ->pluck($definition['related_key']);
            $row[$name] = array_map('intval', $row[$name]);
        }

        return $row;
    }

    /** Full entity for the edit screen: translations + relations + seo. */
    private function entity(array $resource, array $row, string $locale): array
    {
        $id = (int) $row['id'];
        unset($row['password_hash']);

        foreach (['is_active', 'is_featured', 'is_read', 'is_starred', 'is_archived', 'is_current', 'is_default', 'is_primary'] as $flag) {
            if (array_key_exists($flag, $row)) {
                $row[$flag] = (bool) $row[$flag];
            }
        }

        $translations = [];

        if (!empty($resource['translatable'])) {
            foreach (Database::table($resource['translation_table'])->where($resource['foreign_key'], $id)->get() as $translation) {
                $lang = (string) $translation['lang'];
                unset($translation['id'], $translation[$resource['foreign_key']], $translation['lang'], $translation['created_at'], $translation['updated_at']);
                $translations[$lang] = $translation + ['lang' => $lang];
            }
        }

        $relations = [];

        foreach (($resource['relations'] ?? []) as $name => $definition) {
            $relations[$name] = array_map('intval', Database::table($definition['table'])
                ->where($definition['foreign_key'], $id)
                ->pluck($definition['related_key']));
        }

        $seo = [];

        foreach (Database::table('seo_metadata')
            ->where('entity_type', $this->entityType($resource))
            ->where('entity_id', $id)
            ->get() as $metadata) {
            $seo[(string) $metadata['lang']] = $metadata;
        }

        return [
            'id' => $id,
            'resource' => $resource['key'],
            'base' => $row,
            'translations' => $translations,
            'relations' => $relations,
            'seo' => $seo,
        ];
    }

    /**
     * Select/multi-select sources: `options` / `source` on a field names a table
     * (or another resource) whose rows should be offered as choices.
     *
     * @return array<string,array<int,array{value:int,label:string}>>
     */
    private function optionsFor(array $resource, string $locale): array
    {
        $out = [];
        $fallback = $this->locales()->fallbackCode();

        foreach ($resource['fields'] as $field => $definition) {
            $source = $definition['options'] ?? $definition['source'] ?? null;

            if (!is_string($source) || $source === '' || in_array($source, ['true', 'false'], true)) {
                continue;
            }

            if ($definition['type'] === 'select' && isset($definition['rules']['in'])) {
                // Static enumerations are declared inline.
                $out[$field] = array_map(
                    static fn (string $value): array => ['value' => $value, 'label' => ucfirst(str_replace('_', ' ', $value))],
                    (array) $definition['rules']['in']
                );
                continue;
            }

            $related = AdminResourceRegistry::get($source);

            if ($related === null) {
                $out[$field] = [];
                continue;
            }

            $titleColumn = $this->titleColumn($related);
            $table = $related['table'];
            $select = 't.id';

            if ($titleColumn !== null) {
                $select .= ', COALESCE(tr.' . $titleColumn . ', fb.' . $titleColumn . ') AS label';
            }

            $sql = 'SELECT ' . $select . ' FROM ' . $table . ' t';

            if (!empty($related['translatable'])) {
                $sql .= ' LEFT JOIN ' . $related['translation_table'] . ' tr ON tr.' . $related['foreign_key'] . ' = t.id AND tr.lang = ?'
                    . ' LEFT JOIN ' . $related['translation_table'] . ' fb ON fb.' . $related['foreign_key'] . ' = t.id AND fb.lang = ?';
            }

            $sql .= ' ORDER BY t.id';

            $rows = Database::select($sql, !empty($related['translatable']) ? [$locale, $fallback] : []);

            $out[$field] = array_map(static fn (array $row): array => [
                'value' => (int) $row['id'],
                'label' => (string) ($row['label'] ?? ('#' . $row['id'])),
            ], $rows);
        }

        return $out;
    }

    /* --------------------------------------------------------------------- */
    /* Guards & metadata                                                     */
    /* --------------------------------------------------------------------- */

    private function resource(Request $request): array
    {
        $key = (string) $request->routeParam('resource');
        $resource = AdminResourceRegistry::get($key);

        if ($resource === null) {
            throw HttpException::notFound('Unknown admin resource: ' . $key);
        }

        return $resource;
    }

    private function authorize(array $resource, string $action = 'read'): void
    {
        if ($resource['key'] === 'admins') {
            if ((string) ((Auth::user() ?? [])['role'] ?? '') !== 'super_admin') {
                throw HttpException::forbidden('Only a super admin can manage admin accounts.');
            }

            return;
        }

        $capability = match ($resource['group'] ?? 'content') {
            'inbox' => 'messages',
            'settings' => 'seo',
            default => $resource['key'] === 'media' ? 'media' : 'content',
        };

        // Tabs that touch the whole site require the SEO/settings capability.
        if (in_array($resource['key'], ['settings', 'languages', 'translations', 'navigation', 'social_links', 'seo_metadata'], true)) {
            $capability = 'seo';
        }

        Auth::require($capability);
        $unused = $action;
    }

    private function rejectReadonly(array $resource): void
    {
        if (!empty($resource['readonly'])) {
            throw HttpException::forbidden('This resource is read-only. Use the inbox actions instead.');
        }
    }

    private function adminLocale(Request $request): string
    {
        $candidate = $request->query('lang') ?? $request->header('X-Locale');

        return $this->locales()->resolve(is_string($candidate) ? $candidate : null);
    }

    private function id(Request $request): int
    {
        $id = (int) $request->routeParam('id');

        if ($id <= 0) {
            throw HttpException::badRequest('A valid record id is required.');
        }

        return $id;
    }

    private function label(array $resource): string
    {
        $label = $resource['label'] ?? $resource['key'];

        return is_array($label) ? (string) ($label['en'] ?? reset($label)) : (string) $label;
    }

    /** @return array<int,string> */
    private function baseFieldNames(array $resource): array
    {
        $names = [];

        foreach ($resource['fields'] as $field => $definition) {
            if (empty($definition['translated']) && empty($definition['virtual']) && ($definition['type'] ?? '') !== 'password') {
                $names[] = (string) $field;
            }
        }

        return $names;
    }

    /** @return array<string,array<string,mixed>> */
    private function translatableFields(array $resource): array
    {
        $fields = [];

        foreach ($resource['fields'] as $field => $definition) {
            if (!empty($definition['translated'])) {
                $fields[(string) $field] = $definition;
            }
        }

        return $fields;
    }

    /** @return array<int,string> */
    private function relationNames(array $resource): array
    {
        return array_map('strval', array_keys($resource['relations'] ?? []));
    }

    /** @return array<int,string> */
    private function booleanFields(array $resource): array
    {
        $fields = [];

        foreach ($resource['fields'] as $field => $definition) {
            if (($definition['type'] ?? '') === 'boolean' && empty($definition['translated'])) {
                $fields[] = (string) $field;
            }
        }

        return $fields;
    }

    private function titleColumn(array $resource): ?string
    {
        $candidates = [];

        foreach ($this->translatableFields($resource) as $field => $definition) {
            $type = (string) ($definition['type'] ?? '');
            $candidates[$type === 'title' ? 0 : ($field === 'name' ? 1 : ($field === 'label' ? 2 : 3))] = $field;
        }

        if ($candidates === []) {
            foreach (['title', 'name', 'label', 'key_name', 'email', 'code'] as $field) {
                if (in_array($field, $this->baseFieldNames($resource), true)) {
                    return null; // resolved directly from the base row
                }
            }

            return null;
        }

        ksort($candidates);
        $column = (string) reset($candidates);

        // A base column with the same name wins (no join needed).
        return in_array($column, $this->baseFieldNames($resource), true) ? null : $column;
    }

    private function slugColumn(array $resource): ?string
    {
        foreach ($this->translatableFields($resource) as $field => $definition) {
            if (($definition['type'] ?? '') === 'slug') {
                return $field;
            }
        }

        return null;
    }

    private function isBooleanColumn(array $resource, string $column): bool
    {
        return in_array($column, $this->booleanFields($resource), true);
    }

    private function slugType(array $resource): string
    {
        return match ($resource['key']) {
            'blog_posts' => 'post',
            'projects' => 'project',
            'services' => 'service',
            'project_categories' => 'project_category',
            'blog_categories' => 'blog_category',
            'blog_tags' => 'blog_tag',
            'pages' => 'page',
            default => (string) ($resource['singular'] ?? $resource['key']),
        };
    }

    private function entityType(array $resource): string
    {
        return match ($resource['key']) {
            'blog_posts' => 'post',
            'projects' => 'project',
            'services' => 'service',
            'pages' => 'page',
            default => (string) $resource['key'],
        };
    }
}
