<?php

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
