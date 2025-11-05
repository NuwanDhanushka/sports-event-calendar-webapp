<?php
namespace App\Core;

/**
 * Request class
 * Represents a request in the system
 * Handles parsing of request data from the client
 */

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
    private ?array $filesNormalized = null;
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

    /**
     * Capture from PHP superglobals and raw body
     * @return self
     */
    public static function capture(): self
    {
        $inst = new self($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $inst->setRawBody((string)file_get_contents('php://input'));
        return $inst;
    }

    /**
     * Get the HTTP method of the request (GET, POST, PUT, PATCH, DELETE)
     * - Header override: X-HTTP-Method-Override: PATCH
     * - Form override: POST with _method=PUT|PATCH|DELETE
     * @return string
     */
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

    /** helpers */

    public function isGet(): bool    { return $this->method() === 'GET'; }
    public function isPost(): bool   { return $this->method() === 'POST'; }
    public function isPut(): bool    { return $this->method() === 'PUT'; }
    public function isPatch(): bool  { return $this->method() === 'PATCH'; }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }

    /**
     * URL path (without query string)
     * @return string
     */
    public function path(): string
    {
        $p = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        return $p === '' ? '/' : $p;
    }

    /**
     * Http scheme (http or https)
     * @return string
     */
    public function scheme(): string
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') return 'https';
        if (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return 'https';
        return 'http';
    }

    /**
     * Host header with server name fallback
     * @return string
     */
    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
    }

    /**
     * URL with scheme, host, path, and query string
     * @return string
     */
    public function url(): string
    {
        $q = $this->server['QUERY_STRING'] ?? '';
        $qs = $q ? ('?' . $q) : '';
        return $this->scheme() . '://' . $this->host() . $this->path() . $qs;
    }

    /**
     * Client IP address
     * @return string
     */
    public function ip(): string
    {
        if (!empty($this->server['HTTP_CLIENT_IP'])) return $this->server['HTTP_CLIENT_IP'];
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', $this->server['HTTP_X_FORWARDED_FOR']));
            if (!empty($parts[0])) return $parts[0];
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }


    /**
     * Canonicalized header array (e.g., "Content-Type")
     * @return array
     */
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

    /**
     * Get a header by name or default
     * @param string $name
     * @param $default
     * @return mixed|null
     */
    public function header(string $name, $default = null)
    {
        $key = $this->normalizeHeaderName($name);
        $all = $this->headers();
        return $all[$key] ?? $default;
    }

    /**
     * Get all cookies
     * @return array
     */
    public function cookies(): array { return $this->cookies; }

    /**
     * Get a cookie by name or default
     * @param string $name
     * @param $default
     * @return mixed|null
     */
    public function cookie(string $name, $default = null) { return $this->cookies[$name] ?? $default; }

    /**
     * Extract Bearer token from Authorization header
     * @return string|null
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if (!$auth) return null;
        return (stripos($auth, 'Bearer ') === 0) ? trim(substr($auth, 7)) : null;
    }

    /**
     * Query string: $_GET
     * - GET: query overrides body on key conflicts.
     * - Others: body (JSON > form) overrides a query.
     * @param string|null $key
     * @param $default
     * @return array|mixed|null
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) return $this->get;
        return $this->get[$key] ?? $default;
    }

    /**
     * Raw body (or empty string if not present)
     * @return string
     */
    public function body(): string
    {
        if ($this->rawBody !== null) return $this->rawBody;
        return $this->rawBody = (string)file_get_contents('php://input');
    }

    /**
     * Content-Type header (without parameters)
     * @return string
     */
    public function contentType(): string
    {
        $ct = (string)$this->header('Content-Type', '');
        $semi = strpos($ct, ';');
        return $semi === false ? trim($ct) : trim(substr($ct, 0, $semi));
    }

    /**
     * Check if Content-Type is JSON
     * @return bool
     */
    public function isJson(): bool
    {
        $ct = strtolower((string)$this->header('Content-Type', ''));
        return $ct === 'application/json'
            || str_starts_with($ct, 'application/json') // handles "; charset=utf-8"
            || str_ends_with($ct, '+json')              // e.g. merge-patch+json
            || $ct === 'text/json';
    }

    /**
     * Decode JSON body (or empty array if not JSON)
     * @return array
     */
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

    /**
     * Input data: form + json
     * - JSON > form
     * - GET: query overrides body on key conflicts.
     * - Others: body (JSON > form) overrides a query.
     * @param string|null $key
     * @param $default
     * @return array|mixed|null
     */
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
     *  - GET: query overrides body on key conflicts.
     *  - Others: body (JSON > form) overrides a query.
     * @param string|null $key
     * @param $default
     * @return array|mixed|null
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

    /**
     * Get all files alias for files()
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files();
    }

    /**
     * First file for a field (or null)
     * @param string $field
     * @return array|null
     */
    public function file(string $field): ?array
    {
        $all = $this->files();
        return $all[$field][0] ?? null;
    }

    /**
     * All files for a field (possibly empty array)
     * @param string $field
     * @return array
     */
    public function filesOf(string $field): array
    {
        $all = $this->files();
        return $all[$field] ?? [];
    }

    /**
     * Normalize $_FILES array to match PHP's $_FILES format.'
     * @return array
     */
    public function files(): array
    {
        if ($this->filesNormalized !== null) return $this->filesNormalized;
        $src = is_array($this->files) ? $this->files : [];
        return $this->filesNormalized = self::normalizeFilesArray($src);
    }

    /**
     * normalize single/multiple upload shapes into a uniform list
     * @param array $files
     * @return array
     */
    private static function normalizeFilesArray(array $files): array
    {
        $out = [];
        foreach ($files as $field => $spec) {
            // Single upload
            if (is_string($spec['name'] ?? null)) {
                $out[$field] = [[
                    'name'     => $spec['name'],
                    'type'     => $spec['type']     ?? '',
                    'tmp_name' => $spec['tmp_name'] ?? '',
                    'error'    => $spec['error']    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $spec['size']     ?? 0,
                ]];
                continue;
            }

            // Multiple upload: name[], type[], ...
            $count = count($spec['name'] ?? []);
            $out[$field] = [];
            for ($i = 0; $i < $count; $i++) {
                $out[$field][] = [
                    'name'     => $spec['name'][$i]     ?? '',
                    'type'     => $spec['type'][$i]     ?? '',
                    'tmp_name' => $spec['tmp_name'][$i] ?? '',
                    'error'    => $spec['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $spec['size'][$i]     ?? 0,
                ];
            }
        }
        return $out;
    }

    /**
     * Clone-with attribute (immutable style
     * @param string $key
     * @param $value
     * @return $this
     */
    public function withAttr(string $key, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Read an attribute or default
     * @param string $key
     * @param $default
     * @return mixed|null
     */
    public function attr(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /** getters and setters */

    public function getServer(): array { return $this->server; }
    public function setServer(array $server): self { $this->server = $server; $this->headers = null; return $this; }

    public function getGet(): array { return $this->get; }
    public function setGet(array $get): self { $this->get = $get; return $this; }

    public function getPost(): array { return $this->post; }
    public function setPost(array $post): self { $this->post = $post; return $this; }

    public function getCookies(): array { return $this->cookies; }
    public function setCookies(array $cookies): self { $this->cookies = $cookies; return $this; }

    public function setFiles(array $files): self {
        $this->files = $files;
        $this->filesNormalized = null;
        return $this;
    }

    public function getHeaders(): array { return $this->headers(); } // build if needed
    public function setHeaders(array $headers): self { $this->headers = $headers; return $this; }

    public function getAttributes(): array { return $this->attributes; }
    public function setAttributes(array $attributes): self { $this->attributes = $attributes; return $this; }

    /**
     * Set raw body and clear cached parsed data
     * @param string|null $raw
     * @return $this
     */
    public function setRawBody(?string $raw): self { $this->rawBody = $raw; $this->jsonBody = null; $this->formBody = null; return $this; }

    /**
     * Normalize header name to be used in headers() and header()
     * @param string $name
     * @return string
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = str_replace('_', ' ', $name);
        $name = ucwords(strtolower($name));
        return str_replace(' ', '-', $name);
    }

}
