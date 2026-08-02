<?php

use App\Service\FinanceDdsCategoryService;

$directionLabel = function ($d) {
    return match ($d) {
        'INCOME' => 'Поступление',
        'EXPENSE' => 'Расход',
        'BOTH' => 'Оба направления',
        default => e($d ?? '—'),
    };
};
?>
<?php if ($company === null): ?>
<div class="notice warn">Компания не найдена. Укажите корректный company_id.</div>
<?php elseif (($company['status'] ?? '') !== 'active'): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Статьи ДДС</h1>
        <div class="page-summary"><span>Компания находится в неактивном статусе.</span></div>
    </div>
</div>
<div class="notice warn">Работа со справочником недоступна.</div>
<?php elseif ($dbError !== null): ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Статьи ДДС</h1>
        <div class="page-summary"><span>Справочник поступлений и расходов для БДДС и распределения платежей.</span></div>
    </div>
</div>
<div class="notice warn"><?= e($dbError) ?></div>
<?php else: ?>
<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Статьи ДДС</h1>
        <div class="page-summary"><span>Справочник поступлений и расходов для БДДС и распределения платежей.</span></div>
    </div>
    <div class="page-head-right">
        <button type="button" class="btn btn-primary btn--toolbar" id="dds-category-create-btn">Создать статью</button>
    </div>
</div>

<?php if (!empty($successFlash)): ?>
<div class="notice success"><?= e($successFlash) ?></div>
<?php endif; ?>
<?php if (!empty($errorFlash)): ?>
<div class="notice warn"><?= e($errorFlash) ?></div>
<?php endif; ?>

<?php if (empty($categories)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Статьи ДДС не созданы.</p>
            <p class="empty-desc">Создайте справочник статей доходов и расходов для бюджета движения денежных средств.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-card table-card--standard">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Код</th>
                    <th>Название</th>
                    <th>Направление</th>
                    <th>Сортировка</th>
                    <th>Статус</th>
                    <th>Системная</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="col-mono"><?= e($cat['code'] ?? '—') ?></td>
                    <td><?= e($cat['name'] ?? '—') ?></td>
                    <td><?= $directionLabel($cat['direction'] ?? '') ?></td>
                    <td class="col-mono"><?= (int) ($cat['sort_order'] ?? 100) ?></td>
                    <td>
                        <span class="<?= ($cat['is_active'] ?? 0) ? 'badge badge-ok' : 'badge badge-neutral' ?>">
                            <span class="dot"></span>
                            <?= ($cat['is_active'] ?? 0) ? 'Активна' : 'Неактивна' ?>
                        </span>
                    </td>
                    <td><?= ($cat['is_system'] ?? 0) ? 'Да' : '—' ?></td>
                    <td class="col-actions">
                        <button type="button" class="btn btn-ghost btn-sm btn-action" data-edit-id="<?= (int) $cat['id'] ?>">✎</button>
                        <form method="post" action="<?= app_url('/company/finance/settings/dds-categories/active-toggle') ?>" class="inline-form" data-confirm="<?= ($cat['is_active'] ?? 0) ? 'Деактивировать статью?' : 'Активировать статью?' ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm btn-action">
                                <?= ($cat['is_active'] ?? 0) ? '⊘' : '✓' ?>
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

<div id="dds-category-create-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title">Создание статьи ДДС</span>
            <button type="button" class="modal-close" data-close-modal="dds-category-create-modal">&times;</button>
        </div>
        <div class="modal-body" id="dds-category-create-modal-body">
        </div>
    </div>
</div>

<div id="dds-category-edit-modal" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal modal-md">
        <div class="modal-head">
            <span class="modal-title">Редактирование статьи ДДС</span>
            <button type="button" class="modal-close" data-close-modal="dds-category-edit-modal">&times;</button>
        </div>
        <div class="modal-body" id="dds-category-edit-modal-body">
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

    loadModal('dds-category-create-btn', 'dds-category-create-modal', 'dds-category-create-modal-body', '/company/finance/settings/dds-categories/create');

    document.querySelectorAll('[data-edit-id]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-edit-id');
            var modal = document.getElementById('dds-category-edit-modal');
            var body = document.getElementById('dds-category-edit-modal-body');
            if (!modal || !body) return;
            body.innerHTML = '<div class="empty-state compact"><p>Загрузка...</p></div>';
            window.openModal('dds-category-edit-modal');
            fetch(window.getErpBasePath() + '/company/finance/settings/dds-categories/edit?id=' + id)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<div class="form-alert alert-error">Не удалось загрузить форму.</div>';
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
