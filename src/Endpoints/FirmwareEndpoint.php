<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class FirmwareEndpoint
{
    use FormatsEndpointResponses;

    /**
     * Create a new firmware endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Validate firmware file metadata before update.
     *
     * @param  array<string, mixed>  $payload  Firmware verification payload.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function verifyFile(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'POST',
            path: '/sys/file_verify',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeFirmwareResponse('verify_file', '/sys/file_verify', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/file_verify', 'action' => 'verify_file'],
        );
    }

    /**
     * Upload firmware payload metadata.
     *
     * @param  array<string, mixed>  $payload  Upload payload.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function upload(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'POST',
            path: '/sys/upload',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeFirmwareResponse('upload', '/sys/upload', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/upload', 'action' => 'upload'],
        );
    }

    /**
     * Start firmware update workflow.
     *
     * @param  array<string, mixed>  $payload  Update request payload.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function update(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'POST',
            path: '/sys/update',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeFirmwareResponse('update', '/sys/update', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/update', 'action' => 'update'],
        );
    }

    /**
     * Trigger fallback to default firmware image.
     *
     * @param  array<string, mixed>  $payload  Recovery payload (typically empty).
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function recoverDefaultImage(array $payload = [], ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'POST',
            path: '/sys/image/recovery',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeFirmwareResponse('recover_default_image', '/sys/image/recovery', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/image/recovery', 'action' => 'recover_default_image'],
        );
    }

    /**
     * Normalize firmware mutation responses into a stable contract.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeFirmwareResponse(
        string $action,
        string $path,
        array $payload,
        mixed $rawPayload,
    ): array {
        return [
            'action' => $action,
            'path' => $path,
            'payload' => $payload,
            'result' => $rawPayload,
        ];
    }
}
