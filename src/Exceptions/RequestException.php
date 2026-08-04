<?php

namespace OTGH\LaravelEdgelink\Exceptions;

use Illuminate\Http\Client\RequestException as HttpRequestException;

class RequestException extends EdgelinkException
{
    /**
     * Build a request exception with transport context.
     *
     * @param  string  $message  Human-readable failure message.
     * @param  string  $method  HTTP method used for the request.
     * @param  string  $path  Normalized request path.
     * @param  ?int  $statusCode  HTTP status code when available.
     * @param  ?string  $responseBody  Raw response body when available.
     * @param  ?\Throwable  $previous  Previous chained exception.
     */
    public function __construct(
        string $message,
        protected string $method,
        protected string $path,
        protected ?int $statusCode = null,
        protected ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Convert an Illuminate HTTP exception into a package RequestException.
     *
     * @param  string  $method  HTTP method used.
     * @param  string  $path  Request path used.
     * @param  HttpRequestException  $exception  Source HTTP client exception.
     * @return self Wrapped package-level exception.
     */
    public static function fromHttpClient(
        string $method,
        string $path,
        HttpRequestException $exception,
    ): self {
        $statusCode = $exception->response?->status();
        $body = $exception->response?->body();

        $message = sprintf(
            'Edgelink request failed: [%s %s]%s',
            $method,
            $path,
            $statusCode !== null ? ' HTTP '.$statusCode : '',
        );

        return new self($message, $method, $path, $statusCode, $body, $exception);
    }

    /**
     * Build an exception for unsupported request verbs.
     *
     * @param  string  $method  Unsupported HTTP method.
     */
    public static function unsupportedMethod(string $method): self
    {
        return new self(
            'Unsupported HTTP method: '.$method,
            strtoupper($method),
            '',
            null,
            null,
        );
    }

    /**
     * Get the request HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the normalized request path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the HTTP status code if available.
     */
    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the raw response body if available.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
