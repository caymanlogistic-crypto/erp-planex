<?php
/**
 * P48 read-only inventory for removing the user-facing cash model.
 * HARD RULE: SELECT/SHOW only. No writes, no DDL, no transactions that mutate data.
 */
function envFile(string $path): array {
    $out = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $out;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $out[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    return $out;
}
function qi(string $s): string { return '`'.str_replace('`','``',$s).'`'; }
function j($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION); }
function section(string $name, $data): void { echo "\n=== {$name} ===\n".j($data)."\n"; }

$e = envFile('/home/s/spugovxsim/planexp/public_html/erpv2/.env');
$pdo = new PDO(
    'mysql:host='.($e['DB_HOST'] ?? '127.0.0.1').';port='.($e['DB_PORT'] ?? '3306').';dbname='.($e['DB_DATABASE'] ?? 'erp_planex').';charset=utf8mb4',
    $e['DB_USERNAME'] ?? 'root',
    $e['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    section('DATABASE', ['database' => $dbName, 'mode' => 'READ ONLY']);

    $allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $interesting = [];
    foreach ($allTables as $t) {
        if (preg_match('/finance|employee|cash|invoice|obligation|allocation|settlement|payment/i', $t)) $interesting[] = $t;
    }
    sort($interesting);
    section('INTERESTING_TABLES', $interesting);

    $schemas = [];
    $counts = [];
    foreach ($interesting as $t) {
        $schemas[$t] = $pdo->query('SHOW COLUMNS FROM '.qi($t))->fetchAll();
        $counts[$t] = (int)$pdo->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn();
    }
    section('SCHEMAS', $schemas);
    section('ROW_COUNTS', $counts);

    // Exact source-of-truth operation #159 must never be mutated again.
    if (in_array('finance_operations', $allTables, true)) {
        $cols = array_column($schemas['finance_operations'] ?? $pdo->query('SHOW COLUMNS FROM finance_operations')->fetchAll(), 'Field');
        if (in_array('id', $cols, true)) {
            $st = $pdo->prepare('SELECT * FROM finance_operations WHERE id = ?');
            $st->execute([159]);
            section('FINANCE_OPERATION_159', $st->fetchAll());
        }

        // Full operation inventory: dataset is currently small enough for migration classification.
        $order = in_array('id', $cols, true) ? ' ORDER BY id ASC' : '';
        $ops = $pdo->query('SELECT * FROM finance_operations'.$order.' LIMIT 10000')->fetchAll();
        section('FINANCE_OPERATIONS_ALL', $ops);

        // Value distributions for columns that define source/type/status/direction/account semantics.
        $dist = [];
        foreach ($cols as $c) {
            if (preg_match('/status|type|source|direction|kind|method|account/i', $c)) {
                try {
                    $sql = 'SELECT '.qi($c).' AS v, COUNT(*) AS n FROM finance_operations GROUP BY '.qi($c).' ORDER BY n DESC';
                    $dist[$c] = $pdo->query($sql)->fetchAll();
                } catch (Throwable $ignored) {}
            }
        }
        section('FINANCE_OPERATION_DISTRIBUTIONS', $dist);
    }

    // Dump all rows for directly relevant small finance/employee tables, capped defensively.
    $rowDump = [];
    foreach ($interesting as $t) {
        if ($t === 'finance_operations') continue;
        $n = $counts[$t] ?? 0;
        if ($n <= 2000) {
            $cols = array_column($schemas[$t] ?? [], 'Field');
            $order = in_array('id', $cols, true) ? ' ORDER BY id ASC' : '';
            try { $rowDump[$t] = $pdo->query('SELECT * FROM '.qi($t).$order.' LIMIT 2000')->fetchAll(); }
            catch (Throwable $ex) { $rowDump[$t] = ['__error' => $ex->getMessage()]; }
        } else {
            $rowDump[$t] = ['__skipped_rows' => $n];
        }
    }
    section('RELEVANT_ROWS', $rowDump);

    // Search textual columns for explicit cash semantics without guessing schema.
    $cashHits = [];
    foreach ($interesting as $t) {
        $cols = $schemas[$t] ?? [];
        foreach ($cols as $meta) {
            $c = $meta['Field'] ?? '';
            $type = strtolower($meta['Type'] ?? '');
            if (!preg_match('/char|text|enum|set/', $type)) continue;
            try {
                $sql = 'SELECT * FROM '.qi($t).' WHERE LOWER(COALESCE('.qi($c).",'')) LIKE '%cash%' OR LOWER(COALESCE(".qi($c).",'')) LIKE '%касс%' LIMIT 200";
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) $cashHits[$t.'.'.$c] = $rows;
            } catch (Throwable $ignored) {}
        }
    }
    section('EXPLICIT_CASH_HITS', $cashHits);

    $pdo->rollBack();
    echo "\nP48_FINANCE_CASH_INVENTORY_OK\n";
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'P48_ERROR: '.$ex->getMessage()."\n");
    exit(2);
}
