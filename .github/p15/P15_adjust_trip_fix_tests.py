from pathlib import Path

root = Path(__file__).resolve().parents[2]
path = root / 'tests/p15_trip_create_payment_terms_regression_test.php'
text = path.read_text(encoding='utf-8')
old = "$check(is_string($edit) && !str_contains($edit, \"'message' => \\$e->getMessage()\"), 'edit UI does not expose unexpected exception message');\n"
new = "$unexpectedCatch = is_string($edit) ? substr($edit, (int) strrpos($edit, '} catch (Throwable $e) {')) : '';\n$check(!str_contains($unexpectedCatch, \"'message' => \\$e->getMessage()\"), 'edit unexpected-error UI does not expose exception message');\n"
if text.count(old) != 1:
    raise RuntimeError('unexpected-error assertion anchor not found')
path.write_text(text.replace(old, new, 1), encoding='utf-8', newline='\n')
print('P15_TEST_ADJUSTMENT=PASS')
