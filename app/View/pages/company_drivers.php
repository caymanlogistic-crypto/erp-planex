<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с водителями недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($drivers)): ?>
<?php $isLogist = ($_SESSION['role_code'] ?? '') === 'logist'; ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('driver-create-modal')">Создать водителя</button>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Нет доступных водителей</p>
        <p class="empty-desc">У вас пока нет созданных водителей, либо руководитель ещё не выдал вам доступ к существующим.</p>
    </div>
</div>

<!-- Modal: create driver -->
<div class="modal-overlay" id="driver-create-modal" onclick="closeOnOverlay(event,this)">
  <div class="modal modal-lg driver-create-modal">
    <div class="modal-head">
      <span class="modal-title">Создать водителя</span>
      <button type="button" class="modal-close" onclick="closeModal('driver-create-modal')">✕</button>
    </div>
    <div class="modal-body">
      <?php $driverCreateFormMode = 'modal'; require base_path('app/View/partials/company_driver_create_form.php'); ?>
    </div>
    <div class="modal-foot is-spaced">
      <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
      <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('driver-create-modal')">Отмена</button>
        <button type="submit" form="driver-create-form" class="btn btn-primary">Создать водителя</button>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<?php
// Helper: render document badges for a driver cell.
// $docs — array of document rows (id, mime_type, original_name).
// Returns HTML string with compact inline badges.
function renderDocBadges(array $docs): string {
    if (empty($docs)) return '';
    $badges = [];
    foreach ($docs as $doc) {
        $mime = $doc['mime_type'] ?? '';
        if (strpos($mime, 'pdf') !== false)          $label = 'PDF';
        elseif (strpos($mime, 'image') !== false)    $label = 'IMG';
        elseif (strpos($mime, 'word') !== false || strpos($mime, 'document') !== false && strpos($mime, 'openxml') !== false) $label = 'DOC';
        elseif (strpos($mime, 'spreadsheet') !== false || strpos($mime, 'excel') !== false) $label = 'XLS';
        else $label = 'FILE';
        $badges[] = '<a href="/company/documents/view?id=' . $doc['id'] . '" target="_blank" class="driver-doc-badge" title="' . e($doc['original_name'] ?? '') . '">' . $label . '</a>';
    }
    $total = count($badges);
    $shown = array_slice($badges, 0, 2);
    $out = implode('', $shown);
    if ($total > 2) {
        $remaining = $total - 2;
        $out .= ' <a href="/company/documents?entity_type=driver&amp;entity_id=' . $docs[0]['entity_id'] . '" class="driver-doc-more">+' . $remaining . '</a>';
    }
    return $out;
}
?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список водителей</h1>
        <div class="page-summary"><span>Реестр водителей транспортных средств · Управление доступами и документами</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('driver-create-modal')">Создать водителя</button>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($drivers) ?></b> водителей</div>
        <div class="toolbar-right">
            <div class="toolbar-sort">
                <span class="toolbar-sort-label">Сортировка по:</span>
                <select class="toolbar-select" data-erp-grid-sort>
                    <option value="date" selected>По дате добавления</option>
                    <option value="alpha">По алфавиту</option>
                </select>
            </div>
            <input type="text" class="toolbar-search" placeholder="Поиск по таблице">
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>ФИО ВОДИТЕЛЯ</th>
                    <th>КОНТАКТЫ</th>
                    <th>ПАСПОРТ</th>
                    <th>ВУ</th>
                    <th>СНИЛС</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $d): ?>
                <tr data-erp-sort-date="<?= $d['id'] ?>">
                    <td><?= e($d['full_name']) ?: '—' ?></td>
                    <td>
                        <?php if (!empty($d['main_phone'])): ?>
                            <?= e($d['main_phone']) ?>
                            <?php $extraCount = (int)($d['extra_phones_count'] ?? 0); ?>
                            <?php if ($extraCount === 1): ?>
                                (+1 доп. тел.)
                            <?php elseif ($extraCount > 1): ?>
                                (+<?= $extraCount ?> доп. тел.)
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $passportDocs = $d['passport_docs'] ?? []; ?>
                        <?php if (!empty($d['passport_number']) || !empty($passportDocs)): ?>
                            <?= !empty($d['passport_number']) ? e($d['passport_number']) : '—' ?>
                            <?= renderDocBadges($passportDocs) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $licenseDocs = $d['license_docs'] ?? []; ?>
                        <?php if (!empty($d['license_number']) || !empty($licenseDocs)): ?>
                            <?= !empty($d['license_number']) ? 'ВУ ' . e($d['license_number']) : '—' ?>
                            <?= renderDocBadges($licenseDocs) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $snilsDocs = $d['snils_docs'] ?? []; ?>
                        <?php if (!empty($d['snils']) || !empty($snilsDocs)): ?>
                            <?= !empty($d['snils']) ? e($d['snils']) : '—' ?>
                            <?= renderDocBadges($snilsDocs) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($drivers) ?></b> из <b class="footer-total"><?= count($drivers) ?></b></span>
    </div>
</div>

<!-- Modal: create driver -->
<div class="modal-overlay" id="driver-create-modal" onclick="closeOnOverlay(event,this)">
  <div class="modal modal-lg driver-create-modal">
    <div class="modal-head">
      <span class="modal-title">Создать водителя</span>
      <button type="button" class="modal-close" onclick="closeModal('driver-create-modal')">✕</button>
    </div>
    <div class="modal-body">
      <?php $driverCreateFormMode = 'modal'; require base_path('app/View/partials/company_driver_create_form.php'); ?>
    </div>
    <div class="modal-foot is-spaced">
      <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
      <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('driver-create-modal')">Отмена</button>
        <button type="submit" form="driver-create-form" class="btn btn-primary">Создать водителя</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
