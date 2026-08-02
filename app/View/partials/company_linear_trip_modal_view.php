<?php

use App\Service\DocumentService;
use App\Service\LinearRouteService;

$na = '—';
$routeTitle = 'Рейс #' . (int) ($route['id'] ?? 0);
$executorName = trim((string) (($route['executor_contractor_name'] ?? '') . ' · ' . ($route['executor_driver_name'] ?? '')));
$executorPlates = trim((string) ($route['executor_primary_plate'] ?? ''));
if (!empty($route['executor_secondary_plate'])) {
    $executorPlates .= ' + ' . $route['executor_secondary_plate'];
}
$documentTitles = [
    'customer_document' => 'Договор/заявка с заказчиком',
    'carrier_document' => 'Договор/заявка с перевозчиком',
    'principal_document' => 'Договор/заявка с принципалом',
];
$paymentLabel = static function (array $payment): string {
    $paymentMethod = LinearRouteService::paymentMethodLabel($payment['payment_method'] ?? null);
    $vatLabel = LinearRouteService::vatRateLabel(isset($payment['vat_rate']) ? (string) $payment['vat_rate'] : null);
    $parts = [
        LinearRouteService::formatAmount($payment['amount'] ?? null),
        $paymentMethod . ' / ' . $vatLabel,
        (string) ($payment['payment_due_type'] ?? '—'),
    ];
    if (!empty($payment['payment_due_days'])) {
        $parts[] = (int) $payment['payment_due_days'] . ' ' . (($payment['payment_due_days_kind'] ?? '') === 'calendar' ? 'календарных дней' : 'рабочих дней');
    }

    return implode(' · ', array_filter($parts, static fn(string $part): bool => trim($part) !== ''));
};
$financeSectionRendered = false;
?>
<div class="modal-body driver-modal-body">
  <div class="driver-modal-layout">
    <div class="driver-modal-main">
      <h3 class="driver-view-name"><?= e($routeTitle) ?></h3>

      <div class="driver-view-card">
        <div class="driver-view-grid">
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Тип рейса</div>
            <div class="driver-view-cell driver-view-cell-value"><?= e(LinearRouteService::routeTypeLabel((string) ($route['route_type'] ?? ''))) ?></div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Статус</div>
            <div class="driver-view-cell driver-view-cell-value"><?= e((string) ($route['status'] ?? 'active')) ?></div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Заказчик</div>
            <div class="driver-view-cell driver-view-cell-value"><?= e((string) ($route['client_name'] ?? $na)) ?></div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Перевозчик</div>
            <div class="driver-view-cell driver-view-cell-value"><?= e((string) ($route['carrier_name'] ?? $na)) ?></div>
          </div>

          <?php if (($route['route_type'] ?? '') === LinearRouteService::ROUTE_TYPE_AGENCY): ?>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Принципалы</div>
            <div class="driver-view-cell driver-view-cell-value">
              <?php foreach (($route['principal_items'] ?? []) as $principal): ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Юрлицо</div>
                <div><?= e((string) ($principal['display_name'] ?? $na)) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Исполнитель рейса</div>
            <div class="driver-view-cell driver-view-cell-value">
              <?= $executorName !== '' ? e($executorName) : '<span class="is-na">' . e($na) . '</span>' ?>
              <?php if ($executorPlates !== ''): ?>
              <div class="driver-view-hint"><?= e($executorPlates) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Тип груза</div>
            <div class="driver-view-cell driver-view-cell-value"><?= e((string) ($route['cargo_type_name'] ?? $na)) ?></div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Плановые даты</div>
            <div class="driver-view-cell driver-view-cell-value">
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Загрузка</div>
                <div><?= e(ui_date($route['planned_loading_date'] ?? null)) ?></div>
              </div>
              <?php if (LinearRouteService::shouldShowPlannedUnloading($route['planned_loading_date'] ?? null, $route['planned_unloading_date'] ?? null)): ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Выгрузка</div>
                <div><?= e(ui_date($route['planned_unloading_date'] ?? null)) ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Фактические даты</div>
            <div class="driver-view-cell driver-view-cell-value">
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Загрузка</div>
                <div><?= e(ui_date($route['actual_loading_date'] ?? null)) ?></div>
              </div>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Выгрузка</div>
                <div><?= e(ui_date($route['actual_unloading_date'] ?? null)) ?></div>
              </div>
            </div>
          </div>

          <?php if ($isFinanceRealm): ?>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Оплаты заказчика</div>
            <div class="driver-view-cell driver-view-cell-value">
              <?php foreach (($route['payments']['customer'] ?? []) as $payment): ?>
              <div class="driver-view-inline-row">
                <div><?= e($paymentLabel($payment)) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Оплаты перевозчика</div>
            <div class="driver-view-cell driver-view-cell-value">
              <?php foreach (($route['payments']['carrier'] ?? []) as $payment): ?>
              <div class="driver-view-inline-row">
                <div><?= e($paymentLabel($payment)) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php foreach (($route['principal_items'] ?? []) as $principal): ?>
            <?php $principalPayments = $route['payments']['principals'][(int) ($principal['id'] ?? 0)] ?? []; ?>
          <div class="driver-view-row">
            <div class="driver-view-cell driver-view-cell-label">Оплаты принципала</div>
            <div class="driver-view-cell driver-view-cell-value">
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label"><?= e((string) ($principal['display_name'] ?? $na)) ?></div>
                <div></div>
              </div>
              <?php foreach ($principalPayments as $payment): ?>
              <div class="driver-view-inline-row">
                <div><?= e($paymentLabel($payment)) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php
          $allFinancePayments = array_merge(
              $route['payments']['customer'] ?? [],
              $route['payments']['carrier'] ?? [],
              ...array_map(static fn(array $p): array => $p, array_values($route['payments']['principals'] ?? []))
          );
          $paymentDetailLines = static function (array $payment): void {
              $methodLabel = LinearRouteService::paymentMethodLabel($payment['payment_method'] ?? null);
              $vatLabel = LinearRouteService::vatRateLabel(isset($payment['vat_rate']) ? (string) $payment['vat_rate'] : null);
              $dueType = (string) ($payment['payment_due_type'] ?? '—');
              $dueDate = $payment['calculated_due_date'] ?? null;
              $dueDateStr = $dueDate !== null ? date('d.m.Y', strtotime($dueDate)) : '—';
              $dueDaysStr = '';
              if (!empty($payment['payment_due_days'])) {
                  $dueDaysStr = (int) $payment['payment_due_days'] . ' ' . ((string) ($payment['payment_due_days_kind'] ?? '') === 'calendar' ? 'кал.' : 'раб.');
              }
              ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Статус</div>
                <div>
                  <span class="<?= e(LinearRouteService::paymentStatusBadgeClass($payment['payment_status'] ?? 'unpaid')) ?>"><?= e(LinearRouteService::paymentStatusLabel($payment['payment_status'] ?? 'unpaid')) ?></span>
                </div>
              </div>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Сумма</div>
                <div><?= e(LinearRouteService::formatAmount($payment['amount'] ?? null)) ?></div>
              </div>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Оплата / НДС</div>
                <div><?= e($methodLabel) ?> / <?= e($vatLabel) ?></div>
              </div>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Срок</div>
                <div><?= e($dueType) ?><?= $dueDaysStr !== '' ? ' · ' . e($dueDaysStr) : '' ?></div>
              </div>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Расч. дата</div>
                <div><?= e($dueDateStr) ?></div>
              </div>
              <?php
          };
          ?>
          <?php if (!empty($allFinancePayments)): ?>
          <?php $financeSectionRendered = true; ?>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Финансы рейса</div>
            <div class="driver-view-cell driver-view-cell-value">
              <?php foreach (($route['payments']['customer'] ?? []) as $payment): ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Заказчик</div>
                <div></div>
              </div>
              <?php $paymentDetailLines($payment); ?>
              <?php endforeach; ?>
              <?php foreach (($route['payments']['carrier'] ?? []) as $payment): ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Перевозчик</div>
                <div></div>
              </div>
              <?php $paymentDetailLines($payment); ?>
              <?php endforeach; ?>
              <?php foreach (($route['principal_items'] ?? []) as $principal): ?>
                <?php $principalPayments = $route['payments']['principals'][(int) ($principal['id'] ?? 0)] ?? []; ?>
                <?php foreach ($principalPayments as $payment): ?>
              <div class="driver-view-inline-row">
                <div class="driver-view-inline-label">Принципал</div>
                <div></div>
              </div>
              <?php $paymentDetailLines($payment); ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php endif; ?>

          <?php if (!empty($route['comments'])): ?>
          <div class="driver-view-row driver-view-row-wide">
            <div class="driver-view-cell driver-view-cell-label">Комментарий</div>
            <div class="driver-view-cell driver-view-cell-value driver-view-comments"><?= e($route['comments']) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="driver-modal-docs">
      <div class="section-title driver-docs-heading">Документы</div>
      <div class="file-list">
        <?php foreach ($documentTitles as $docKey => $docTitle): ?>
            <?php $documents = $docsByCode[$docKey] ?? []; ?>
            <?php if (empty($documents)) continue; ?>
            <?php foreach ($documents as $document): ?>
                <?php $badge = DocumentService::detectDocumentBadge($document['original_name'] ?? $document['stored_name'] ?? null, $document['mime_type'] ?? null); ?>
        <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item">
          <div class="file-type-badge <?= e($badge['badge_class']) ?>"><?= e($badge['badge_text']) ?></div>
          <div class="file-info">
            <div class="file-name"><?= e($docTitle) ?></div>
            <div class="file-meta"><?= e($document['original_name'] ?? $document['stored_name'] ?? 'Файл') ?></div>
          </div>
          <a href="<?= app_url('/company/documents/view?id=' . (int) $document['id']) ?>" target="_blank" rel="noopener" class="btn btn-secondary file-action-btn js-doc-popup-window">
            <span>Просмотр</span>
          </a>
          <a href="<?= app_url('/company/documents/download?id=' . (int) $document['id']) ?>" class="predef-file-clear driver-doc-download-btn" download title="Скачать файл" aria-label="Скачать файл">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M8 2.5V9.5M8 9.5L5.5 7M8 9.5L10.5 7M3 12.5H13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
        </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php foreach (($docsByCode['other'] ?? []) as $document): ?>
            <?php $badge = DocumentService::detectDocumentBadge($document['original_name'] ?? $document['stored_name'] ?? null, $document['mime_type'] ?? null); ?>
        <div class="file-item file-item-predef document-file-row has-file has-existing-file driver-doc-view-item">
          <div class="file-type-badge <?= e($badge['badge_class']) ?>"><?= e($badge['badge_text']) ?></div>
          <div class="file-info">
            <div class="file-name"><?= e($document['document_type'] ?? $document['type_name'] ?? 'Документ') ?></div>
            <div class="file-meta"><?= e($document['original_name'] ?? $document['stored_name'] ?? 'Файл') ?></div>
          </div>
          <a href="<?= app_url('/company/documents/view?id=' . (int) $document['id']) ?>" target="_blank" rel="noopener" class="btn btn-secondary file-action-btn js-doc-popup-window">
            <span>Просмотр</span>
          </a>
          <a href="<?= app_url('/company/documents/download?id=' . (int) $document['id']) ?>" class="predef-file-clear driver-doc-download-btn" download title="Скачать файл" aria-label="Скачать файл">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M8 2.5V9.5M8 9.5L5.5 7M8 9.5L10.5 7M3 12.5H13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </a>
        </div>
        <?php endforeach; ?>

        <?php if (empty($docsByCode['customer_document']) && empty($docsByCode['carrier_document']) && empty($docsByCode['principal_document']) && empty($docsByCode['other'])): ?>
        <div class="driver-docs-empty">Документы не загружены.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal-foot is-spaced">
  <div class="modal-foot-actions">
    <?php if ($canDelete): ?>
    <button type="button" class="btn btn-ghost" data-linear-trip-delete-btn>Удалить</button>
    <?php endif; ?>
  </div>
  <div class="modal-foot-actions">
    <button type="button" class="btn btn-ghost" data-linear-trip-view-close-btn>Закрыть</button>
    <?php if ($canEdit): ?>
    <button type="button" class="btn btn-primary" data-linear-trip-edit-btn>Редактировать</button>
    <?php endif; ?>
  </div>
</div>
