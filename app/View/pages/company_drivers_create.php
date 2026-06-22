<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
        <h1 class="page-title">Создать водителя</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="form-alert alert-warning">
        <div class="alert-mark">
            <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M9 6V10M9 12V12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </div>
        <div class="alert-body">
            <div class="alert-body-title">Компания неактивна</div>
            <div class="alert-body-sub">Статус компании: «<?= e($company['status']) ?>». Создание водителей недоступно.</div>
        </div>
    </div>
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
        <h1 class="page-title">Водитель создан</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers/create" class="btn btn-secondary">Создать ещё</a>
        <a href="/company/drivers" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="page-content">
    <div class="panel">
        <div class="panel-body">

            <div class="form-alert alert-success">
                <div class="alert-mark">
                    <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M4 9.5L7 12.5L14 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="alert-body">
                    <div class="alert-body-title">Водитель успешно создан</div>
                    <?php if (!empty($uploadedDocs)): ?>
                        <div class="alert-body-sub">Загружено документов: <?= count($uploadedDocs) ?>.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($docErrors)): ?>
                <div class="form-alert alert-warning">
                    <div class="alert-mark">
                        <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="1.6"/><path d="M9 6V10M9 12V12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </div>
                    <div class="alert-body">
                        <div class="alert-body-title">Некоторые документы не загружены</div>
                        <div class="alert-body-sub"><?= implode(', ', array_map('e', $docErrors)) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <div class="section-title">Данные водителя</div>
                <dl class="dl">
                    <dt>ФИО</dt>
                    <dd><?= e($createdDriver['full_name']) ?></dd>
                    <dt>Телефон</dt>
                    <dd class="mono"><?= e($createdDriver['phone']) ?></dd>
                    <?php if (!empty($createdDriver['email'])): ?>
                    <dt>Email</dt>
                    <dd><?= e($createdDriver['email']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($createdDriver['extra_phones'] ?? [])): ?>
                    <dt>Доп. телефоны</dt>
                    <dd>
                        <?php foreach ($createdDriver['extra_phones'] as $ep): ?>
                            <div class="mono"><?= e($ep['phone']) ?><?= !empty($ep['comment']) ? ' (' . e($ep['comment']) . ')' : '' ?></div>
                        <?php endforeach; ?>
                    </dd>
                    <?php endif; ?>
                    <?php if (!empty($createdDriver['passport_number'])): ?>
                    <dt>Паспорт</dt>
                    <dd><?= e($createdDriver['passport_number']) ?><?= !empty($createdDriver['passport_issued_by']) ? ' · ' . e($createdDriver['passport_issued_by']) : '' ?><?= !empty($createdDriver['passport_issue_date']) ? ' · ' . e($createdDriver['passport_issue_date']) : '' ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($createdDriver['snils'])): ?>
                    <dt>СНИЛС</dt>
                    <dd class="mono"><?= e($createdDriver['snils']) ?></dd>
                    <?php endif; ?>
                    <dt>Номер ВУ</dt>
                    <dd class="mono"><?= e($createdDriver['license_number'] ?? '—') ?></dd>
                    <dt>Статус</dt>
                    <dd><span class="badge badge-ok">Активен</span></dd>
                </dl>
            </div>

        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <span class="page-eyebrow">ПОДРЯДЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span>
        <h1 class="page-title">Создать водителя</h1>
    </div>
    <div class="page-head-actions">
        <a href="/company/drivers" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<div class="page-content">

<?php require base_path('app/View/partials/company_driver_create_form.php'); ?>

</div><!-- /.page-content -->

<?php endif; ?>
