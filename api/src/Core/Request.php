<?php
namespace App\Core;

final class Request
{
    private array $server  = [];
    private array $get     = [];
    private array $post    = [];
    private array $cookies = [];
    private array $files   = [];

    private ?array  $headers  = null;
    private ?string $rawBody  = null;
    private ?array  $jsonBody = null;
    private ?array  $formBody = null;
    private array   $attributes = [];

    public function __construct(
        ?array $server  = null,
        ?array $get     = null,
        ?array $post    = null,
        ?array $cookies = null,
        ?array $files   = null
    ) {
        if ($server  !== null) $this->server  = $server;
        if ($get     !== null) $this->get     = $get;
        if ($post    !== null) $this->post    = $post;
        if ($cookies !== null) $this->cookies = $cookies;
        if ($files   !== null) $this->files   = $files;
    }

    /** Capture from PHP superglobals */
    public static function capture(): self
    {
        $inst = new self($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $inst->setRawBody((string)file_get_contents('php://input'));
        return $inst;
    }

    public function method(): string
    {
        $m = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // Header override: X-HTTP-Method-Override: PATCH
        $override = $this->header('X-HTTP-Method-Override');
        if ($override) return strtoupper($override);

        // Form override: POST with _method=PUT|PATCH|DELETE
        $formMethod = strtoupper((string)($this->post['_method'] ?? ''));
        if ($m === 'POST' && in_array($formMethod, ['PUT','PATCH','DELETE'], true)) {
            return $formMethod;
        }
        return $m;
    }

    public function isGet(): bool    { return $this->method() === 'GET'; }
    public function isPost(): bool   { return $this->method() === 'POST'; }
    public function isPut(): bool    { return $this->method() === 'PUT'; }
    public function isPatch(): bool  { return $this->method() === 'PATCH'; }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }

    public function path(): string
    {
        $p = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        return $p === '' ? '/' : $p;
    }

    public function scheme(): string
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') return 'https';
        if (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return 'https';
        return 'http';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
    }

    public function url(): string
    {
        $q = $this->server['QUERY_STRING'] ?? '';
        $qs = $q ? ('?' . $q) : '';
        return $this->scheme() . '://' . $this->host() . $this->path() . $qs;
    }

    public function ip(): string
    {
        if (!empty($this->server['HTTP_CLIENT_IP'])) return $this->server['HTTP_CLIENT_IP'];
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', $this->server['HTTP_X_FORWARDED_FOR']));
            if (!empty($parts[0])) return $parts[0];
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }


    /** Canonicalized header array (e.g., "Content-Type") */
    public function headers(): array
    {
        if ($this->headers !== null) return $this->headers;

        if (function_exists('getallheaders')) {
            $h = getallheaders() ?: [];
        } else {
            $h = [];
            foreach ($this->server as $k => $v) {
                if (strncmp($k, 'HTTP_', 5) === 0) {
                    $name = $this->normalizeHeaderName(substr($k, 5));
                    $h[$name] = $v;
                } elseif (in_array($k, ['CONTENT_TYPE','CONTENT_LENGTH','CONTENT_MD5'], true)) {
                    $name = $this->normalizeHeaderName($k);
                    $h[$name] = $v;
                }
            }
        }

        $norm = [];
        foreach ($h as $k => $v) $norm[$this->normalizeHeaderName($k)] = $v;
        return $this->headers = $norm;
    }

    public function header(string $name, $default = null)
    {
        $key = $this->normalizeHeaderName($name);
        $all = $this->headers();
        return $all[$key] ?? $default;
    }

