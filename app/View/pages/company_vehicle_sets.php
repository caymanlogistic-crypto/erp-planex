<?php
require_once base_path('app/View/components/view_formatters.php');
?>
<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный `company_id`.
</div>

<?php elseif (($company['status'] ?? '') !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Работа с транспортом недоступна.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($vehicleSets)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('vehicle-set-create-modal')">Добавить новый транспорт</button>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Транспорт ещё не создан.</p>
        <p class="empty-desc">Создайте транспорт, чтобы он появился в списке и стал доступен для просмотра и редактирования.</p>
    </div>
</div>

<?php else: ?>
<?php
function vehicle_table_num($value): string {
    if ($value === null || $value === '') return '—';
    $num = (float)$value;
    return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
}
?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Список транспорта</h1>
        <div class="page-summary"><span>Тягачи и полуприцепы · Транспортные средства перевозчиков</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('vehicle-set-create-modal')">Добавить новый транспорт</button>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid data-vehicle-sets-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($vehicleSets) ?></b> транспорта</div>
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
        <table class="table company-vehicle-sets-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-type">ТИП</th>
                    <th class="col-main-unit">ОСНОВНОЕ ТС</th>
                    <th class="col-volume">ОБЪЕМ (м3.)</th>
                    <th class="col-capacity">ГП (т.)</th>
                    <th class="col-check">СТС</th>
                    <th class="col-check">ДК</th>
                    <th class="col-check">ФОТО</th>
                    <th class="col-secondary-unit">ДОПОЛНИТЕЛЬНОЕ ТС</th>
                    <th class="col-volume">ОБЪЕМ (м3.)</th>
                    <th class="col-capacity">ГП (т.)</th>
                    <th class="col-check">СТС</th>
                    <th class="col-check">ДК</th>
                    <th class="col-check">ФОТО</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicleSets as $vs):
                    $setLabel = ui_set_type($vs['set_type'] ?? null);
                    $hasSecondary = !empty($vs['secondary_vehicle_unit_id']);
                    // Основное ТС
                    $primaryParts = [];
                    if (!empty($vs['primary_brand'])) $primaryParts[] = trim($vs['primary_brand']);
                    if (!empty($vs['primary_model'])) $primaryParts[] = trim($vs['primary_model']);
                    if (!empty($vs['primary_plate'])) $primaryParts[] = trim($vs['primary_plate']);
                    $primaryLabel = !empty($primaryParts) ? implode(' ', $primaryParts) : '—';
                    // Объём / ГП (primary)
                    $volume = vehicle_table_num($vs['primary_volume_m3'] ?? null);
                    $capacity = vehicle_table_num($vs['primary_capacity_tons'] ?? null);
                    // СТС / ДК / Фото (primary)
                    $hasSts = !empty($vs['has_sts']);
                    $hasDk = !empty($vs['has_diagnostic_card_doc']);
                    $hasPhoto = !empty($vs['has_photo']);
                    // Дополнительное ТС
                    if ($hasSecondary):
                        $secParts = [];
                        if (!empty($vs['secondary_brand'])) $secParts[] = trim($vs['secondary_brand']);
                        if (!empty($vs['secondary_model'])) $secParts[] = trim($vs['secondary_model']);
                        if (!empty($vs['secondary_plate'])) $secParts[] = trim($vs['secondary_plate']);
                        $secondaryLabel = !empty($secParts) ? implode(' ', $secParts) : '—';
                        $secVolume = vehicle_table_num($vs['secondary_volume_m3'] ?? null);
                        $secCapacity = vehicle_table_num($vs['secondary_capacity_tons'] ?? null);
                        $hasStsSec = !empty($vs['has_sts_sec']);
                        $hasDkSec = !empty($vs['has_diagnostic_card_doc_sec']);
                        $hasPhotoSec = !empty($vs['has_photo_sec']);
                    endif;
                ?>
                <tr data-vehicle-set-id="<?= (int)$vs['id'] ?>" data-erp-sort-date="<?= (int)$vs['id'] ?>">
                    <td class="col-id"><?= (int)$vs['id'] ?></td>
                    <td class="col-type"><?= e($setLabel !== '' ? $setLabel : '—') ?></td>
                    <td class="col-main-unit"><?= e($primaryLabel) ?></td>
                    <td class="col-volume"><?= e($volume) ?></td>
                    <td class="col-capacity"><?= e($capacity) ?></td>
                    <td class="col-check">
                        <?php if ($hasSts): ?>
                        <span class="table-check table-check--yes" title="СТС загружен">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="СТС не загружен">×</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-check">
                        <?php if ($hasDk): ?>
                        <span class="table-check table-check--yes" title="Диагностическая карта">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="Диагностическая карта не загружена">×</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-check">
                        <?php if ($hasPhoto): ?>
                        <span class="table-check table-check--yes" title="Фото загружено">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="Фото не загружено">×</span>
                        <?php endif; ?>
                    </td>
                    <!-- Дополнительное ТС -->
                    <td class="col-secondary-unit"><?= $hasSecondary ? e($secondaryLabel) : '—' ?></td>
                    <td class="col-volume"><?= $hasSecondary ? e($secVolume) : '—' ?></td>
                    <td class="col-capacity"><?= $hasSecondary ? e($secCapacity) : '—' ?></td>
                    <td class="col-check">
                        <?php if (!$hasSecondary): ?>
                        —
                        <?php elseif ($hasStsSec): ?>
                        <span class="table-check table-check--yes" title="СТС загружен">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="СТС не загружен">×</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-check">
                        <?php if (!$hasSecondary): ?>
                        —
                        <?php elseif ($hasDkSec): ?>
                        <span class="table-check table-check--yes" title="Диагностическая карта">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="Диагностическая карта не загружена">×</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-check">
                        <?php if (!$hasSecondary): ?>
                        —
                        <?php elseif ($hasPhotoSec): ?>
                        <span class="table-check table-check--yes" title="Фото загружено">✓</span>
                        <?php else: ?>
                        <span class="table-check table-check--no" title="Фото не загружено">×</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($vehicleSets) ?></b> из <b class="footer-total"><?= count($vehicleSets) ?></b></span>
    </div>
</div>


<?php endif; ?>

<?php if (($company ?? null) !== null && (($company['status'] ?? '') === 'active') && !isset($dbError)): ?>
<div class="modal-overlay" id="vehicle-set-create-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1">
  <div class="modal modal-lg driver-create-modal">
    <div class="modal-head">
      <span class="modal-title">Добавить новый транспорт</span>
      <button type="button" class="modal-close" onclick="closeModal('vehicle-set-create-modal')">✕</button>
    </div>
    <div class="modal-body">
      <?php
      $vehicleSetCreateFormMode = 'modal';
      $vehicleSetFormId = 'vehicle-set-create-form';
      $vehicleSetFormAction = '/company/vehicle-sets/create';
      require base_path('app/View/partials/company_vehicle_set_create_form.php');
      ?>
    </div>
    <div class="modal-foot is-spaced">
      <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
      <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('vehicle-set-create-modal')">Отмена</button>
        <button type="submit" form="vehicle-set-create-form" class="btn btn-primary">Добавить новый транспорт</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
