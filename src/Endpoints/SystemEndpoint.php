<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use Carbon\Carbon;
use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\Exceptions\ResponseTransformationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class SystemEndpoint
{
    use FormatsEndpointResponses;

    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function version(bool $raw = false, string $responseMode = 'data'): array|string|null
    {
        try {
            $response = $this->client->requestBody('GET', '/sys/version');

            return $this->formatVersionResponse($response, $raw, $responseMode, '/sys/version');
        } catch (RequestException $e) {
            if (in_array($e->statusCode(), [400, 500], true)) {
                try {
                    $legacyResponse = $this->client->requestBody('GET', '/xml/version.xml');

                    return $this->formatVersionResponse($legacyResponse, $raw, $responseMode, '/xml/version.xml');
                } catch (RequestException $legacyException) {
                    throw $legacyException;
                }
            }

            throw $e;
        }
    }

    protected function extractLegacyFirmwareVersion(string $xml): ?string
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $firmwareInfo = simplexml_load_string($xml);

            if ($firmwareInfo === false) {
                return null;
            }

            $version = (string) ($firmwareInfo['version'] ?? '');

            return $version !== '' ? $version : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    protected function formatVersionResponse(
        array|string|null $response,
        bool $raw,
        string $responseMode,
        string $source,
    ): array|string|null {
        if ($raw) {
            return $response;
        }

        $normalized = $this->normalizeVersionPayload($response, $source);

        return $this->formatResponse($normalized, $responseMode, ['source' => $source]);
    }

    /**
     * @return array{version:string,released:?string,released_at:?string}
     */
    protected function normalizeVersionPayload(array|string|null $response, string $source): array
    {
        $descriptor = $this->extractNormalizedFirmwareDescriptor($response, $source);

        return $this->extractVersionDetails($descriptor);
    }

    /**
     * @return array{version:string,released:?string,released_at:?string}
     */
    protected function extractVersionDetails(string $descriptor): array
    {
        if (! preg_match('/image\s+version\s+([0-9]+(?:\.[0-9]+)+)/i', $descriptor, $versionMatch)) {
            throw new ResponseTransformationException('Unable to parse firmware semantic version from response.');
        }

        $released = null;
        $releasedAt = null;

        if (preg_match('/release\s+([A-Za-z]{3}\s+\d{1,2}\s+\d{4})/i', $descriptor, $releaseMatch)) {
            $released = preg_replace('/\s+/', ' ', trim($releaseMatch[1]));

            try {
                $releasedAt = Carbon::createFromFormat('M j Y', $released)->toDateString();
            } catch (\Throwable $e) {
                throw new ResponseTransformationException('Unable to parse firmware release date.', 0, $e);
            }
        }

        return [
            'version' => $versionMatch[1],
            'released' => $released,
            'released_at' => $releasedAt,
        ];
    }

    protected function extractNormalizedFirmwareDescriptor(array|string|null $response, string $source): string
    {
        if (is_array($response)) {
            foreach (['version', 'Local version', 'local_version', 'v'] as $key) {
                if (isset($response[$key]) && is_scalar($response[$key])) {
                    return (string) $response[$key];
                }
            }
        }

        if (is_string($response)) {
            if ($source === '/xml/version.xml') {
                $legacyVersion = $this->extractLegacyFirmwareVersion($response);
                if ($legacyVersion !== null) {
                    return $legacyVersion;
                }
            }

            if ($response !== '') {
                return $response;
            }
        }

        throw new ResponseTransformationException('Unable to normalize firmware version response.');
    }

    public function updateInfo(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/update_info');
    }

    public function restart(): array|string|null
    {
        $req = $this->client->requestBody('PATCH', '/sys/control/rst');
        if (is_array($req) && isset($req['token'])) {
            // Newer firmwares give us a restart token, so we need to send it back alongside our password to actually restart the device
            return $this->client->requestJson('PATCH', '/sys/control/rst?token='.urlencode($req['token']), ['rst' => '1', 'key' => $this->client->getConfig('password')]);
        }

        return $req;
    }

    public function control(array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/control', $payload);
    }

    public function calibration(array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/control/cali', $payload);
    }

    public function webSettings(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/websettings');
    }

    public function updateWebSettings(array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', '/sys/websettings', $payload);
    }

    public function deviceInfo(int $slot = 0): array|string|null
    {
        return $this->client->requestBody('GET', '/data/device_info/slot_'.$slot);
    }

    public function updateDeviceInfo(int $slot, array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/data/device_info/slot_'.$slot, $payload);
    }
}
