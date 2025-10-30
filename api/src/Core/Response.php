<?php

namespace App\Core;
class Response
{

    private const DEFAULT_STATUS = 200;
    private const DEFAULT_HEADERS = ['Content-Type' => 'application/json; charset=utf-8'];

    private int $httpStatus = self::DEFAULT_STATUS;
    private string $message = '';
    private bool $success = true;
    private array $data = [];
    private array $headers = self::DEFAULT_HEADERS;

    public function __construct(?int    $httpStatus = null,
                                ?string $message = null,
                                ?bool   $success = null,
                                ?array  $data = null,
                                ?array  $headers = null)
    {
        if ($httpStatus !== null) $this->httpStatus = $httpStatus;
        if ($message !== null) $this->message = $message;
        if ($data !== null) $this->data = $data;
        if ($headers !== null) $this->headers = $headers;
        if ($success !== null) $this->success = $success;
    }

    public function send(?int $status = null, ?string $message = null, ?bool $success = null): void
    {

        if ($status !== null) $this->httpStatus = $status;
        if ($message !== null) $this->message = $message;
        if ($success !== null) $this->success = $success;

        http_response_code($this->httpStatus);
        foreach ($this->headers as $k => $v) {
            header("$k: $v", true);
        }

        $payload = [
            'status' => $this->httpStatus,
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];

        try {

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

        } catch (\JsonException $e) {

            $this->httpStatus = 500;
            http_response_code(500);
            $json = json_encode(
                ['status' => 500, 'success' => false, 'message' => 'JSON encoding error', 'data' => []],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        header('Content-Length: ' . strlen($json), true);
        echo $json;
        exit;
    }

    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

}