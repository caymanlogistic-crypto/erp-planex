<?php
/** P48 production READ-ONLY finance/cash inventory. PHP 5.6 compatible. */
function envFile($path) {
    $out = array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $out;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) continue;
        $parts = explode('=', $line, 2);
        $out[trim($parts[0])] = trim($parts[1], " \t\n\r\0\x0B\"'");
    }
    return $out;
}
function qi($s) { return '`'.str_replace('`','``',$s).'`'; }
function j($v) { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION); }
function section($name, $data) { echo "\n=== ".$name." ===\n".j($data)."\n"; }

$e = envFile('/home/s/spugovxsim/planexp/public_html/erpv2/.env');
$host = isset($e['DB_HOST']) ? $e['DB_HOST'] : '127.0.0.1';
$port = isset($e['DB_PORT']) ? $e['DB_PORT'] : '3306';
$db = isset($e['DB_DATABASE']) ? $e['DB_DATABASE'] : 'erp_planex';
$user = isset($e['DB_USERNAME']) ? $e['DB_USERNAME'] : 'root';
$pass = isset($e['DB_PASSWORD']) ? $e['DB_PASSWORD'] : '';
$pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$db.';charset=utf8mb4', $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    section('DATABASE', array('database' => $pdo->query('SELECT DATABASE()')->fetchColumn(), 'mode' => 'READ ONLY'));
    $allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $interesting = array();
    foreach ($allTables as $t) if (preg_match('/finance|employee|cash|invoice|obligation|allocation|settlement|payment/i', $t)) $interesting[] = $t;
    sort($interesting);
    section('INTERESTING_TABLES', $interesting);

    $schemas = array(); $counts = array();
    foreach ($interesting as $t) {
        $schemas[$t] = $pdo->query('SHOW COLUMNS FROM '.qi($t))->fetchAll();
        $counts[$t] = (int)$pdo->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn();
    }
    section('SCHEMAS', $schemas);
    section('ROW_COUNTS', $counts);

    if (in_array('finance_operations', $allTables, true)) {
        $opSchema = isset($schemas['finance_operations']) ? $schemas['finance_operations'] : $pdo->query('SHOW COLUMNS FROM finance_operations')->fetchAll();
        $cols = array_column($opSchema, 'Field');
        if (in_array('id', $cols, true)) {
            $st = $pdo->prepare('SELECT * FROM finance_operations WHERE id = ?');
            $st->execute(array(159));
            section('FINANCE_OPERATION_159', $st->fetchAll());
        }
        $order = in_array('id', $cols, true) ? ' ORDER BY id ASC' : '';
        section('FINANCE_OPERATIONS_ALL', $pdo->query('SELECT * FROM finance_operations'.$order.' LIMIT 10000')->fetchAll());
        $dist = array();
        foreach ($cols as $c) {
            if (!preg_match('/status|type|source|direction|kind|method|account/i', $c)) continue;
            try {
                $dist[$c] = $pdo->query('SELECT '.qi($c).' AS v, COUNT(*) AS n FROM finance_operations GROUP BY '.qi($c).' ORDER BY n DESC')->fetchAll();
            } catch (Exception $ignored) {}
        }
        section('FINANCE_OPERATION_DISTRIBUTIONS', $dist);
    }

    $rowDump = array();
    foreach ($interesting as $t) {
        if ($t === 'finance_operations') continue;
        $n = isset($counts[$t]) ? $counts[$t] : 0;
        if ($n <= 2000) {
            $cols = array_column(isset($schemas[$t]) ? $schemas[$t] : array(), 'Field');
            $order = in_array('id', $cols, true) ? ' ORDER BY id ASC' : '';
            try { $rowDump[$t] = $pdo->query('SELECT * FROM '.qi($t).$order.' LIMIT 2000')->fetchAll(); }
            catch (Exception $ex) { $rowDump[$t] = array('__error' => $ex->getMessage()); }
        } else $rowDump[$t] = array('__skipped_rows' => $n);
    }
    section('RELEVANT_ROWS', $rowDump);

    $cashHits = array();
    foreach ($interesting as $t) {
        $cols = isset($schemas[$t]) ? $schemas[$t] : array();
        foreach ($cols as $meta) {
            $c = isset($meta['Field']) ? $meta['Field'] : '';
            $type = strtolower(isset($meta['Type']) ? $meta['Type'] : '');
            if (!preg_match('/char|text|enum|set/', $type)) continue;
            try {
                $sql = 'SELECT * FROM '.qi($t).' WHERE LOWER(COALESCE('.qi($c).",'')) LIKE '%cash%' OR LOWER(COALESCE(".qi($c).",'')) LIKE '%касс%' LIMIT 200";
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) $cashHits[$t.'.'.$c] = $rows;
            } catch (Exception $ignored) {}
        }
    }
    section('EXPLICIT_CASH_HITS', $cashHits);
    $pdo->rollBack();
    echo "\nP48_FINANCE_CASH_INVENTORY_OK\n";
} catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'P48_ERROR: '.$ex->getMessage()."\n");
    exit(2);
}
