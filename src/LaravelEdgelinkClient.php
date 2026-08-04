<?php

namespace OTGH\LaravelEdgelink;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException as HttpRequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Endpoints\AuthEndpoint;
use OTGH\LaravelEdgelink\Endpoints\DataLoggerEndpoint;
use OTGH\LaravelEdgelink\Endpoints\FirmwareEndpoint;
use OTGH\LaravelEdgelink\Endpoints\IoEndpoint;
use OTGH\LaravelEdgelink\Endpoints\LogsEndpoint;
use OTGH\LaravelEdgelink\Endpoints\NetworkEndpoint;
use OTGH\LaravelEdgelink\Endpoints\SystemEndpoint;
use OTGH\LaravelEdgelink\Endpoints\TagsEndpoint;
use OTGH\LaravelEdgelink\Exceptions\AuthenticationException;
use OTGH\LaravelEdgelink\Exceptions\ConfigurationException;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\Exceptions\SessionException;

class LaravelEdgelinkClient
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $debugHistory = [];

    protected ?string $sessionId = null;

    protected AuthEndpoint $authEndpoint;

    protected TagsEndpoint $tagsEndpoint;

    protected SystemEndpoint $systemEndpoint;

    protected IoEndpoint $ioEndpoint;

    protected DataLoggerEndpoint $dataLoggerEndpoint;

    protected FirmwareEndpoint $firmwareEndpoint;

    protected LogsEndpoint $logsEndpoint;

    protected NetworkEndpoint $networkEndpoint;

    public function __construct(
        protected string $baseUrl,
        protected string $password,
        protected ?string $referer = null,
        protected bool $verifyTls = false,
        protected int $timeoutSeconds = 10,
        protected string $responseMode = 'data',
        protected bool $rawResponse = false,
        protected bool $debugEnabled = false,
        protected bool $debugRequestHeaders = true,
        protected bool $debugRequestBody = true,
        protected bool $debugResponseHeaders = true,
        protected bool $debugResponseBody = true,
    ) {
        $this->authEndpoint = new AuthEndpoint($this);
        $this->tagsEndpoint = new TagsEndpoint($this);
        $this->systemEndpoint = new SystemEndpoint($this);
        $this->ioEndpoint = new IoEndpoint($this);
        $this->dataLoggerEndpoint = new DataLoggerEndpoint($this);
        $this->firmwareEndpoint = new FirmwareEndpoint($this);
        $this->logsEndpoint = new LogsEndpoint($this);
        $this->networkEndpoint = new NetworkEndpoint($this);
    }

    public static function fromConfig(?array $config = null): static
    {
        $config ??= config('edgelink', config('services.edgelink', []));

        return new static(
            baseUrl: rtrim((string) ($config['base_url'] ?? ''), '/'),
            password: (string) ($config['password'] ?? ''),
            referer: $config['referer'] ?? null,
            verifyTls: self::toBool($config['verify_tls'] ?? false),
            timeoutSeconds: (int) ($config['timeout_seconds'] ?? 10),
            responseMode: (string) ($config['response_mode'] ?? 'data'),
            rawResponse: self::toBool($config['raw_response'] ?? false),
            debugEnabled: self::toBool($config['debug_enabled'] ?? false),
            debugRequestHeaders: self::toBool($config['debug_request_headers'] ?? true, true),
            debugRequestBody: self::toBool($config['debug_request_body'] ?? true, true),
            debugResponseHeaders: self::toBool($config['debug_response_headers'] ?? true, true),
            debugResponseBody: self::toBool($config['debug_response_body'] ?? true, true),
        );
    }

    public static function make(
        string $baseUrl,
        string $password,
        ?string $referer = null,
        bool $verifyTls = false,
        int $timeoutSeconds = 10,
        string $responseMode = 'data',
        bool $rawResponse = false,
        bool $debugEnabled = false,
        bool $debugRequestHeaders = true,
        bool $debugRequestBody = true,
        bool $debugResponseHeaders = true,
        bool $debugResponseBody = true,
    ): static {
        return new static(
            baseUrl: rtrim($baseUrl, '/'),
            password: $password,
            referer: $referer,
            verifyTls: $verifyTls,
            timeoutSeconds: $timeoutSeconds,
            responseMode: $responseMode,
            rawResponse: $rawResponse,
            debugEnabled: $debugEnabled,
            debugRequestHeaders: $debugRequestHeaders,
            debugRequestBody: $debugRequestBody,
            debugResponseHeaders: $debugResponseHeaders,
            debugResponseBody: $debugResponseBody,
        );
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'base_url' => $this->baseUrl,
            'password' => $this->password,
            'referer' => $this->referer,
            'verify_tls' => $this->verifyTls,
            'timeout_seconds' => $this->timeoutSeconds,
            'response_mode' => $this->responseMode,
            'raw_response' => $this->rawResponse,
            'debug_enabled' => $this->debugEnabled,
            'debug_request_headers' => $this->debugRequestHeaders,
            'debug_request_body' => $this->debugRequestBody,
            'debug_response_headers' => $this->debugResponseHeaders,
            'debug_response_body' => $this->debugResponseBody,
            default => $default,
        };
    }

    public function beginDebugTrace(): int
    {
        return count($this->debugHistory);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectDebugTrace(int $fromIndex = 0): array
    {
        return array_slice($this->debugHistory, max(0, $fromIndex));
    }

    public function auth(): AuthEndpoint
    {
        return $this->authEndpoint;
    }

    public function tags(): TagsEndpoint
    {
        return $this->tagsEndpoint;
    }

    public function system(): SystemEndpoint
    {
        return $this->systemEndpoint;
    }

    public function io(): IoEndpoint
    {
        return $this->ioEndpoint;
    }

    public function dataLogger(): DataLoggerEndpoint
    {
        return $this->dataLoggerEndpoint;
    }

    public function firmware(): FirmwareEndpoint
    {
        return $this->firmwareEndpoint;
    }

    public function logs(): LogsEndpoint
    {
        return $this->logsEndpoint;
    }

    public function network(): NetworkEndpoint
    {
        return $this->networkEndpoint;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function login(): string
    {
        return $this->auth()->login($this->password);
    }

    public function performLogin(string $password): string
    {
        $this->assertConfigured();

        $response = $this->baseRequest()->put('/sys/log_in', ['password' => $password]);

        try {
            $response->throw();
        } catch (HttpRequestException $e) {
            throw RequestException::fromHttpClient('PUT', '/sys/log_in', $e);
        }

        $sessionId = $this->extractSessionId($response);

        if (! $sessionId) {
            throw new AuthenticationException('Unable to get SID from login response.');
        }

        $this->sessionId = $sessionId;

        return $sessionId;
    }

    public function logout(): array|string|null
    {
        return $this->auth()->logout();
    }

    public function performLogout(): array|string|null
    {
        $response = $this->authenticatedRequest()->put('/sys/log_out');

        try {
            $response->throw();
        } catch (HttpRequestException $e) {
            throw RequestException::fromHttpClient('PUT', '/sys/log_out', $e);
        }

        $this->sessionId = null;

        return $response->json() ?? $response->body();
    }

    public function getTags(): array
    {
        return $this->tags()->all();
    }

    public function getTag(string $tagName): ?array
    {
        return $this->tags()->one($tagName);
    }

    public function updateTag(string $path, array $payload): array|string|null
    {
        return $this->tags()->update($path, $payload);
    }

    public function updateDoValue(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->tags()->updateDoValue($slot, $channel, $value);
    }

    public function requestJson(string $method, string $path, array $data = [], bool $requiresAuth = true): array
    {
        $response = $this->request($method, $path, $data, $requiresAuth);

        return $response->json() ?? [];
    }

    public function requestBody(string $method, string $path, array $data = [], bool $requiresAuth = true): array|string|null
    {
        $response = $this->request($method, $path, $data, $requiresAuth);

        return $response->json() ?? $response->body();
    }

    public function request(string $method, string $path, array $data = [], bool $requiresAuth = true): Response
    {
        $request = $requiresAuth ? $this->authenticatedRequest() : $this->baseRequest();
        $normalizedPath = $this->normalizePath($path);
        $requestHeaders = $this->buildDebugRequestHeaders($requiresAuth);

        $method = strtoupper($method);

        $response = match ($method) {
            'GET' => $request->get($normalizedPath, $data),
            'PUT' => $request->put($normalizedPath, $data),
            'PATCH' => $request->patch($normalizedPath, $data),
            'POST' => $request->post($normalizedPath, $data),
            'DELETE' => $request->delete($normalizedPath, $data),
            default => throw RequestException::unsupportedMethod($method),
        };

        $this->recordDebugExchange(
            method: $method,
            path: $normalizedPath,
            requestHeaders: $requestHeaders,
            requestBody: $data,
            response: $response,
        );

        try {
            $response->throw();
        } catch (HttpRequestException $e) {
            throw RequestException::fromHttpClient($method, $normalizedPath, $e);
        }

        return $response;
    }

    public function isAuthenticated(): bool
    {
        return $this->sessionId !== null && $this->sessionId !== '';
    }

    public function ensureAuthenticated(): void
    {
        if ($this->isAuthenticated()) {
            return;
        }

        try {
            $this->login();
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AuthenticationException('Unable to authenticate before request.', 0, $e);
        }

        if (! $this->isAuthenticated()) {
            throw new SessionException('No valid session is available for authenticated request.');
        }
    }

    protected function authenticatedRequest(): PendingRequest
    {
        $this->ensureAuthenticated();

        return $this->baseRequest()->withHeaders([
            'Cookie' => 'SID='.$this->sessionId.'; ADAMSID='.$this->sessionId,
        ])->asJson();
    }

    protected function baseRequest(): PendingRequest
    {
        $request = Http::acceptJson()
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->withHeaders([
                'Referer' => $this->referer ?? $this->baseUrl.'/',
                'Content-Type' => 'application/json',
            ]);

        if (! $this->verifyTls) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    protected function assertConfigured(): void
    {
        if ($this->baseUrl === '') {
            throw new ConfigurationException('ADAM RTU base URL is not configured.');
        }

        if ($this->password === '') {
            throw new ConfigurationException('ADAM RTU password is not configured.');
        }
    }

    protected function normalizePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    protected function extractSessionId(Response $response): ?string
    {
        $headers = $response->headers();
        $setCookie = $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? [];
        $setCookieLines = is_array($setCookie) ? $setCookie : [$setCookie];

        foreach ($setCookieLines as $cookieLine) {
            if (preg_match('/SID=([^;]+)/', (string) $cookieLine, $matches) === 1) {
                return $matches[1];
            }

            if (preg_match('/ADAMSID=([^;]+)/', (string) $cookieLine, $matches) === 1) {
                return $matches[1];
            }
        }

        $json = $response->json();

        if (isset($json['session_id']) && is_string($json['session_id'])) {
            return $json['session_id'];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function buildDebugRequestHeaders(bool $requiresAuth): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Referer' => $this->referer ?? $this->baseUrl.'/',
        ];

        if ($requiresAuth && $this->sessionId !== null && $this->sessionId !== '') {
            $headers['Cookie'] = 'SID='.$this->sessionId.'; ADAMSID='.$this->sessionId;
        }

        return $headers;
    }

    protected function recordDebugExchange(
        string $method,
        string $path,
        array $requestHeaders,
        array $requestBody,
        Response $response,
    ): void {
        $this->debugHistory[] = [
            'request' => [
                'method' => $method,
                'path' => $path,
                'headers' => $requestHeaders,
                'body' => $requestBody,
            ],
            'response' => [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json() ?? $response->body(),
            ],
        ];

        if (count($this->debugHistory) > 200) {
            $this->debugHistory = array_slice($this->debugHistory, -200);
        }
    }

    protected static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return $default;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }
}
