<?php

requireRole(['company_owner', 'senior_logist', 'logist']);

$pageTitle = 'Отходы';
$pageContext = 'Рейсы › Отходы';
$companyId = (int) (getSessionCompanyId() ?? 0);
$company = null;

if ($companyId > 0) {
    try {
        $pdo = $db->connection();
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($company) {
            $pageContext = 'Рейсы › Отходы › Компания: ' . $company['name'];
        }
    } catch (\Throwable $e) {
    }
}

ob_start();
?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Отходы</h1>
        <div class="page-summary"><span>Раздел зарезервирован под следующий этап модуля рейсов.</span></div>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Модуль «Отходы» пока не реализован.</p>
        <p class="empty-desc">Основной рабочий блок сейчас находится в разделе «Рейсы → Линейные».</p>
        <a href="/company/trips/linear" class="btn btn-primary">Открыть линейные рейсы</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require base_path('app/View/layouts/main.php');
