from __future__ import annotations

from pathlib import Path


def replace_once(path: Path, before: str, after: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    if text.count(before) != 1:
        raise RuntimeError(f"{label}: expected one anchor in {path}, got {text.count(before)}")
    path.write_text(text.replace(before, after), encoding="utf-8", newline="\n")


helper_path = Path("app/Support/legal_entity_document_upload.php")
helper_anchor = "if (!function_exists('findLegalEntityDocumentTypeId')) {"
helper_code = r'''if (!function_exists('validateLegalEntityCreateDocumentFiles')) {
    function validateLegalEntityCreateDocumentFiles(array $files): ?string
    {
        $groups = [
            'predef_doc' => 'Документ',
            'custom_doc_file' => 'Документ',
        ];
        $allowedExtensions = legalEntityDocumentAllowedExtensions();
        $maxFileSize = legalEntityDocumentMaxFileSize();

        foreach ($groups as $groupKey => $fallbackLabel) {
            $names = $files[$groupKey]['name'] ?? [];
            if (!is_array($names)) {
                continue;
            }

            foreach ($names as $index => $originalName) {
                $originalName = trim((string) $originalName);
                $errorCode = (int) ($files[$groupKey]['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode === UPLOAD_ERR_NO_FILE && $originalName === '') {
                    continue;
                }
                if ($errorCode !== UPLOAD_ERR_OK) {
                    return $fallbackLabel . ' «' . ($originalName !== '' ? $originalName : '#' . ((int) $index + 1)) . '»: ошибка загрузки.';
                }
                if ($originalName === '') {
                    return $fallbackLabel . ': имя файла не указано.';
                }

                $fileSize = (int) ($files[$groupKey]['size'][$index] ?? 0);
                if ($fileSize <= 0) {
                    return $fallbackLabel . ' «' . $originalName . '»: пустой файл не допускается.';
                }
                if ($fileSize > $maxFileSize) {
                    return $fallbackLabel . ' «' . $originalName . '»: размер превышает 20 МБ.';
                }

                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    return $fallbackLabel . ' «' . $originalName . '»: недопустимый формат.';
                }
                if (strpos($originalName, '../') !== false || strpos($originalName, '..\\') !== false || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
                    return $fallbackLabel . ' «' . $originalName . '»: недопустимое имя.';
                }

                $tmpName = (string) ($files[$groupKey]['tmp_name'][$index] ?? '');
                if ($tmpName === '' || !is_file($tmpName)) {
                    return $fallbackLabel . ' «' . $originalName . '»: временный файл недоступен.';
                }
            }
        }

        return null;
    }
}

'''
replace_once(helper_path, helper_anchor, helper_code + helper_anchor, "insert legal-entity file prevalidation")

client_path = Path("app/Http/Controllers/Company/ClientActions/create_submit.php")
client_anchor = """    $_FILES['custom_doc_file']['name'] = [];
"""
client_code = """    $documentFileError = validateLegalEntityCreateDocumentFiles($legalEntityFiles);
    if ($documentFileError !== null) {
        $formError = $documentFileError;
        ob_start();
        require base_path('app/View/pages/company_clients_create.php');
        $content = ob_get_clean();
        require base_path('app/View/layouts/main.php');
        return;
    }

    $_FILES['custom_doc_file']['name'] = [];
"""
replace_once(client_path, client_anchor, client_code, "client pre-persistence file validation")

contractor_path = Path("app/Http/Controllers/Company/ContractorActions/create_submit.php")
contractor_anchor = """    $newContractorId = $service->createContractor($localPdo, $_POST, (int)$_SESSION['user_id'], $_SESSION['role_code']);
"""
contractor_code = """    $documentFileError = validateLegalEntityCreateDocumentFiles($legalEntityFiles);
    if ($documentFileError !== null) {
        $formError = $documentFileError;
        $renderContractorCreateResponse();
        return;
    }

    $newContractorId = $service->createContractor($localPdo, $_POST, (int)$_SESSION['user_id'], $_SESSION['role_code']);
"""
replace_once(contractor_path, contractor_anchor, contractor_code, "contractor pre-persistence file validation")

Path("tests/p17_zero_byte_document_rejection_test.php").write_text(r'''<?php

require_once __DIR__ . '/../app/Support/legal_entity_document_upload.php';

$failed = 0;
function p17assert(string $name, bool $condition): void
{
    global $failed;
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $name . PHP_EOL;
    if (!$condition) $failed++;
}

$tmp = tempnam(sys_get_temp_dir(), 'p17_doc_');
file_put_contents($tmp, 'test');

$zero = [
    'custom_doc_file' => [
        'name' => ['empty.pdf'],
        'type' => ['application/pdf'],
        'tmp_name' => [$tmp],
        'error' => [UPLOAD_ERR_OK],
        'size' => [0],
    ],
];
$zeroError = validateLegalEntityCreateDocumentFiles($zero);
p17assert('zero-byte document is rejected', is_string($zeroError) && str_contains($zeroError, 'пустой файл'));

$valid = [
    'custom_doc_file' => [
        'name' => ['valid.pdf'],
        'type' => ['application/pdf'],
        'tmp_name' => [$tmp],
        'error' => [UPLOAD_ERR_OK],
        'size' => [4],
    ],
];
p17assert('positive-size allowed document passes prevalidation', validateLegalEntityCreateDocumentFiles($valid) === null);

$missing = [
    'custom_doc_file' => [
        'name' => [''],
        'tmp_name' => [''],
        'error' => [UPLOAD_ERR_NO_FILE],
        'size' => [0],
    ],
];
p17assert('optional absent file remains allowed', validateLegalEntityCreateDocumentFiles($missing) === null);

$invalidExtension = $valid;
$invalidExtension['custom_doc_file']['name'][0] = 'malware.exe';
p17assert('disallowed extension is rejected before entity persistence', validateLegalEntityCreateDocumentFiles($invalidExtension) !== null);

$tooLarge = $valid;
$tooLarge['custom_doc_file']['size'][0] = legalEntityDocumentMaxFileSize() + 1;
p17assert('oversize document is rejected before entity persistence', validateLegalEntityCreateDocumentFiles($tooLarge) !== null);

@unlink($tmp);
echo 'FAILED=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
''', encoding="utf-8", newline="\n")

print("P17R_ZERO_BYTE_PATCH=APPLIED")
