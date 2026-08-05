from __future__ import annotations

import base64
import gzip
import hashlib
import re
from pathlib import Path

ORIGINAL_SHA = "08031fe8097e9bde64f005418b2a47e64900f88eb4552f1ab35b00f6c09b1f72"
CORRECTED_SHA = "7f521e033c07ff325023f067cc98c0f03d9e00a8c4dd34e8dfd16300d598bbc9"

wrapper = Path(".github/p16/P16_apply_representative_fixtures.php").read_text("utf-8")
match = re.search(r"\$payload = '([^']+)'", wrapper)
if not match:
    raise SystemExit("P16 fixture payload is missing")

source = gzip.decompress(base64.b64decode(match.group(1))).decode("utf-8")
if hashlib.sha256(source.encode()).hexdigest() != ORIGINAL_SHA:
    raise SystemExit("P16 original fixture hash mismatch")

owner_before = """    $ownerStmt = $central->prepare(\"SELECT id FROM company_users WHERE company_id = ? AND role_code = 'company_owner' AND status = 'active' ORDER BY id LIMIT 1\");
    $ownerStmt->execute([TEST_TENANT_ID]);"""
owner_after = """    $ownerStmt = $central->prepare(\"SELECT id FROM company_users WHERE company_id = ? AND login = ? AND status = 'active' ORDER BY id LIMIT 1\");
    $ownerStmt->execute([TEST_TENANT_ID, 'p14_owner_260804221827']);"""
if source.count(owner_before) != 1:
    raise SystemExit("P16 owner compatibility anchor mismatch")
source = source.replace(owner_before, owner_after)

persist_start = source.index("function persist(PDO $pdo")
persist_end = source.index("\nfunction p16Guard", persist_start)
persist_replacement = r'''function tableColumnSet(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $columns = $pdo->query('SHOW COLUMNS FROM ' . quoteIdent($table))->fetchAll(PDO::FETCH_ASSOC);
    $cache[$table] = array_fill_keys(array_map(static fn(array $column): string => (string) $column['Field'], $columns), true);
    return $cache[$table];
}

function persist(PDO $pdo, string $table, array $where, array $data, callable $guard, array &$stats): int
{
    $columnSet = tableColumnSet($pdo, $table);
    foreach (array_keys($where) as $column) {
        if (!isset($columnSet[$column])) {
            throw new RuntimeException('P16 fixture identity column is absent in ' . $table . ': ' . $column);
        }
    }
    $omitted = [];
    foreach (array_keys($data) as $column) {
        if (!isset($columnSet[$column])) {
            $omitted[] = $column;
            unset($data[$column]);
        }
    }
    if ($omitted !== []) {
        $stats['_omitted_columns'][$table] = array_values(array_unique(array_merge($stats['_omitted_columns'][$table] ?? [], $omitted)));
    }

    $row = fetchOne($pdo, $table, $where);
    if ($row !== null) {
        if (!$guard($row)) {
            throw new RuntimeException('P16 guard rejected existing row in ' . $table . '.');
        }
        if ($data !== []) {
            $sets = [];
            $params = [];
            foreach ($data as $column => $value) {
                $param = ':u_' . count($params);
                $sets[] = quoteIdent((string) $column) . ' = ' . $param;
                $params[$param] = $value;
            }
            $params[':id'] = (int) $row['id'];
            $stmt = $pdo->prepare('UPDATE ' . quoteIdent($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($params);
        }
        $stats[$table]['reused'] = ($stats[$table]['reused'] ?? 0) + 1;
        return (int) $row['id'];
    }

    $all = array_merge($where, $data);
    $columns = [];
    $values = [];
    $params = [];
    foreach ($all as $column => $value) {
        $columns[] = quoteIdent((string) $column);
        $param = ':i_' . count($params);
        $values[] = $param;
        $params[$param] = $value;
    }
    $stmt = $pdo->prepare('INSERT INTO ' . quoteIdent($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');
    $stmt->execute($params);
    $stats[$table]['created'] = ($stats[$table]['created'] ?? 0) + 1;
    return (int) $pdo->lastInsertId();
}
'''
source = source[:persist_start] + persist_replacement + source[persist_end:]

actual = hashlib.sha256(source.encode()).hexdigest()
if actual != CORRECTED_SHA:
    raise SystemExit(f"P16 corrected fixture hash mismatch: {actual}")

Path("/tmp/P16_apply_representative_fixtures.php").write_text(source, "utf-8")
print(f"P16_FIXTURE_SOURCE=PASS sha256={actual}")
