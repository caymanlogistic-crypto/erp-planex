<?php

    requireRole(['company_owner']);
    verifyCsrfRequest();

    $companyId = (int)(getSessionCompanyId() ?? 0);

    if ($companyId <= 0) {
        $_SESSION['flash_error'] = 'Компания не найдена.';
        redirect_to('/company/finance/bank-accounts');
    }

    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company || $company['status'] !== 'active') {
            $_SESSION['flash_error'] = 'Компания недоступна.';
            redirect_to('/company/finance/bank-accounts');
        }

        if (!isset($_FILES['bank_statement']) || $_FILES['bank_statement']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Ошибка загрузки файла.';
            redirect_to('/company/finance/bank-accounts');
        }

        $tmpPath = $_FILES['bank_statement']['tmp_name'];
        $originalName = $_FILES['bank_statement']['name'];

        if (!is_uploaded_file($tmpPath) || (int) ($_FILES['bank_statement']['size'] ?? 0) <= 0
            || (int) ($_FILES['bank_statement']['size'] ?? 0) > ERP_MAX_FILE_SIZE) {
            $_SESSION['flash_error'] = 'Файл не прошёл проверку или превышает 20 МБ.';
            redirect_to('/company/finance/bank-accounts');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $_SESSION['flash_error'] = 'Можно загружать только файлы в формате XLSX.';
            redirect_to('/company/finance/bank-accounts');
        }

        $fileHash = hash_file('sha256', $tmpPath);
        if ($fileHash === false) {
            $_SESSION['flash_error'] = 'Не удалось прочитать файл.';
            redirect_to('/company/finance/bank-accounts');
        }

        $localDbConfig = companyDatabaseConfig($config, $company);
        $localDb = new \App\Core\Database($localDbConfig);
        $localPdo = $localDb->connection();
        applyLocalMigrations($localPdo);

        $parser = new \App\Service\BankStatementXlsxParser();
        $parsed = $parser->parse($tmpPath);

        if (empty($parsed['account_number']) && preg_match('/(?<!\d)(\d{20})(?!\d)/', $originalName, $m)) {
            $parsed['account_number'] = $m[1];
        }
        if (empty($parsed['account_number'])) {
            $_SESSION['flash_error'] = 'Не удалось определить номер счёта в файле. Проверьте формат выписки.';
            redirect_to('/company/finance/bank-accounts');
        }

        $result = \App\Service\BankFinanceService::importParsedData(
            $localPdo,
            $parsed,
            'manual',
            null,
            $originalName,
            $fileHash
        );

        if ($result['status'] === 'skipped') {
            $_SESSION['flash_info'] = 'Этот файл уже был импортирован ранее.';
        } elseif ($result['status'] === 'error') {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_success'] = $result['message'];
        }

        redirect_to('/company/finance/bank-accounts');
    } catch (\Exception $e) {
        $_SESSION['flash_error'] = 'Ошибка импорта: ' . $e->getMessage();
        redirect_to('/company/finance/bank-accounts');
    }
