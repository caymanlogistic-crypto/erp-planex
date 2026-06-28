<?php
/** @var ContractorService $service */
use App\Service\CompanyInnLookupService;

requireRole(['company_owner', 'senior_logist', 'logist']);

$companyId = (int)(getSessionCompanyId() ?? 0);
if ($companyId <= 0) {
    jsonResponse([
        'ok' => false,
        'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
    ], 200);
    return;
}

try {
    $company = $service->loadCompany($companyId);

    if (!$company || ($company['status'] ?? '') !== 'active') {
        jsonResponse([
            'ok' => false,
            'message' => 'Не удалось получить данные. Заполните реквизиты вручную.',
        ], 200);
        return;
    }

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
