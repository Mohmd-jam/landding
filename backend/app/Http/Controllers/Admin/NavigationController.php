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
use App\Services\NavigationService;

/**
 * Menus: header items (two levels) and the four footer columns.
 *
 * Labels are stored per language; the location, parent and target are shared,
 * so reordering a menu never has to be repeated for every language.
 */
final class NavigationController extends Controller
{
    public const LOCATIONS = ['header', 'footer_quick', 'footer_services', 'footer_company', 'footer_legal'];
    public const TYPES = ['route', 'url', 'page', 'project_category', 'blog_category', 'anchor'];

    public function index(Request $request): Response
    {
        $unused = $request;

        $fallback = $this->locales()->fallbackCode();
        $rows = Database::table('navigation')->orderBy('location')->orderBy('sort_order')->orderBy('id')->get();
        $labels = [];

        foreach (Database::table('navigation_translations')->get() as $translation) {
            $labels[(int) $translation['navigation_id']][(string) $translation['lang']] = (string) $translation['label'];
        }

        $items = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $items[] = [
                'id' => $id,
                'location' => (string) $row['location'],
                'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'type_name' => (string) $row['type_name'],
                'target_value' => (string) $row['target_value'],
                'icon' => $row['icon'] ?? null,
                'open_in_new_tab' => (bool) $row['open_in_new_tab'],
                'is_active' => (bool) $row['is_active'],
                'sort_order' => (int) $row['sort_order'],
                'labels' => $labels[$id] ?? [$fallback => (string) $row['target_value']],
            ];
        }

        return Response::json([
            'data' => [
                'items' => $items,
                'locations' => self::LOCATIONS,
                'types' => self::TYPES,
                'locales' => $this->locales()->publicList(),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $input = Validator::make($request->body(), [
            'location' => ['required', 'in:' . implode(',', self::LOCATIONS)],
            'type_name' => ['required', 'in:' . implode(',', self::TYPES)],
            'target_value' => ['required', 'string', 'max' => 255],
            'parent_id' => ['nullable', 'int', 'exists:navigation,id'],
            'icon' => ['nullable', 'string', 'max:60'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'int', 'min:0'],
            'labels' => ['required', 'array'],
        ])->validate();

        $labels = array_intersect_key((array) $input['labels'], array_flip($this->locales()->codes()));

        if ($labels === []) {
            throw HttpException::validation('Add a label for at least one language.', ['labels' => ['At least one label is required.']]);
        }

        $now = now_utc();
        $id = Database::table('navigation')->insertGetId([
            'location' => (string) $input['location'],
            'parent_id' => $input['parent_id'] ?? null,
            'type_name' => (string) $input['type_name'],
            'target_value' => (string) $input['target_value'],
            'icon' => $input['icon'] ?? null,
            'open_in_new_tab' => (int) ($input['open_in_new_tab'] ?? false),
            'is_active' => (int) ($input['is_active'] ?? true),
            'sort_order' => (int) ($input['sort_order'] ?? \App\Core\Model::nextSortOrder('location', $input['location'])),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->saveLabels($id, $labels);

        AuditLogger::log(Auth::id(), 'navigation.create', 'navigation', $id, ['location' => $input['location']]);

        return Response::json(['data' => ['id' => $id], 'message' => 'Menu item added.'], 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $row = Database::table('navigation')->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('Menu item not found.');
        }

        if ($id === (int) ($request->body()['parent_id'] ?? 0)) {
            throw HttpException::badRequest('A menu item cannot be its own parent.');
        }

        $input = Validator::make($request->body(), [
            'location' => ['sometimes', 'in:' . implode(',', self::LOCATIONS)],
            'type_name' => ['sometimes', 'in:' . implode(',', self::TYPES)],
            'target_value' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'int', 'exists:navigation,id'],
            'icon' => ['nullable', 'string', 'max:60'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'int', 'min:0'],
            'labels' => ['sometimes', 'array'],
        ])->validate();

        $update = ['updated_at' => now_utc()];
        $nullable = ['parent_id', 'icon'];

        foreach (['location', 'type_name', 'target_value', 'icon', 'open_in_new_tab', 'is_active', 'sort_order', 'parent_id'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $update[$field] = in_array($field, ['open_in_new_tab', 'is_active'], true)
                ? (int) (bool) $input[$field]
                : ($input[$field] === '' && in_array($field, $nullable, true) ? null : $input[$field]);
        }

        Database::table('navigation')->where('id', $id)->update($update);

        if (!empty($input['labels'])) {
            $this->saveLabels($id, array_intersect_key((array) $input['labels'], array_flip($this->locales()->codes())));
        }

        AuditLogger::log(Auth::id(), 'navigation.update', 'navigation', $id, $update);

        return Response::json(['message' => 'Menu item saved.']);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $row = Database::table('navigation')->where('id', $id)->first();

        if ($row === null) {
            throw HttpException::notFound('Menu item not found.');
        }

        // Children are promoted to the parent's level rather than deleted silently.
        Database::table('navigation')->where('parent_id', $id)->update([
            'parent_id' => $row['parent_id'] ?? null,
            'updated_at' => now_utc(),
        ]);

        Database::table('navigation')->where('id', $id)->delete();

        AuditLogger::log(Auth::id(), 'navigation.delete', 'navigation', $id);

        return Response::json(['message' => 'Menu item removed.']);
    }

    /** Drag-and-drop order: receives the ids of one location in display order. */
    public function reorder(Request $request): Response
    {
        $input = $request->validate([
            'ids' => ['required', 'array'],
            'parent_id' => ['nullable', 'int'],
        ]);

        $ids = array_values(array_filter(array_map('intval', (array) $input['ids']), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            throw HttpException::badRequest('No menu items were sent.');
        }

        Database::transaction(function () use ($ids, $input): void {
            foreach ($ids as $index => $id) {
                Database::table('navigation')->where('id', $id)->update([
                    'sort_order' => $index + 1,
                    'parent_id' => $input['parent_id'] ?? null,
                    'updated_at' => now_utc(),
                ]);
            }
        });

        AuditLogger::log(Auth::id(), 'navigation.reorder', 'navigation', null, ['ids' => $ids]);

        return Response::json(['data' => ['ids' => $ids], 'message' => 'Menu order saved.']);
    }

    /** @param array<string,string> $labels */
    private function saveLabels(int $navigationId, array $labels): void
    {
        $now = now_utc();

        foreach ($labels as $lang => $label) {
            $existing = Database::table('navigation_translations')
                ->where('navigation_id', $navigationId)
                ->where('lang', (string) $lang)
                ->first();

            if ($existing === null) {
                if ((string) $label === '') {
                    continue;
                }

                Database::table('navigation_translations')->insert([
                    'navigation_id' => $navigationId,
                    'lang' => (string) $lang,
                    'label' => (string) $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            Database::table('navigation_translations')->where('id', (int) $existing['id'])->update([
                'label' => (string) $label,
                'updated_at' => $now,
            ]);
        }
    }
}
