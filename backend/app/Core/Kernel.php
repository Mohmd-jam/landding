<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * HTTP kernel: turns a Request into a Response.
 *
 * Responsibilities
 *  - method override for HTML forms (_method);
 *  - canonical trailing-slash redirects;
 *  - global exception rendering (HTML for pages, JSON for /api/*);
 *  - security headers on every response (CSP, nosniff, frame policy …).
 */
final class Kernel
{
    public function __construct(private Router $router)
    {
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        try {
            $this->applyMethodOverride($request);

            $redirect = $this->canonicalRedirect($request);

            if ($redirect !== null) {
                return $redirect;
            }

            $response = $this->router->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->renderException($e, $request);
        }

        return $this->applySecurityHeaders($response, $request);
    }

    /** Forms may post _method=PUT/DELETE; only POST can be overridden. */
    private function applyMethodOverride(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            return;
        }

        $override = strtoupper($request->string('_method'));

        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $request->setMethod($override);
            $request->setAttribute('method_override', true);
        }
    }

    private function canonicalRedirect(Request $request): ?Response
    {
        $path = $request->path();

        if ($path !== '/' && str_ends_with($path, '/') && !str_starts_with($path, '/api/')) {
            $target = rtrim($path, '/') . ($request->queryString() !== '' ? '?' . $request->queryString() : '');

            return Response::redirect($target, 301);
        }

        return null;
    }

    public function renderException(Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->statusCode() : 500;

        if ($status >= 500) {
            Logger::exception($e, ['path' => $request->path(), 'method' => $request->method()]);
        }

        $debug = (bool) Config::get('app.debug', false);
        $hidesMessage = $status >= 500 && !$debug;
        $message = $hidesMessage ? 'Something went wrong on our side. Please try again shortly.' : $e->getMessage();

        if ($request->expectsJson() || str_starts_with($request->path(), '/api/')) {
            $payload = ['error' => ['code' => $status, 'message' => $message]];

            if ($e instanceof ValidationException) {
                $payload['error']['fields'] = $e->errors();
            }

            if ($debug && $status >= 500) {
                $payload['error']['debug'] = [
                    'exception' => $e::class,
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ];
            }

            $response = Response::json($payload, $status);
        } else {
            $response = Response::html(View::errorPage($status, $message, $debug), $status);
        }

        if ($e instanceof HttpException) {
            $response->withHeaders($e->headers());
        }

        return $response;
    }

    private function applySecurityHeaders(Response $response, Request $request): Response
    {
        $headers = (array) Config::get('security.headers', []);

        foreach ($headers as $name => $value) {
            $response->withHeader($name, (string) $value);
        }

        if ($request->isSecure()) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $contentType = (string) ($response->headers()['Content-Type'] ?? '');

        if (str_contains($contentType, 'text/html')) {
            $response->withHeader('Content-Security-Policy', (string) Config::get('security.csp'));
        }

        $response->withHeader('X-Powered-By', 'Portfolio CMS');
        $response->withHeader('Vary', 'Accept-Encoding');

        if ($response->isEmpty() && $response->status() === 200) {
            $response->withStatus(204);
        }

        return $response;
    }
}