    public function cookies(): array { return $this->cookies; }
    public function cookie(string $name, $default = null) { return $this->cookies[$name] ?? $default; }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if (!$auth) return null;
        return (stripos($auth, 'Bearer ') === 0) ? trim(substr($auth, 7)) : null;
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) return $this->get;
        return $this->get[$key] ?? $default;
    }

    /** Raw body (never parsed) */
    public function body(): string
    {
        if ($this->rawBody !== null) return $this->rawBody;
        return $this->rawBody = (string)file_get_contents('php://input');
    }

    /** Content-Type without parameters */
    public function contentType(): string
    {
        $ct = (string)$this->header('Content-Type', '');
        $semi = strpos($ct, ';');
        return $semi === false ? trim($ct) : trim(substr($ct, 0, $semi));
    }

    public function isJson(): bool
    {
        $ct = strtolower((string)$this->header('Content-Type', ''));
        return $ct === 'application/json'
            || str_starts_with($ct, 'application/json') // handles "; charset=utf-8"
            || str_ends_with($ct, '+json')              // e.g. merge-patch+json
            || $ct === 'text/json';
    }

    public function json(): array
    {
        if ($this->jsonBody !== null) return $this->jsonBody;
        if (!$this->isJson()) return $this->jsonBody = [];
        $raw = $this->body();
        if ($raw === '') return $this->jsonBody = [];
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return $this->jsonBody = is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            return $this->jsonBody = [];
        }
    }

    /**
     * Form data:
     * - POST: returns $_POST for urlencoded/multipart.
     * - PUT/PATCH/DELETE urlencoded: parse raw body.
     * - PHP doesn’t parse multipart on PUT/PATCH; prefer POST + _method or JSON.
     */
    public function form(): array
    {
        if ($this->formBody !== null) return $this->formBody;

        $ctRaw       = strtolower((string)$this->header('Content-Type', ''));
        $isUrlEnc    = str_starts_with($ctRaw, 'application/x-www-form-urlencoded');
        $isMultipart = str_starts_with($ctRaw, 'multipart/form-data');

        $original = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET'); // transport method

        if ($original === 'POST' && ($isUrlEnc || $isMultipart)) {
            $arr = $this->post;
            unset($arr['_method']);
            return $this->formBody = $arr;
        }

        if ($isUrlEnc) {
            $raw = $this->body();
            $arr = [];
            if ($raw !== '') parse_str($raw, $arr);
            unset($arr['_method']);
            return $this->formBody = is_array($arr) ? $arr : [];
        }

        return $this->formBody = [];
    }

    public function input(?string $key = null, $default = null)
    {
        $merged = $this->form();
        $json   = $this->json();
        if ($json) $merged = array_replace($merged, $json);
        if ($key === null) return $merged;
        return $merged[$key] ?? $default;
    }

    /**
     * Unified request data with method-aware precedence.
     * - GET: query overrides body on key conflicts.
     * - Others: body (JSON > form) overrides a query.
     */
    public function getData(?string $key = null, $default = null)
    {
        // Build body: form then JSON (JSON wins over form)
        $body = $this->form();
        $json = $this->json();
        if ($json) $body = array_replace($body, $json);

        $query = $this->get;

        // Decide precedence by method
        if ($this->isGet()) {
            // GET: query > body
            $merged = array_replace($body, $query);
        } else {
            // Non-GET: body > query
            $merged = array_replace($query, $body);
        }

        if ($key === null) return $merged;
        return $merged[$key] ?? $default;
    }

    public function getFiles(): array
    {
        return $this->files();
    }

    public function file(string $name): ?array { return $this->files[$name] ?? null; }
    public function files(): array { return $this->files; }

    public function withAttr(string $key, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function attr(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getServer(): array { return $this->server; }
    public function setServer(array $server): self { $this->server = $server; $this->headers = null; return $this; }

    public function getGet(): array { return $this->get; }
    public function setGet(array $get): self { $this->get = $get; return $this; }

    public function getPost(): array { return $this->post; }
    public function setPost(array $post): self { $this->post = $post; return $this; }

    public function getCookies(): array { return $this->cookies; }
    public function setCookies(array $cookies): self { $this->cookies = $cookies; return $this; }

    public function setFiles(array $files): self { $this->files = $files; return $this; }

    public function getHeaders(): array { return $this->headers(); } // build if needed
    public function setHeaders(array $headers): self { $this->headers = $headers; return $this; }

    public function getAttributes(): array { return $this->attributes; }
    public function setAttributes(array $attributes): self { $this->attributes = $attributes; return $this; }

    public function setRawBody(?string $raw): self { $this->rawBody = $raw; $this->jsonBody = null; $this->formBody = null; return $this; }

    private function normalizeHeaderName(string $name): string
    {
        $name = str_replace('_', ' ', $name);
        $name = ucwords(strtolower($name));
        return str_replace(' ', '-', $name);
    }

}
