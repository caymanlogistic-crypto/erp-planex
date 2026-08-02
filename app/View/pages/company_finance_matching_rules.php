<?php

use App\Service\FinanceMatchingRuleService;

$directionLabel = function ($d) {
    return match ($d) {
        'INCOME' => 'Поступление',
        'EXPENSE' => 'Расход',
        default => 'Оба',
    };
};

$actionTypeLabel = function ($t) {
    return match ($t) {
        'categorize' => 'Категория ДДС',
        'match_invoice' => 'Связать со счётом',
        'match_counterparty' => 'Связать контрагента',
        default => e($t ?? '—'),
    };
};

$confidenceLabel = function ($c) {
    return match ($c) {
        'high' => 'Высокая',
        'medium' => 'Средняя',
        'low' => 'Низкая',
        default => e($c ?? '—'),
    };
};
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Правила разнесения</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа с правилами недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Правила разнесения</h1>
        <div class="page-summary"><span>Автоматическое распределение банковских операций по категориям ДДС и счетам.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Правила разнесения</h1>
        <div class="page-summary"><span>Автоматическое распределение банковских операций по категориям ДДС и контрагентам.</span></div>
    </div>
    <div class="page-head-right">
        <button type="button" class="btn btn-primary btn--toolbar" id="matching-rule-create-btn">Создать правило</button>
    </div>
</div>

<?php if (!empty($successFlash)): ?>
<div class="notice success"><?= e($successFlash) ?></div>
<?php endif; ?>
<?php if (!empty($errorFlash)): ?>
<div class="notice warn"><?= e($errorFlash) ?></div>
<?php endif; ?>

<?php if (empty($rules)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Правила разнесения не созданы.</p>
            <p class="empty-desc">Создайте правила для автоматического распределения банковских операций по категориям ДДС и контрагентам.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Приоритет</th>
                    <th>Название</th>
                    <th>Направление</th>
                    <th>Счёт</th>
                    <th>ИНН</th>
                    <th>Действие</th>
                    <th>Авто</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr>
                    <td class="col-mono"><?= (int)$rule['priority'] ?></td>
                    <td><?= e($rule['name'] ?? '—') ?></td>
                    <td><?= $directionLabel($rule['direction'] ?? '') ?></td>
                    <td class="col-mono"><?= e($rule['bank_account_id'] ? '#' . (int)$rule['bank_account_id'] : '—') ?></td>
                    <td class="col-mono"><?= e($rule['counterparty_inn'] ?? '—') ?></td>
                    <td><?= $actionTypeLabel($rule['action_type'] ?? '') ?></td>
                    <td><?= ($rule['auto_apply'] ?? 0) ? 'Да' : '—' ?></td>
                    <td>
                        <span class="<?= ($rule['active'] ?? 0) ? 'badge badge-ok' : 'badge badge-neutral' ?>">
                            <span class="dot"></span>
                            <?= ($rule['active'] ?? 0) ? 'Активно' : 'Неактивно' ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <button type="button" class="btn btn-ghost btn-sm btn-action" data-preview-id="<?= (int)$rule['id'] ?>" title="Предпросмотр">&#9654;</button>
                        <button type="button" class="btn btn-ghost btn-sm btn-action" data-edit-id="<?= (int)$rule['id'] ?>">&#9998;</button>
                        <form method="post" action="<?= app_url('/company/finance/settings/matching-rules/toggle') ?>" class="inline-form" data-confirm="<?= ($rule['active'] ?? 0) ? 'Деактивировать правило?' : 'Активировать правило?' ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm btn-action">
                                <?= ($rule['active'] ?? 0) ? '⊘' : '✓' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div id="matching-rule-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Создание правила разнесения</span>
            <button type="button" class="modal-close" data-close-modal="matching-rule-create-modal">&times;</button>
        </div>
        <div class="modal-body" id="matching-rule-create-modal-body">
        </div>
    </div>
</div>

<div id="matching-rule-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Редактирование правила разнесения</span>
            <button type="button" class="modal-close" data-close-modal="matching-rule-edit-modal">&times;</button>
        </div>
        <div class="modal-body" id="matching-rule-edit-modal-body">
        </div>
    </div>
</div>

<div id="matching-rule-preview-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="1" data-close-on-escape="1">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-title">Предпросмотр правила</span>
            <button type="button" class="modal-close" data-close-modal="matching-rule-preview-modal">&times;</button>
        </div>
        <div class="modal-body" id="matching-rule-preview-body">
            <div class="empty-state compact"><p>Загрузка...</p></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function loadModal(btnId, modalId, bodyId, url) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function() {
            var modal = document.getElementById(modalId);
            var body = document.getElementById(bodyId);
            if (!modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal(modalId);
            fetch(window.getErpBasePath() + url)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму.</div>';
                });
        });
    }

    loadModal('matching-rule-create-btn', 'matching-rule-create-modal', 'matching-rule-create-modal-body', '/company/finance/settings/matching-rules/create');

    document.querySelectorAll('[data-edit-id]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-edit-id');
            var modal = document.getElementById('matching-rule-edit-modal');
            var body = document.getElementById('matching-rule-edit-modal-body');
            if (!modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('matching-rule-edit-modal');
            fetch(window.getErpBasePath() + '/company/finance/settings/matching-rules/edit?id=' + id)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму.</div>';
                });
        });
    });

    document.querySelectorAll('[data-preview-id]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-preview-id');
            var modal = document.getElementById('matching-rule-preview-modal');
            var body = document.getElementById('matching-rule-preview-body');
            if (!modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('matching-rule-preview-modal');
            fetch(window.getErpBasePath() + '/company/finance/settings/matching-rules/preview?id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var html = '<div class="section-title">Правило: ' + data.rule_name + '</div>';
                    html += '<p>Найдено совпадений: ' + data.total_matches + '</p>';
                    if (data.results.length > 0) {
                        html += '<div class="table-scroll"><table class="table"><thead><tr><th>Дата</th><th>Контрагент</th><th>Сумма</th><th>Назначение</th><th>Уверенность</th><th>Результат</th><th>Причина</th></tr></thead><tbody>';
                        data.results.forEach(function(r) {
                            html += '<tr>';
                            html += '<td>' + r.operation_date + '</td>';
                            html += '<td>' + r.counterparty_name + '</td>';
                            html += '<td class="col-mono">' + r.amount + '</td>';
                            html += '<td>' + r.purpose + '</td>';
                            html += '<td><span class="badge badge-' + (r.confidence === 'high' ? 'ok' : r.confidence === 'medium' ? 'warning' : 'neutral') + '">' + r.confidence + '</span></td>';
                            html += '<td>' + r.result + '</td>';
                            html += '<td>' + r.reason + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                    } else {
                        html += '<div class="empty-state compact"><p>Нет непроведённых операций, соответствующих правилу.</p></div>';
                    }
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить предпросмотр.</div>';
                });
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});
</script>
<?php endif; ?>
