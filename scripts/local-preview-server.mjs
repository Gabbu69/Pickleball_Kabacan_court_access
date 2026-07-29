import { createReadStream, existsSync, statSync } from 'node:fs';
import { mkdirSync } from 'node:fs';
import { createServer } from 'node:http';
import { dirname, extname, join, normalize, resolve, sep } from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(root, 'public');
const storageRoot = join(root, 'storage', 'app');
const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 8000);
const indexFile = join(publicRoot, 'index.php');

mkdirSync(storageRoot, { recursive: true });

const mimeTypes = {
    '.css': 'text/css; charset=UTF-8',
    '.js': 'text/javascript; charset=UTF-8',
    '.json': 'application/json; charset=UTF-8',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.webp': 'image/webp',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.mp4': 'video/mp4',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
};

function publicPathFor(urlPath) {
    let pathname;

    try {
        pathname = decodeURIComponent(new URL(urlPath, `http://${host}:${port}`).pathname);
    } catch {
        return null;
    }

    const candidate = normalize(join(publicRoot, pathname));
    const insidePublic = candidate === publicRoot || candidate.startsWith(publicRoot + sep);

    return insidePublic ? candidate : null;
}

function serveStatic(req, res, filePath) {
    if (!['GET', 'HEAD'].includes(req.method || 'GET')) {
        return false;
    }

    if (!filePath || !existsSync(filePath) || !statSync(filePath).isFile()) {
        return false;
    }

    const type = mimeTypes[extname(filePath).toLowerCase()] || 'application/octet-stream';
    res.writeHead(200, {
        'Content-Type': type,
        'Cache-Control': filePath.includes(`${sep}build${sep}`) ? 'public, max-age=31536000, immutable' : 'no-cache',
    });

    if (req.method === 'HEAD') {
        res.end();
        return true;
    }

    createReadStream(filePath).pipe(res);
    return true;
}

function collectBody(req) {
    if (!req.headers['content-length'] && !req.headers['transfer-encoding']) {
        return Promise.resolve(Buffer.alloc(0));
    }

    return new Promise((resolveBody, rejectBody) => {
        const chunks = [];
        let size = 0;

        req.on('data', (chunk) => {
            size += chunk.length;

            if (size > 25 * 1024 * 1024) {
                rejectBody(new Error('Request body is too large for local preview.'));
                req.destroy();
                return;
            }

            chunks.push(chunk);
        });

        req.on('end', () => resolveBody(Buffer.concat(chunks)));
        req.on('error', rejectBody);
    });
}

function cgiHeaderName(name) {
    return `HTTP_${name.toUpperCase().replaceAll('-', '_')}`;
}

function sendLaravel(req, res, body) {
    console.log(`[${new Date().toISOString()}] ${req.method} ${req.url}`);

    const requestUrl = new URL(req.url || '/', `http://${req.headers.host || `${host}:${port}`}`);
    const env = {
        ...process.env,
        REDIRECT_STATUS: '200',
        GATEWAY_INTERFACE: 'CGI/1.1',
        SCRIPT_FILENAME: indexFile,
        SCRIPT_NAME: '/index.php',
        PHP_SELF: '/index.php',
        DOCUMENT_ROOT: publicRoot,
        REQUEST_METHOD: req.method || 'GET',
        REQUEST_URI: requestUrl.pathname + requestUrl.search,
        QUERY_STRING: requestUrl.search.slice(1),
        SERVER_PROTOCOL: `HTTP/${req.httpVersion}`,
        SERVER_SOFTWARE: 'Codex local Laravel preview',
        SERVER_NAME: host,
        SERVER_PORT: String(port),
        REMOTE_ADDR: req.socket.remoteAddress || '127.0.0.1',
        CONTENT_TYPE: req.headers['content-type'] || '',
        CONTENT_LENGTH: body.length ? String(body.length) : '',
    };

    for (const [name, value] of Object.entries(req.headers)) {
        if (name === 'content-type' || name === 'content-length') {
            continue;
        }

        env[cgiHeaderName(name)] = Array.isArray(value) ? value.join(', ') : String(value || '');
    }

    const php = spawn('php-cgi', [], {
        cwd: root,
        env,
        stdio: ['pipe', 'pipe', 'pipe'],
    });

    const stdout = [];
    const stderr = [];

    php.stdout.on('data', (chunk) => stdout.push(chunk));
    php.stderr.on('data', (chunk) => stderr.push(chunk));

    php.on('error', (error) => {
        res.writeHead(500, { 'Content-Type': 'text/plain; charset=UTF-8' });
        res.end(`Unable to start php-cgi: ${error.message}`);
    });

    php.on('close', (code) => {
        console.log(`[${new Date().toISOString()}] ${req.method} ${req.url} -> php-cgi ${code}`);

        const raw = Buffer.concat(stdout);
        const rawText = raw.toString('latin1');
        const splitAt = rawText.indexOf('\r\n\r\n') >= 0 ? rawText.indexOf('\r\n\r\n') + 4 : rawText.indexOf('\n\n') + 2;

        if (splitAt <= 1) {
            res.writeHead(code === 0 ? 200 : 500, { 'Content-Type': 'text/plain; charset=UTF-8' });
            res.end(code === 0 ? raw : Buffer.concat(stderr).toString('utf8') || raw);
            return;
        }

        const headerText = rawText.slice(0, splitAt).trim();
        const responseBody = raw.subarray(splitAt);
        const headers = {};
        let status = 200;

        for (const line of headerText.split(/\r?\n/)) {
            const separator = line.indexOf(':');

            if (separator < 0) {
                continue;
            }

            const name = line.slice(0, separator);
            const value = line.slice(separator + 1).trim();

            if (name.toLowerCase() === 'status') {
                status = Number(value.split(' ')[0]) || status;
                continue;
            }

            if (headers[name]) {
                headers[name] = Array.isArray(headers[name]) ? [...headers[name], value] : [headers[name], value];
            } else {
                headers[name] = value;
            }
        }

        res.writeHead(status, headers);
        res.end(responseBody);
    });

    php.stdin.end(body);
}

const server = createServer(async (req, res) => {
    try {
        if (serveStatic(req, res, publicPathFor(req.url || '/'))) {
            return;
        }

        const body = await collectBody(req);
        sendLaravel(req, res, body);
    } catch (error) {
        res.writeHead(500, { 'Content-Type': 'text/plain; charset=UTF-8' });
        res.end(error.message);
    }
});

server.listen(port, host, () => {
    console.log(`Laravel preview running at http://${host}:${port}`);
});
