<?php

namespace App\Core;

/**
 * Response class to send responses to the client
 * Holds status/message/success/data/headers
 * Sends a JSON payload with proper headers and length
 */
class Response
{

    /** Default HTTP status and headers */
    private const DEFAULT_STATUS = 200;
    private const DEFAULT_HEADERS = ['Content-Type' => 'application/json; charset=utf-8'];

    /** Response state */
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

    /**
     * Send response to the client and exit
     * Parameters status, message, success can be null to keep the current value
     * @param int|null $status
     * @param string|null $message
     * @param bool|null $success
     * @return void
     */
    public function send(?int $status = null, ?string $message = null, ?bool $success = null): void
    {

        if ($status !== null) $this->httpStatus = $status;
        if ($message !== null) $this->message = $message;
        if ($success !== null) $this->success = $success;

        /** set status and headers */
        http_response_code($this->httpStatus);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }

        /** build the payload */
        $payload = [
            'status' => $this->httpStatus,
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];

        try {

            /** encode payload */
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

        } catch (\JsonException $e) {

            /** send error response with 500 status and json encoding error */
            $this->httpStatus = 500;
            http_response_code(500);
            $json = json_encode(
                ['status' => 500, 'success' => false, 'message' => 'JSON encoding error', 'data' => []],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        /** attach content length header and send response echo it and exit */
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    /**
     * Add Header to the response
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Get the HTTP status code of the response
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Set the HTTP status code of the response
     * @param int $httpStatus
     * @return $this
     */
    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    /**
     * Get the message of the response
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set the message of the response
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Check if the response is a success
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Set the success of the response
     * @param bool $success
     * @return void
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * Get the data of the response
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set the data of the response
     * @param array $data
     * @return $this
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get the headers of the response
     * @return array|string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the headers of the response
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

}