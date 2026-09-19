<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;

/** Inbox: filter, read, mark, archive, delete and bulk operations. */
final class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [];
        $bindings = [];
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $filters[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
            $like = '%' . $search . '%';
            array_push($bindings, $like, $like, $like, $like);
        }

        $state = (string) $request->query('state', 'all');

        if ($state === 'unread') {
            $filters[] = 'is_read = 0 AND is_archived = 0';
        } elseif ($state === 'starred') {
            $filters[] = 'is_starred = 1';
        } elseif ($state === 'archived') {
            $filters[] = 'is_archived = 1';
        } elseif ($state === 'read') {
            $filters[] = 'is_read = 1';
        } else {
            $filters[] = 'is_archived = 0';
        }

        if (($locale = (string) $request->query('locale', '')) !== '') {
            $filters[] = 'locale = ?';
            $bindings[] = $locale;
        }

        $where = ' WHERE ' . implode(' AND ', $filters);

        $perPage = $this->perPage($request, 20, 100);
        $page = $this->page($request);
        $offset = ($page - 1) * $perPage;

        $total = (int) Database::scalar('SELECT COUNT(*) AS aggregate FROM messages' . $where, $bindings);

        $items = Database::select(
            'SELECT id, name, email, phone, company, subject, message, budget, service_interest, locale,
                    is_read, is_starred, is_archived, replied_at, created_at
             FROM messages' . $where . ' ORDER BY is_starred DESC, created_at DESC, id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $bindings
        );

        foreach ($items as $index => $item) {
            $items[$index]['id'] = (int) $item['id'];
            $items[$index]['is_read'] = (bool) $item['is_read'];
            $items[$index]['is_starred'] = (bool) $item['is_starred'];
            $items[$index]['is_archived'] = (bool) $item['is_archived'];
            $items[$index]['excerpt'] = mb_substr(trim((string) $item['message']), 0, 140);
        }

        return Response::json([
            'data' => [
                'items' => $items,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) max(1, ceil($total / $perPage)),
                ],
                'counts' => [
                    'all' => (int) Database::table('messages')->count(),
                    'unread' => (int) Database::table('messages')->where('is_read', 0)->count(),
                    'starred' => (int) Database::table('messages')->where('is_starred', 1)->count(),
                    'archived' => (int) Database::table('messages')->where('is_archived', 1)->count(),
                ],
            ],
        ]);
    }

    /** Opening a message marks it read — one less thing to remember. */
    public function show(Request $request): Response
    {
        $message = Database::table('messages')->where('id', (int) $request->routeParam('id'))->first();

        if ($message === null) {
            throw HttpException::notFound('Message not found.');
        }

        if (!(bool) $message['is_read']) {
            Database::table('messages')->where('id', (int) $message['id'])->update([
                'is_read' => 1,
                'updated_at' => now_utc(),
            ]);
            $message['is_read'] = 1;
        }

        return Response::json(['data' => $message]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id');
        $message = Database::table('messages')->where('id', $id)->first();

        if ($message === null) {
            throw HttpException::notFound('Message not found.');
        }

        $update = [];

        foreach (['is_read', 'is_starred', 'is_archived'] as $flag) {
            if ($request->has($flag)) {
                $update[$flag] = (int) $request->boolean($flag);
            }
        }

        if ($request->has('notes')) {
            $update['notes'] = mb_substr((string) $request->input('notes', ''), 0, 5000);
        }

        if ($request->boolean('replied')) {
            $update['replied_at'] = now_utc();
            $update['is_read'] = 1;
        }

        if ($update === []) {
            throw HttpException::badRequest('Nothing to update.');
        }

        $update['updated_at'] = now_utc();
        Database::table('messages')->where('id', $id)->update($update);

        AuditLogger::log(Auth::id(), 'message.update', 'messages', $id, $update);

        return Response::json(['data' => Database::table('messages')->where('id', $id)->first()]);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id');

        if (Database::table('messages')->where('id', $id)->first() === null) {
            throw HttpException::notFound('Message not found.');
        }

        Database::table('messages')->where('id', $id)->delete();
        AuditLogger::log(Auth::id(), 'message.delete', 'messages', $id);

        return Response::json(['message' => 'Message deleted.', 'data' => ['id' => $id]]);
    }

    /** Bulk actions from the inbox toolbar. */
    public function bulk(Request $request): Response
    {
        $input = $request->validate([
            'ids' => ['required', 'array'],
            'action' => ['required', 'in:read,unread,star,unstar,archive,unarchive,delete'],
        ]);

        $ids = array_values(array_filter(array_map('intval', (array) $input['ids']), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            throw HttpException::badRequest('Select at least one message.');
        }

        $action = (string) $input['action'];

        if ($action === 'delete') {
            Database::table('messages')->whereIn('id', $ids)->delete();
        } else {
            $column = match ($action) {
                'star', 'unstar' => 'is_starred',
                'archive', 'unarchive' => 'is_archived',
                default => 'is_read',
            };
            $value = in_array($action, ['read', 'star', 'archive'], true) ? 1 : 0;

            Database::table('messages')->whereIn('id', $ids)->update([
                $column => $value,
                'updated_at' => now_utc(),
            ]);
        }

        AuditLogger::log(Auth::id(), 'message.bulk.' . $action, 'messages', null, ['ids' => $ids]);

        return Response::json([
            'data' => ['ids' => $ids, 'action' => $action],
            'message' => count($ids) . ' message(s) updated.',
        ]);
    }
}
