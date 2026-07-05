<?php

function ensureCompanyBankColumns(PDO $pdo): void
{
    $columns = [
        'bank_account'      => "VARCHAR(32) NULL DEFAULT NULL COMMENT 'Расчётный счёт'",
        'bank_name'         => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Наименование банка'",
        'bank_bik'          => "VARCHAR(16) NULL DEFAULT NULL COMMENT 'БИК'",
        'bank_corr_account' => "VARCHAR(32) NULL DEFAULT NULL COMMENT 'Корр. счёт'",
    ];

    $showCols = $pdo->prepare("SHOW COLUMNS FROM companies");
    $showCols->execute();
    $existing = [];
    while ($row = $showCols->fetch(PDO::FETCH_ASSOC)) {
        $existing[] = $row['Field'];
    }

    $existingLookup = array_flip($existing);

    foreach ($columns as $colName => $colDef) {
        if (!isset($existingLookup[$colName])) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN `{$colName}` {$colDef}");
        }
    }
}
