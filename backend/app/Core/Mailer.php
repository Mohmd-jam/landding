<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Mail delivery with three transports:
 *   - smtp : real SMTP conversation (STARTTLS or implicit TLS), no dependency;
 *   - mail : PHP mail() when a local MTA is configured;
 *   - log  : writes .eml files into storage/mail (default in this environment).
 *
 * The transport is chosen from MAIL_TRANSPORT, so the same code runs on a
 * shared host, a VPS or inside the sandbox preview without changes.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $from = (string) Env::get('MAIL_FROM', 'no-reply@localhost');
        $fromName = (string) Env::get('MAIL_FROM_NAME', (string) Config::get('app.name', 'Portfolio'));
        $transport = strtolower((string) Env::get('MAIL_TRANSPORT', 'log'));

        $attachments = [];
        $html = self::wrapTemplate($subject, $htmlBody, $attachments);

        try {
            return match ($transport) {
                'smtp' => self::sendSmtp($to, $subject, $html, $from, $fromName, $replyTo),
                'mail' => self::sendNative($to, $subject, $html, $from, $fromName, $replyTo),
                default => self::sendToLog($to, $subject, $html, $from, $fromName),
            };
        } catch (\Throwable $e) {
            Logger::error('Mail delivery failed', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Notify the site owner about a new contact message. */
    public static function sendContactNotification(string $to, array $message): bool
    {
        $rows = '';

        foreach (['name', 'email', 'phone', 'company', 'budget', 'service_interest', 'subject'] as $field) {
            if (!empty($message[$field])) {
                $rows .= '<tr><th style="text-align:left;padding:6px 12px 6px 0;color:#64748b;font-weight:600">'
                    . Security::escape($field) . '</th><td style="padding:6px 0">'
                    . Security::escape((string) $message[$field]) . '</td></tr>';
            }
        }

        $body = '<p style="margin:0 0 16px">A new message arrived from the portfolio contact form.</p>'
            . '<table style="border-collapse:collapse;font-size:14px">' . $rows . '</table>'
            . '<div style="margin-top:20px;padding:16px;background:#0f172a;border-radius:12px;color:#e2e8f0;white-space:pre-wrap">'
            . Security::escape((string) ($message['message'] ?? '')) . '</div>';

        return self::send($to, 'New contact message: ' . (string) ($message['subject'] ?? 'Portfolio'), $body, (string) ($message['email'] ?? ''));
    }

    /** Confirmation e-mail sent to the visitor after a successful submit. */
    public static function sendContactAutoReply(string $to, string $name, string $locale = 'fa'): bool
    {
        $isFa = $locale === 'fa';

        $body = $isFa
            ? '<p>' . Security::escape($name) . ' عزیز،</p><p>پیام شما دریافت شد. معمولاً در کمتر از ۲۴ ساعت پاسخ می‌دهم.</p>'
                . '<p>این ایمیل به‌صورت خودکار ارسال شده است.</p>'
            : '<p>Hi ' . Security::escape($name) . ',</p><p>Thanks for reaching out — I received your message and usually reply within one business day.</p>'
                . '<p>This is an automated confirmation.</p>';

        return self::send($to, $isFa ? 'پیام شما دریافت شد' : 'I received your message', $body);
    }

    public static function sendPasswordReset(string $email, string $name, string $url): bool
    {
        $body = '<p>Hi ' . Security::escape($name) . ',</p>'
            . '<p>Use the link below to choose a new password. It expires in 60 minutes and can be used once.</p>'
            . '<p><a href="' . Security::escape($url) . '" style="display:inline-block;padding:12px 18px;background:#34d399;color:#04150f;border-radius:10px;text-decoration:none;font-weight:700">Set a new password</a></p>'
            . '<p style="color:#64748b;font-size:13px">If you did not request this, you can safely ignore the message.</p>';

        return self::send($email, 'Reset your admin password', $body);
    }

    /* --------------------------------------------------------------------- */
    /* Transports                                                            */
    /* --------------------------------------------------------------------- */

    private static function sendSmtp(string $to, string $subject, string $html, string $from, string $fromName, ?string $replyTo): bool
    {
        $host = (string) Env::get('MAIL_HOST', '');
        $port = (int) Env::get('MAIL_PORT', 587);
        $username = (string) Env::get('MAIL_USERNAME', '');
        $password = (string) Env::get('MAIL_PASSWORD', '');
        $encryption = strtolower((string) Env::get('MAIL_ENCRYPTION', 'tls'));

        if ($host === '') {
            return self::sendToLog($to, $subject, $html, $from, $fromName);
        }

        $scheme = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $error, 12, STREAM_CLIENT_CONNECT);

        if (!is_resource($socket)) {
            throw new \RuntimeException('SMTP connect failed: ' . $error);
        }

        stream_set_timeout($socket, 12);

        $read = static function () use ($socket): string {
            $data = '';

            while ($line = fgets($socket, 515)) {
                $data .= $line;

                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }

            return $data;
        };

        $write = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        $read();
        $hostname = gethostname() ?: 'localhost';
        $write('EHLO ' . $hostname);
        $ehlo = $read();

        if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $read();

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Unable to start TLS on the SMTP connection.');
            }

            $write('EHLO ' . $hostname);
            $read();
        }

        if ($username !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($username));
            $read();
            $write(base64_encode($password));
            $auth = $read();

            if (!str_starts_with(trim($auth), '235')) {
                throw new \RuntimeException('SMTP authentication rejected.');
            }
        }

        $headers = [
            'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        $write('MAIL FROM: <' . $from . '>');
        $read();
        $write('RCPT TO: <' . $to . '>');
        $read();
        $write('DATA');
        $read();

        $write(implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html)));
        fwrite($socket, ".\r\n");
        $result = $read();
        $write('QUIT');
        fclose($socket);

        return str_starts_with(trim($result), '250');
    }

    private static function sendNative(string $to, string $subject, string $html, string $from, string $fromName, ?string $replyTo): bool
    {
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
            . 'From: ' . self::encodeName($fromName) . ' <' . $from . ">\r\n";

        if ($replyTo !== null && $replyTo !== '') {
            $headers .= 'Reply-To: <' . $replyTo . ">\r\n";
        }

        return @mail($to, self::encodeHeader($subject), $html, $headers);
    }

    /** Development transport: a real .eml file per message, viewable locally. */
    private static function sendToLog(string $to, string $subject, string $html, string $from, string $fromName): bool
    {
        $directory = rtrim((string) Config::get('app.storage_path', sys_get_temp_dir()), '/') . '/mail';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $file = $directory . '/' . gmdate('Ymd-His') . '-' . substr(sha1($to . $subject . microtime()), 0, 8) . '.eml';

        $contents = 'From: ' . self::encodeName($fromName) . ' <' . $from . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . self::encodeHeader($subject) . "\r\n"
            . 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html;

        return @file_put_contents($file, $contents) !== false;
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                               */
    /* --------------------------------------------------------------------- */

    /** @param array<int,string> $unused */
    private static function wrapTemplate(string $title, string $content, array $unused = []): string
    {
        $name = Security::escape((string) Config::get('app.name', 'Portfolio'));

        return '<!doctype html><html><body style="margin:0;background:#0b0d12;padding:28px;font-family:Inter,system-ui,sans-serif;color:#e8edf5">'
            . '<div style="max-width:600px;margin:0 auto;background:#111722;border:1px solid #1f2937;border-radius:16px;padding:28px">'
            . '<p style="margin:0 0 18px;font-size:13px;letter-spacing:.14em;text-transform:uppercase;color:#34d399">' . $name . '</p>'
            . '<h1 style="margin:0 0 18px;font-size:20px">' . Security::escape($title) . '</h1>'
            . '<div style="font-size:15px;line-height:1.7;color:#cbd5e1">' . $content . '</div>'
            . '</div></body></html>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function encodeName(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) === 1 ? self::encodeHeader($value) : '"' . str_replace('"', '', $value) . '"';
    }
}
