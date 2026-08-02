<?php require_once __DIR__ . '/../components/status_badge.php'; ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Использование БД</h1>
        <div class="page-summary"><span>Мониторинг пула баз данных компании</span></div>
    </div>
</div>

<div class="page-content">

    <div class="notice info db-usage-info">
        <div class="db-usage-info-body">
            <p><strong>Как работает пул БД:</strong> Базы данных пула переиспользуются. После удаления экспедитора база очищается и возвращается в пул.</p>
            <p><strong>Статус «Занята»</strong> — база назначена компании или зарезервирована для операции. <strong>Статус «Свободна»</strong> — база готова к назначению.</p>
            <p><strong>Пароли и доступы не отображаются</strong> на этой странице.</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3 class="panel-head-title">Базы данных пула</h3>
            <span class="badge"><?= (int)$totalPool ?> записей</span>
        </div>
        <?php if (empty($poolEntries)): ?>
        <div class="panel-body">
            <div class="empty-state">
                <p class="empty-title">Пул не настроен</p>
                <p class="empty-desc">Переменная COMPANY_DB_POOL_JSON не содержит записей.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>База данных</th>
                        <th>Хост</th>
                        <th>Статус</th>
                        <th>Состояние</th>
                        <th>ID компании</th>
                        <th>Компания</th>
                        <th>Статус компании</th>
                        <th>Резервирование</th>
                        <th>Назначена</th>
                        <th>Освобождена</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poolEntries as $e): ?>
                    <tr>
                        <td><code><?= e($e['db_identifier']) ?></code></td>
                        <td class="col-muted"><?= e($e['host']) ?></td>
                        <td><span class="badge <?= $e['statusClass'] ?>"><?= e($e['status']) ?></span></td>
                        <td class="col-muted"><?= e($e['state_label']) ?></td>
                        <td><?= $e['company_id'] ? (int)$e['company_id'] : '<span class="col-muted">—</span>' ?></td>
                        <td><?= $e['company_name'] ? e($e['company_name']) : '<span class="col-muted">—</span>' ?></td>
                        <td><?= $e['company_status'] ? e($e['company_status']) : '<span class="col-muted">—</span>' ?></td>
                        <td class="col-muted"><?= e($e['usage_note_label']) ?></td>
                        <td class="col-muted"><?= $e['usage_created_at'] ? e(substr($e['usage_created_at'], 0, 16)) : '—' ?></td>
                        <td class="col-muted"><?= $e['usage_released_at'] ? e(substr($e['usage_released_at'], 0, 16)) : '<span class="col-muted">—</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
