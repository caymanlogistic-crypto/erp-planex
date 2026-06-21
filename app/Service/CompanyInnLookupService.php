<?php

namespace App\Service;

final class CompanyInnLookupService
{
    private string $apiKey;
    private string $apiUrl;
    private int $timeoutSeconds;
    private ?string $caInfoPath;

    public function __construct(string $apiKey, string $apiUrl, int $timeoutSeconds = 5, ?string $caInfoPath = null)
    {
        $this->apiKey = trim($apiKey);
        $this->apiUrl = trim($apiUrl);
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->caInfoPath = $this->resolveCaInfoPath($caInfoPath);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiUrl !== '';
    }

    public function lookup(string $inn): array
    {
        $cleanInn = preg_replace('/[^0-9]/', '', $inn) ?? '';

        if ($cleanInn === '' || !in_array(strlen($cleanInn), [10, 12], true)) {
            return [
                'ok' => false,
                'message' => 'Укажите корректный ИНН.',
            ];
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('DaData credentials are not configured.');
        }

        $payload = json_encode(['query' => $cleanInn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new \RuntimeException('Failed to encode lookup payload.');
        }

        $response = $this->sendRequest($payload);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected DaData response format.');
        }

        $firstSuggestion = $decoded['suggestions'][0]['data'] ?? null;
        if (!is_array($firstSuggestion)) {
            return [
                'ok' => false,
                'message' => 'Компания по указанному ИНН не найдена.',
            ];
        }

        $partyType = strtoupper((string)($firstSuggestion['type'] ?? ''));
        $statusCode = strtoupper((string)($firstSuggestion['state']['status'] ?? ''));
        $legalAddress = (string)($firstSuggestion['address']['unrestricted_value'] ?? $firstSuggestion['address']['value'] ?? '');

        return [
            'ok' => true,
            'message' => 'Данные найдены',
            'data' => [
                'inn' => (string)($firstSuggestion['inn'] ?? $cleanInn),
                'name' => (string)($firstSuggestion['name']['short_with_opf'] ?? $firstSuggestion['name']['full_with_opf'] ?? ''),
                'contractor_type' => $this->mapContractorType($partyType),
                'kpp' => (string)($firstSuggestion['kpp'] ?? ''),
                'ogrn' => (string)($firstSuggestion['ogrn'] ?? ''),
                'legal_address' => $legalAddress,
                'physical_address' => '',
                'director_full_name' => (string)($firstSuggestion['management']['name'] ?? ''),
                'director_position'  => (string)($firstSuggestion['management']['post'] ?? ''),
            ],
            'meta' => [
                'source' => 'dadata',
                'status_code' => $statusCode,
                'status_label' => $this->mapStatusLabel($statusCode),
                'type' => $partyType,
            ],
        ];
    }

    private function sendRequest(string $payload): string
    {
        $curl = curl_init($this->apiUrl);

        if ($curl === false) {
            throw new \RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Token ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
        ]);

        if ($this->caInfoPath !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->caInfoPath);
        }

        $response = curl_exec($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($response === false || $error !== '') {
            throw new \RuntimeException('DaData request failed.');
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException('DaData returned HTTP ' . $statusCode . '.');
        }

        return (string)$response;
    }

    private function mapContractorType(string $partyType): string
    {
        return match ($partyType) {
            'LEGAL' => 'legal_entity',
            'INDIVIDUAL' => 'individual',
            default => '',
        };
    }

    private function mapStatusLabel(string $statusCode): string
    {
        return match ($statusCode) {
            'ACTIVE' => 'Действует',
            'LIQUIDATING' => 'Ликвидируется',
            'LIQUIDATED' => 'Ликвидирована',
            'BANKRUPT' => 'Банкротство',
            'REORGANIZING' => 'Реорганизация',
            default => 'Статус не указан',
        };
    }

    private function resolveCaInfoPath(?string $caInfoPath): ?string
    {
        $candidate = trim((string)$caInfoPath);
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }

        $defaults = [
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
        ];

        foreach ($defaults as $defaultPath) {
            if (is_file($defaultPath)) {
                return $defaultPath;
            }
        }

        return null;
    }
}
