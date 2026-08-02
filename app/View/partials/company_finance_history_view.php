<?php

$fmtDt = function ($d) { return ($d && $d !== '—') ? date('d.m.Y H:i', is_numeric(strtotime($d)) ? strtotime($d) : time()) : '—'; };

$actionLabels = [
    'create' => 'Создание',
    'update' => 'Изменение',
    'post' => 'Проведение',
    'cancel' => 'Отмена',
    'allocation_create' => 'Создание распределения',
    'allocation_cancel' => 'Отмена распределения',
    'invoice_create' => 'Создание счёта',
    'invoice_update' => 'Изменение счёта',
    'invoice_cancel' => 'Аннулирование счёта',
    'status_change' => 'Изменение статуса',
    'route_payment_update' => 'Изменение платежа рейса',
    'due_date_change' => 'Изменение даты оплаты',
    'transfer_create' => 'Создание перевода',
    'transfer_confirm' => 'Подтверждение перевода',
    'transfer_cancel' => 'Отмена перевода',
    'rule_apply' => 'Применение правила',
    'category_change' => 'Изменение категории',
];
?>
<?php if ($error): ?>
<div class="form-alert alert-error"><?= e($error) ?></div>
<?php elseif (empty($logs)): ?>
<div class="empty-state compact">
    <p class="empty-title">История изменений пуста</p>
    <p class="empty-desc">По данной записи пока нет зафиксированных изменений.</p>
</div>
<?php else: ?>
<div class="history-timeline">
    <?php foreach ($logs as $log): ?>
    <div class="history-item">
        <div class="history-item-head">
            <span class="history-item-action"><?= e($actionLabels[$log['action']] ?? $log['action']) ?></span>
            <span class="history-item-date"><?= $fmtDt($log['created_at'] ?? '') ?></span>
        </div>
        <?php if ($log['created_by_user_id'] || $log['created_by_role']): ?>
        <div class="history-item-user">
            <?= e($log['created_by_role'] ?? '') ?> (ID: <?= (int)($log['created_by_user_id'] ?? 0) ?>)
        </div>
        <?php endif; ?>
        <?php
        $oldValues = $log['old_values'] ? json_decode($log['old_values'], true) : null;
        $newValues = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        ?>
        <?php if ($oldValues && is_array($oldValues)): ?>
        <div class="history-item-diff">
            <div class="history-diff-old">
                <span class="history-diff-label">Было:</span>
                <pre class="history-diff-json"><?= e(json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
            <div class="history-diff-new">
                <span class="history-diff-label">Стало:</span>
                <pre class="history-diff-json"><?= e(json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
        </div>
        <?php elseif ($newValues && is_array($newValues)): ?>
        <div class="history-item-data">
            <pre class="history-diff-json"><?= e(json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
