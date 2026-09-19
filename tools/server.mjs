/**
 * HTTP host for the PHP application.
 *
 * Production runs the same codebase behind Apache/Nginx + PHP-FPM (see
 * public/.htaccess and docs/INSTALLATION.md). This sandbox has no native PHP,
 * so the identical front controller is executed through the `@php-wasm/node`
 * runtime — a real PHP 8.3 interpreter compiled to WebAssembly with PDO
 * (mysql + sqlite), mbstring, gd, fileinfo and sessions.
 *
 * Responsibilities of this file:
 *   1. serve static assets straight from public/ (fast path, no PHP boot);
 *   2. map every other request onto public/index.php with realistic
 *      superglobals ($_SERVER, $_GET, $_POST, $_FILES, cookies, raw body);
 *   3. stream PHP's status line, headers (incl. Set-Cookie) and bytes back;
 *   4. gzip textual responses when the client supports it.
 */
import http from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import path from 'node:path';
import { bootPhp, enqueue, PUBLIC_DIR, ROOT, STORAGE_DIR } from './php-runtime.mjs';

const PORT = Number(process.env.PORT || 8080);
const HOST = process.env.HOST || '0.0.0.0';

/** Extensions served directly by Node instead of booting PHP. */
const STATIC_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.map': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.avif': 'image/avif',
    '.gif': 'image/gif',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.pdf': 'application/pdf',
    '.txt': 'text/plain; charset=utf-8',
    '.xml': 'application/xml; charset=utf-8',
    '.webmanifest': 'application/manifest+json',
    '.mp4': 'video/mp4',
    '.svgz': 'image/svg+xml',
};

const COMPRESSIBLE = /^(text\/|application\/(json|xml|manifest\+json|javascript)|image\/svg)/;

function cacheControl(relativePath) {
    if (relativePath.startsWith('app/assets/')) {
        return 'public, max-age=31536000, immutable';
    }
    if (relativePath.startsWith('app/')) {
        return 'public, max-age=3600, must-revalidate';
    }
    if (relativePath.startsWith('uploads/')) {
        return 'public, max-age=2592000';
    }
    return 'no-cache';
}

function serveStatic(req, res, filePath, relativePath) {
    const type = STATIC_TYPES[path.extname(filePath).toLowerCase()] || 'application/octet-stream';
    const stats = statSync(filePath);
    const etag = `W/"${stats.size}-${Number(stats.mtimeMs).toString(36)}"`;

    res.setHeader('Content-Type', type);
    res.setHeader('Content-Length', String(stats.size));
    res.setHeader('ETag', etag);
    res.setHeader('Cache-Control', cacheControl(relativePath));
    res.setHeader('X-Content-Type-Options', 'nosniff');

    if (relativePath.startsWith('uploads/')) {
        // Upload hardening mirrors public/uploads/.htaccess in production.
        res.setHeader('Content-Disposition', 'inline');
    }

    if (req.headers['if-none-match'] === etag) {
        res.writeHead(304).end();
        return;
    }

    if (req.method === 'HEAD') {
        res.writeHead(200).end();
        return;
    }

    res.writeHead(200);
    createReadStream(filePath).pipe(res);
}

function buildServerVars(req, pathname, query) {
    const remote = req.socket.remoteAddress || '127.0.0.1';
    const forwardedProto = String(req.headers['x-forwarded-proto'] || '').split(',')[0].trim();
    const secure = forwardedProto === 'https';

    const vars = {
        SERVER_SOFTWARE: 'portfolio-node-host',
        SERVER_NAME: String(req.headers.host || 'localhost').split(':')[0],
        HTTP_HOST: String(req.headers.host || 'localhost'),
        SERVER_PORT: secure ? '443' : String(PORT),
        SERVER_PROTOCOL: 'HTTP/1.1',
        REQUEST_METHOD: String(req.method || 'GET').toUpperCase(),
        REQUEST_URI: pathname + (query ? `?${query}` : ''),
        QUERY_STRING: query || '',
        SCRIPT_NAME: '/index.php',
        SCRIPT_FILENAME: path.join(PUBLIC_DIR, 'index.php'),
        PHP_SELF: '/index.php',
        DOCUMENT_ROOT: PUBLIC_DIR,
        REMOTE_ADDR: remote.includes(':') ? remote : String(req.headers['x-forwarded-for'] || remote).split(',')[0].trim(),
        REMOTE_PORT: String(req.socket.remotePort || 0),
        REQUEST_TIME: String(Math.floor(Date.now() / 1000)),
        REQUEST_TIME_FLOAT: String(Date.now() / 1000),
        SERVER_ADDR: HOST,
        GATEWAY_INTERFACE: 'CGI/1.1',
        REDIRECT_STATUS: '200',
        CONTENT_TYPE: String(req.headers['content-type'] || ''),
        CONTENT_LENGTH: String(req.headers['content-length'] || ''),
    };

    if (secure) {
        vars.HTTPS = 'on';
        vars.SERVER_PORT = '443';
        vars.HTTP_X_FORWARDED_PROTO = forwardedProto;
    }

    for (const [name, value] of Object.entries(req.headers)) {
        const key = `HTTP_${name.replace(/-/g, '_').toUpperCase()}`;

        if (key !== 'HTTP_CONTENT_TYPE' && key !== 'HTTP_CONTENT_LENGTH') {
            vars[key] = Array.isArray(value) ? value.join(', ') : String(value ?? '');
        }
    }

    // Reject absurdly long header values early — defence in depth.
    for (const [key, value] of Object.entries(vars)) {
        if (typeof value === 'string' && value.length > 8192) {
            vars[key] = value.slice(0, 8192);
        }
    }

    return vars;
}

