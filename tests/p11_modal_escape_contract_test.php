<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = [];
$pass = 0;
$fail = 0;

function p11Check(string $name, bool $condition, string $detail = ''): void
{
    global $checks, $pass, $fail;
    $checks[] = [$name, $condition, $detail];
    if ($condition) {
        $pass++;
    } else {
        $fail++;
    }
}

$modalShellPath = __DIR__ . '/../public/assets/js/modal-shell.js';
$layoutPath = __DIR__ . '/../app/View/layouts/main.php';
$modalShell = file_get_contents($modalShellPath);
$layout = file_get_contents($layoutPath);

p11Check('ModalShell is loaded by the main layout', str_contains($layout, '/assets/js/modal-shell.js'));
p11Check('Central keyboard controller exists', str_contains($modalShell, 'initErpModalKeyboardController'));
p11Check('Escape listener uses capture phase', preg_match("/addEventListener\\('keydown'.*?,\\s*true\\);/s", $modalShell) === 1);
p11Check('Only the topmost open modal is selected', str_contains($modalShell, 'topmostOpenModal'));
p11Check('Escape is prevented after a modal is resolved', str_contains($modalShell, 'event.preventDefault()'));
p11Check('Legacy bubble handlers cannot close parent modals', str_contains($modalShell, 'event.stopImmediatePropagation()'));
p11Check('Explicit hard lock has a dedicated attribute', str_contains($modalShell, "modal.dataset.escapeLocked === '1'"));
p11Check('Controller closes through the shared closeModal API', str_contains($modalShell, 'window.closeModal(modal.id)'));
p11Check('Generated view modal allows Escape', str_contains($modalShell, "el.dataset.closeOnEscape = '1';"));
p11Check('Generated confirmation modal allows Escape', str_contains($modalShell, "c.dataset.closeOnEscape = '1';"));
p11Check('Generated ModalShell no longer defaults Escape to zero', !str_contains($modalShell, "dataset.closeOnEscape = '0';"));

echo "=== P11 Modal Escape Contract ===\n";
foreach ($checks as [$name, $ok, $detail]) {
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $name;
    if ($detail !== '') echo ': ' . $detail;
    echo "\n";
}
echo "TOTAL={$pass}; FAILED={$fail}\n";
exit($fail === 0 ? 0 : 1);
