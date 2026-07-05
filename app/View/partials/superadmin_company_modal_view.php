<?php
require_once base_path('app/View/components/status_badge.php');
$companyId = (int) ($company['id'] ?? 0);
$companyName = $company['name'] ?? 'Компания';
?>
<div class="modal-body driver-modal-body">
  <?php if (!empty($docWarning)): ?>
  <div class="notice warn mt-3"><?= e($docWarning) ?></div>
  <?php endif; ?>
  <div class="driver-modal-layout">
    <div class="driver-modal-main">
      <h3 class="driver-view-name"><?= e($companyName) ?></h3>

      <div class="driver-view-card">
        <div class="driver-view-grid">
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">ИНН</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['inn']) ? '<code>' . e($company['inn']) . '</code>' : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Статус</div>
            <div class="driver-view-cell driver-view-cell-value"><?= renderStatusBadge($company['status'] ?? null) ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">КПП</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['kpp']) ? e($company['kpp']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">ОГРН</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['ogrn']) ? e($company['ogrn']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Руководитель</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['director_full_name']) ? e($company['director_full_name']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Должность</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['director_position']) ? e($company['director_position']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Юридический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['legal_address']) ? nl2br(e($company['legal_address'])) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Фактический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['physical_address']) ? nl2br(e($company['physical_address'])) : '<span class="is-na">—</span>' ?></div>
          </div>
          <?php if (!empty($company['comments'])): ?>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Комментарий</div>
            <div class="driver-view-cell driver-view-cell-value driver-view-comments"><?= e($company['comments']) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($company['bank_account']) || !empty($company['bank_name']) || !empty($company['bank_bik']) || !empty($company['bank_corr_account'])): ?>
      <div class="driver-view-card mt-3">
        <h4 class="driver-view-section-title">Банковские реквизиты</h4>
        <div class="driver-view-grid">
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Расчётный счёт</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_account']) ? '<code>' . e($company['bank_account']) . '</code>' : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">БИК</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_bik']) ? '<code>' . e($company['bank_bik']) . '</code>' : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Банк</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_name']) ? e($company['bank_name']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Корр. счёт</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_corr_account']) ? '<code>' . e($company['bank_corr_account']) . '</code>' : '<span class="is-na">—</span>' ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-foot-actions"></div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-company-view-close-btn>Закрыть</button>
    <?php if ($canEdit ?? false): ?>
    <button type="button" class="btn btn-primary" data-company-edit-btn>Редактировать</button>
    <?php endif; ?>
  </div>
</div>
