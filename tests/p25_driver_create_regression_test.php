<?php
$handler = file_get_contents(__DIR__ . '/../app/Support/driver_create_handler.php');
$form = file_get_contents(__DIR__ . '/../app/View/partials/company_driver_create_form.php');

$fail = static function (string $message): void {
    fwrite(STDERR, "P25 FAIL: {$message}\n");
    exit(1);
};

if (strpos($handler, 'applyLocalMigrations($localPdo);') !== false) {
    $fail('driver create handler must not run the full local migration chain');
}
if (strpos($form, 'Телефон <span class="req">*</span>') !== false) {
    $fail('phone must not be marked as required');
}
foreach ([
    'Укажите ФИО полностью: фамилия, имя и отчество',
    'Телефон должен содержать 10 или 11 цифр',
    'Серия и номер паспорта должны содержать 10 цифр',
    'СНИЛС должен содержать 11 цифр',
] as $expected) {
    if (strpos($handler, $expected) === false) {
        $fail('missing clear validation message: ' . $expected);
    }
}

echo "P25 DRIVER CREATE REGRESSION: PASS\n";
