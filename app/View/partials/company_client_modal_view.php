<?php
require_once base_path('app/View/components/status_badge.php');
require_once base_path('app/View/components/view_formatters.php');

$clientContacts = $contacts ?? [];
$clientCanEdit = (bool) ($canEdit ?? false);
$clientCanArchive = (bool) ($canArchive ?? false);
$clientArchiveBlocked = (string) ($archiveBlockedMessage ?? '');
$clientDocumentsUrl = '/company/documents?entity_type=client&entity_id=' . (int) ($client['id'] ?? 0);
?>
<div class="modal-body driver-modal-body">
  <div class="driver-modal-layout">
    <div class="driver-modal-main">
      <h3 class="driver-view-name"><?= e($client['name'] ?? 'Клиент') ?></h3>

      <div class="driver-view-card">
        <div class="driver-view-grid">
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">ИНН</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['inn']) ? '<code>' . e($client['inn']) . '</code>' : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Тип</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['entity_type']) ? e(ui_contractor_type($client['entity_type'])) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Статус</div>
            <div class="driver-view-cell driver-view-cell-value"><?= renderStatusBadge($client['status'] ?? null) ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">КПП</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['kpp']) ? e($client['kpp']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">ОГРН</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['ogrn']) ? e($client['ogrn']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Руководитель</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['director_full_name']) ? e($client['director_full_name']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Должность</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['director_position']) ? e($client['director_position']) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Юридический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['legal_address']) ? nl2br(e($client['legal_address'])) : '<span class="is-na">—</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Фактический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($client['physical_address']) ? nl2br(e($client['physical_address'])) : '<span class="is-na">—</span>' ?></div>
          </div>
          <?php if (!empty($client['comments'])): ?>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Комментарий</div>
            <div class="driver-view-cell driver-view-cell-value driver-view-comments"><?= e($client['comments']) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="driver-modal-docs">
      <div class="driver-doc-section-title">Контакты</div>
      <?php if (empty($clientContacts)): ?>
      <div class="driver-docs-empty">Контакты не указаны.</div>
      <?php else: ?>
      <div class="file-list">
        <?php foreach ($clientContacts as $contact): ?>
        <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item">
          <div class="file-type-badge is-doc"><?= !empty($contact['is_primary']) ? 'MAIN' : 'INFO' ?></div>
          <div class="file-info">
            <div class="file-name"><?= e($contact['contact_person'] ?? 'Контакт') ?></div>
            <div class="file-meta">
              <?= e($contact['phone'] ?? '') ?: '—' ?>
              <?php if (!empty($contact['email'])): ?> · <?= e($contact['email']) ?><?php endif; ?>
              <?php if (!empty($contact['is_document_email'])): ?> · для документов<?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="form-actions" style="margin-top:12px">
        <a href="<?= e($clientDocumentsUrl) ?>" class="btn btn-secondary">Документы</a>
      </div>
      <?php if ($clientArchiveBlocked !== ''): ?>
      <div class="notice warn" style="margin-top:12px"><?= e($clientArchiveBlocked) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-foot-actions">
    <?php if ($clientCanArchive): ?>
    <button type="button" class="btn btn-ghost" data-client-archive-btn>Удалить</button>
    <?php endif; ?>
  </div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-client-view-close-btn>Закрыть</button>
    <?php if ($clientCanEdit): ?>
    <button type="button" class="btn btn-primary" data-client-edit-btn>Редактировать</button>
    <?php endif; ?>
  </div>
</div>
