<?php
require_once base_path('app/View/components/status_badge.php');
$companyId = (int) ($company['id'] ?? 0);
$companyName = $company['name'] ?? 'Компания';

$na = '—';

$docSections = [
    'Карточка предприятия' => $docsByType['company_card'] ?? [],
    'Свидетельство ИНН' => $docsByType['inn_cert'] ?? [],
    'Свидетельство ОГРН' => $docsByType['ogrn_cert'] ?? [],
    'Договор' => $docsByType['contract'] ?? [],
    'Прочие документы' => $docsByType['other'] ?? [],
];

$totalDocs = 0;
foreach ($docSections as $secDocs) {
    $totalDocs += count($secDocs);
}
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
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['inn']) ? '<code>' . e($company['inn']) . '</code>' : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Статус</div>
            <div class="driver-view-cell driver-view-cell-value"><?= renderStatusBadge($company['status'] ?? null) ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">КПП</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['kpp']) ? e($company['kpp']) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">ОГРН</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['ogrn']) ? e($company['ogrn']) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Руководитель</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['director_full_name']) ? e($company['director_full_name']) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Должность</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['director_position']) ? e($company['director_position']) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Юридический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['legal_address']) ? nl2br(e($company['legal_address'])) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Фактический адрес</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['physical_address']) ? nl2br(e($company['physical_address'])) : '<span class="is-na">' . e($na) . '</span>' ?></div>
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
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_account']) ? '<code>' . e($company['bank_account']) . '</code>' : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">БИК</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_bik']) ? '<code>' . e($company['bank_bik']) . '</code>' : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Банк</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_name']) ? e($company['bank_name']) : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Корр. счёт</div>
            <div class="driver-view-cell driver-view-cell-value"><?= !empty($company['bank_corr_account']) ? '<code>' . e($company['bank_corr_account']) . '</code>' : '<span class="is-na">' . e($na) . '</span>' ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="driver-modal-docs">
      <?php if ($totalDocs === 0): ?>
        <div class="driver-docs-empty">Документы не загружены.</div>
      <?php else: ?>
        <div class="file-list">
          <?php foreach ($docSections as $secName => $secDocs): ?>
            <?php if (empty($secDocs)) continue; ?>
            <?php foreach ($secDocs as $doc):
              $name = $doc['original_name'] ?? $doc['stored_name'] ?? '';
              $parts = explode('.', $name);
              $ext = count($parts) > 1 ? strtoupper(end($parts)) : '';
              $mime = $doc['mime_type'] ?? '';
              $badgeCls = 'file-type-badge';
              $badgeTxt = $na;
              if ($ext === '') {
                  if (strpos($mime, 'pdf') !== false) {
                      $ext = 'PDF';
                  } elseif (strpos($mime, 'image') !== false) {
                      $ext = 'IMG';
                  }
              }
              if ($ext === 'PDF') {
                  $badgeCls .= ' is-pdf';
                  $badgeTxt = 'PDF';
              } elseif ($ext === 'DOC' || $ext === 'DOCX' || $ext === 'RTF' || $ext === 'ODT') {
                  $badgeCls .= ' is-doc';
                  $badgeTxt = 'DOC';
              } elseif ($ext === 'XLS' || $ext === 'XLSX' || $ext === 'CSV' || $ext === 'ODS') {
                  $badgeCls .= ' is-xls';
                  $badgeTxt = 'XLS';
              } elseif (in_array($ext, ['JPG', 'JPEG', 'PNG', 'WEBP', 'GIF', 'BMP', 'TIF', 'TIFF', 'HEIC', 'HEIF'], true)) {
                  $badgeCls .= ' is-img';
                  $badgeTxt = 'IMG';
              } else {
                  $badgeCls .= ' is-other';
                  $badgeTxt = $ext ?: 'FILE';
              }
              $rowTitle = $secName;
              if ($secName === 'Прочие документы' && !empty($doc['document_type'])) {
                  $rowTitle = (string) $doc['document_type'];
              }
            ?>
            <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item">
              <div class="<?= e($badgeCls) ?>"><?= e($badgeTxt) ?></div>
              <div class="file-info">
                <div class="file-name"><?= e($rowTitle) ?></div>
                <div class="file-meta"><?= e($name ?: 'Файл') ?></div>
              </div>
              <a href="<?= app_url('/superadmin/companies/' . (int)($company['id'] ?? 0) . '/documents/' . (int)$doc['id'] . '/view') ?>" target="_blank" rel="noopener" class="btn btn-secondary file-action-btn js-doc-popup-window">
                <span>Просмотр</span>
              </a>
              <a href="<?= app_url('/superadmin/companies/' . (int)($company['id'] ?? 0) . '/documents/' . (int)$doc['id'] . '/download') ?>" class="predef-file-clear driver-doc-download-btn" download title="Скачать файл" aria-label="Скачать файл">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M8 2.5V9.5M8 9.5L5.5 7M8 9.5L10.5 7M3 12.5H13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </a>
            </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($documentsWarning)): ?>
        <div class="notice warn mt-3"><?= e($documentsWarning) ?></div>
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
