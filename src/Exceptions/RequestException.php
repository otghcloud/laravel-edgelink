<?php

namespace OTGH\LaravelEdgelink\Exceptions;

use Illuminate\Http\Client\RequestException as HttpRequestException;

class RequestException extends EdgelinkException
{
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

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
