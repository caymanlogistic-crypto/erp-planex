<?php

/**
 * Страница: Привязка перевозчиков
 * Доступ: только company_owner
 * Позволяет перепривязать перевозчика и его контекст к другому логисту.
 */

?>
<?php if ($company === null): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice warn">Компания не найдена.</div>
    </div>
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div><h1>Привязка перевозчиков</h1></div>
</div>
<div class="notice warn">Компания в статусе «<?= e($company['status']) ?>».</div>

<?php elseif (isset($dbError)): ?>

<div class="panel">
    <div class="panel-body">
        <div class="notice danger"><?= e($dbError) ?></div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1 class="page-title">Привязка перевозчиков</h1>
        <div class="page-summary"><span>ПЕРЕВОЗЧИКИ / <?= e(mb_strtoupper($company['name'])) ?></span></div>
    </div>
    <div class="page-head-actions">
        <a href="<?= app_url('/company/contractors/create') ?>" class="btn btn-primary">Создать перевозчика</a>
        <a href="<?= app_url('/company/contractors') ?>" class="btn btn-ghost">← К списку перевозчиков</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">

        <p class="text-muted mb-section">
            Перепривязка переносит перевозчика и его рабочий контекст (экипажи, водители, транспорт)
            от одного логиста к другому. Владение записями меняется, история операции сохраняется.
            Водители и транспорт, используемые другими перевозчиками, не перепривязываются.
        </p>

        <?php if ($successMessage): ?>
        <div class="notice ok mb-section"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($formError): ?>
        <div class="notice warn mb-section"><?= e($formError) ?></div>
        <?php endif; ?>

        <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p class="empty-title">Нет перевозчиков</p>
            <p class="empty-desc">В компании ещё нет перевозчиков. Создайте первого перевозчика.</p>
        </div>
        <?php else: ?>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Перевозчик</th>
                        <th>ИНН</th>
                        <th>Сейчас привязан</th>
                        <th class="text-center">Экипажи</th>
                        <th class="text-center">Водители</th>
                        <th class="text-center">Транспорт</th>
                        <th>Новый логист</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td class="cell-double">
                        <span class="cell-main">
                            <a href="/company/contractors/<?= $a['id'] ?>"><?= e($a['name']) ?></a>
                        </span>
                        <?php if ($a['status'] === 'archived'): ?>
                        <span class="badge">Удалён</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono"><?= e($a['inn'] ?? '') ?: '—' ?></td>
                    <td>
                        <?php if ($a['logist_name']): ?>
                        <span class="cell-main"><?= e($a['logist_name']) ?></span>
                        <span class="cell-sub"><?= e($a['logist_login'] ?? '') ?></span>
                        <?php else: ?>
                        <span class="text-muted">Не назначен</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-mono text-center"><?= (int)($a['crew_count'] ?? 0) ?></td>
                    <td class="col-mono text-center"><?= (int)($a['driver_count'] ?? 0) ?></td>
                    <td class="col-mono text-center"><?= (int)($a['vehicle_count'] ?? 0) ?></td>
                    <td>
                        <form method="post" action="/company/contractor-assignments/<?= $a['id'] ?>/assign" class="inline-form">
                            <select name="new_logist_id" class="field-select select-md">
                                <option value="">— Выберите —</option>
                                <?php foreach ($logists as $l):
                                    $selected = ((int)$l['id'] === (int)($a['logist_id'] ?? 0)) ? ' selected' : '';
                                ?>
                                <option value="<?= $l['id'] ?>"<?= $selected ?>><?= e($l['full_name']) ?> (<?= e($l['login']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Перепривязать перевозчика «<?= e($a['name']) ?>»? Владение перейдёт к новому логисту.')">Перепривязать</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php endif; ?>
