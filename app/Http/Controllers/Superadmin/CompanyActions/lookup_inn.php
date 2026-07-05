<?php
use App\Service\CompanyInnLookupService;

requireRole('superadmin');

try {
    $payload = requestJsonBody();
    $inn = (string)($payload['inn'] ?? $_POST['inn'] ?? '');

    $lookupService = new CompanyInnLookupService(
        (string)env('DADATA_API_KEY', ''),
        (string)env('DADATA_API_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party'),
        (int)env('DADATA_API_TIMEOUT', 5),
        (string)env('DADATA_CAINFO', '')
    );

    $result = $lookupService->lookup($inn);
    jsonResponse($result, 200);
} catch (\Throwable $e) {
    jsonResponse([
        'ok' => false,
        'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
    ], 200);
}
