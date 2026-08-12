<?php

$root = dirname(__DIR__);
$js = file_get_contents($root . '/public/assets/js/linear-trip-route-points.js');
$migration = file_get_contents($root . '/database/migrations-local/060_relax_linear_route_payment_legacy_due_fields.sql');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($migration, 'linear_route_payments'), 'migration must target linear_route_payments');
$assert(str_contains($migration, 'payment_due_type` VARCHAR(100) NULL DEFAULT NULL'), 'payment_due_type must become nullable');
$assert(str_contains($migration, 'payment_due_days_kind` VARCHAR(20) NULL DEFAULT NULL'), 'payment_due_days_kind must become nullable');
$assert(str_contains($js, 'function syncPaymentRow(row, index)'), 'payment rows must have a presentation synchronizer');
$assert(str_contains($js, "label.style.visibility = index === 0 ? '' : 'hidden'"), 'duplicate first-line payment headers must be hidden');
$assert(str_contains($js, "var isCash = methodSelect.value === 'cash'"), 'cash payment method must be detected');
$assert(str_contains($js, "vatSelect.value = ''"), 'cash must clear the VAT value before submit');
$assert(str_contains($js, 'vatSelect.disabled = true'), 'cash VAT control must not submit a stale VAT rate');
$assert(str_contains($js, "emptyOption.textContent = '—'"), 'cash VAT UI must display a dash');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OK linear trip payment compatibility\n";