async function readBody(req) {
    const chunks = [];
    let size = 0;

    for await (const chunk of req) {
        size += chunk.length;
        if (size > 12 * 1024 * 1024) {
            throw new Error('Request body too large');
        }
        chunks.push(chunk);
    }

    return Buffer.concat(chunks);
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url || '/', `http://${req.headers.host || 'localhost'}`);
    const pathname = decodeURIComponent(url.pathname);
    const relativePath = pathname.replace(/^\/+/, '');
    const filePath = path.join(PUBLIC_DIR, relativePath);

    // Static fast path — never serve PHP sources or escape the docroot.
    const isStaticCandidate =
        relativePath !== '' &&
        !relativePath.endsWith('.php') &&
        path.extname(relativePath) !== '' &&
        path.resolve(filePath).startsWith(PUBLIC_DIR) &&
        existsSync(filePath) &&
        statSync(filePath).isFile();

    if (isStaticCandidate) {
        try {
            serveStatic(req, res, filePath, relativePath);
            return;
        } catch (error) {
            console.error('[static]', error.message);
        }
    }

    let body = Buffer.alloc(0);

    try {
        body = await readBody(req);
    } catch (error) {
        res.writeHead(413, { 'Content-Type': 'text/plain; charset=utf-8' }).end('Payload too large');
        return;
    }

    try {
        const response = await enqueue(async () => {
            const php = await bootPhp();

            return php.run({
                scriptPath: path.join(PUBLIC_DIR, 'index.php'),
                relativeUri: pathname,
                method: String(req.method || 'GET').toUpperCase(),
                headers: Object.fromEntries(
                    Object.entries(req.headers).map(([key, value]) => [key, Array.isArray(value) ? value.join(', ') : String(value ?? '')])
                ),
                body: body.length > 0 ? new Uint8Array(body) : undefined,
                $_SERVER: buildServerVars(req, pathname, url.search.replace(/^\?/, '')),
            });
        });

        const status = response.httpStatusCode || 200;
        const headers = response.headers || {};
        const bytes = Buffer.from(response.bytes || new Uint8Array());
        const acceptsGzip = /\bgzip\b/.test(String(req.headers['accept-encoding'] || ''));
        const contentType = String(headers['content-type']?.[0] || headers['Content-Type']?.[0] || 'text/html');

        let payload = bytes;

        if (acceptsGzip && bytes.length > 1024 && COMPRESSIBLE.test(contentType)) {
            payload = gzipSync(bytes, { level: 6 });
        }

        const outHeaders = {};

        for (const [name, values] of Object.entries(headers)) {
            const lower = name.toLowerCase();

            if (['transfer-encoding', 'connection', 'keep-alive', 'content-length'].includes(lower)) {
                continue;
            }

            outHeaders[name] = Array.isArray(values) ? values : [String(values)];
        }

        if (payload !== bytes) {
            outHeaders['Content-Encoding'] = ['gzip'];
            outHeaders['Vary'] = ['Accept-Encoding'];
        }

        outHeaders['Content-Length'] = [String(payload.length)];

        if (response.errors) {
            const text = String(response.errors).trim();
            if (text) {
                console.error(`[php:${status}] ${pathname}\n${text}`);
            }
        }

        res.writeHead(status, outHeaders);
        res.end(req.method === 'HEAD' ? undefined : payload);
    } catch (error) {
        console.error('[php]', error);
        res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' }).end(
            '<!doctype html><meta charset="utf-8"><title>Server error</title>' +
                '<body style="font-family:system-ui;background:#0b0d12;color:#e8edf5;padding:40px">' +
                '<h1 style="font-size:20px">PHP runtime error</h1>' +
                `<pre style="white-space:pre-wrap;font-size:13px;opacity:.8">${String(error?.stack || error)}</pre>`
        );
    }
});

server.on('error', (error) => {
    console.error('[server]', error);
    process.exit(1);
});

await bootPhp();

server.listen(PORT, HOST, () => {
    console.log(`Portfolio CMS dev server → http://${HOST}:${PORT}`);
    console.log(`Document root: ${PUBLIC_DIR}`);
    console.log(`Storage:       ${STORAGE_DIR}`);
    console.log(`Root:          ${ROOT}`);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        console.log(`\n[server] ${signal} received, shutting down`);
        server.close(() => process.exit(0));
        setTimeout(() => process.exit(0), 1500).unref();
    });
}
