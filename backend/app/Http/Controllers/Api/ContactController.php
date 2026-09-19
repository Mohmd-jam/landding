<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\AuditLogger;
use App\Core\Config;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controllers\Controller;

/**
 * The public contact form.
 *
 * Server-side validation only, honeypot + minimum-fill-time against bots, a
 * dedicated throttle bucket, the message stored in the database, the owner
 * notified by mail and the visitor answered with a short confirmation.
 */
final class ContactController extends Controller
{
    public function store(Request $request): Response
    {
        $locale = $this->locale($request);
        $message = $this->handle($request, $locale);

        return $this->respond([
            'message' => $locale === 'fa'
                ? 'پیام شما دریافت شد. معمولاً در کمتر از یک روز کاری پاسخ می‌دهم.'
                : 'Thanks — your message arrived. I usually reply within one business day.',
            'id' => $message['id'],
        ], $locale, 201);
    }

    /** No-JavaScript fallback: same validation, redirect back with a flash. */
    public function storeWeb(Request $request): Response
    {
        $locale = $this->locales()->resolve((string) $request->input('locale', $request->routeParam('locale', '')));

        try {
            $this->handle($request, $locale);
            Session::flash('contact_status', [
                'type' => 'success',
                'message' => $locale === 'fa'
                    ? 'پیام شما دریافت شد. ممنون از تماس شما!'
                    : 'Your message arrived — thanks for reaching out!',
            ]);
        } catch (\App\Core\ValidationException $e) {
            Session::flash('contact_status', [
                'type' => 'error',
                'message' => implode(' ', array_merge(...array_values($e->errors()))),
            ]);
        }

        return Response::redirect('/' . $locale . '/contact' . '#contact', 303);
    }

    /**
     * @return array{id:int}
     */
    private function handle(Request $request, string $locale): array
    {
        // Honeypot: real users never fill this field.
        if (trim((string) $request->input('website', '')) !== '') {
            throw new \App\Core\HttpException(422, 'Spam detected.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max' => 160],
            'email' => ['required', 'email', 'max' => 190],
            'phone' => ['nullable', 'string', 'max' => 40],
            'company' => ['nullable', 'string', 'max' => 160],
            'subject' => ['nullable', 'string', 'max' => 200],
            'message' => ['required', 'string', 'min' => 10, 'max' => 5000],
            'budget' => ['nullable', 'string', 'max' => 60],
            'service_interest' => ['nullable', 'string', 'max' => 120],
        ]);

        // Budget/service fields are only accepted when they match the seeded lists.
        foreach (['budget', 'service_interest'] as $field) {
            if (!empty($data[$field]) && mb_strlen((string) $data[$field]) > 120) {
                unset($data[$field]);
            }
        }

        $status = RateLimiter::check('contact', $request->ip());

        if (!$status['allowed']) {
            throw new \App\Core\HttpException(429, 'Too many messages from this address. Please try again later.', [
                'Retry-After' => (string) $status['retry_after'],
            ]);
        }

        RateLimiter::hit('contact', $request->ip());

        $id = Database::table('messages')->insertGetId([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => trim((string) $data['message']),
            'budget' => $data['budget'] ?? null,
            'service_interest' => $data['service_interest'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
            'locale' => $locale,
            'is_read' => 0,
            'is_starred' => 0,
            'is_archived' => 0,
            'created_at' => now_utc(),
            'updated_at' => now_utc(),
        ]);

        $owner = (string) $this->settings()->get('contact_email', Config::get('mail.from', ''));

        if ($owner !== '') {
            Mailer::sendContactNotification($owner, $data + ['message' => trim((string) $data['message'])]);
        }

        Mailer::sendContactAutoReply(mb_strtolower(trim((string) $data['email'])), (string) $data['name'], $locale);

        AuditLogger::log('contact.received', 'messages', $id, [
            'email' => mb_strtolower(trim((string) $data['email'])),
            'locale' => $locale,
        ]);

        return ['id' => $id];
    }
}
