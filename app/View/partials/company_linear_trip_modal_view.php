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

$routeStatusMap = [
    'active' => 'Активен',
    'completed' => 'Завершён',
    'cancelled' => 'Отменён',
    'draft' => 'Черновик',
];
$routeStatusRaw = (string) ($route['status'] ?? 'active');
$routeStatusLabel = $routeStatusMap[$routeStatusRaw] ?? $routeStatusRaw;

$documentTitles = [
    'customer_document' => 'Договор/заявка с заказчиком',
    'carrier_document' => 'Договор/заявка с перевозчиком',
    'principal_document' => 'Договор/заявка с принципалом',
];

$paymentTotal = static function (array $payments): float {
    $total = 0.0;
    foreach ($payments as $payment) {
        $total += (float) ($payment['amount'] ?? 0);
    }
    return $total;
};

$customerPayments = $route['payments']['customer'] ?? [];
$carrierPayments = $route['payments']['carrier'] ?? [];
$customerTotal = $paymentTotal($customerPayments);
$carrierTotal = $paymentTotal($carrierPayments);
$margin = $customerTotal - $carrierTotal;

$paymentDueLabel = static function (array $payment): string {
    $dueType = trim((string) ($payment['payment_due_type'] ?? ''));
    $parts = [];
    if ($dueType !== '') {
        $parts[] = $dueType;
    }
    if (!empty($payment['payment_due_days'])) {
        $parts[] = (int) $payment['payment_due_days'] . ' ' . (($payment['payment_due_days_kind'] ?? '') === 'calendar' ? 'РД' : 'БД');
    }
    if (!empty($payment['calculated_due_date'])) {
        $ts = strtotime((string) $payment['calculated_due_date']);
        if ($ts !== false) {
            $parts[] = date('d.m.Y', $ts);
        }
    }
    return $parts !== [] ? implode(' · ', $parts) : '—';
};

$renderPayment = static function (array $payment) use ($paymentDueLabel): void {
    $methodLabel = LinearRouteService::paymentMethodLabel($payment['payment_method'] ?? null);
    $vatLabel = LinearRouteService::vatRateLabel(isset($payment['vat_rate']) ? (string) $payment['vat_rate'] : null);
    $status = $payment['payment_status'] ?? 'unpaid';
    ?>
    <div class="trip-view-payment">
      <div class="trip-view-payment-main">
        <strong class="trip-view-payment-amount"><?= e(LinearRouteService::formatAmount($payment['amount'] ?? null)) ?> ₽</strong>
        <span class="trip-view-payment-meta"><?= e($methodLabel) ?> · <?= e($vatLabel) ?></span>
      </div>
      <div class="trip-view-payment-side">
        <span class="<?= e(LinearRouteService::paymentStatusBadgeClass($status)) ?>"><?= e(LinearRouteService::paymentStatusLabel($status)) ?></span>
        <span class="trip-view-payment-due"><?= e($paymentDueLabel($payment)) ?></span>
      </div>
    </div>
    <?php
};
?>
<div class="modal-body driver-modal-body trip-view-modal-body">
  <div class="driver-modal-layout trip-view-layout">
    <div class="driver-modal-main trip-view-main">
      <div class="trip-view-header">
        <div>
          <h3 class="driver-view-name trip-view-title"><?= e($routeTitle) ?></h3>
          <div class="trip-view-type"><?= e(LinearRouteService::routeTypeLabel((string) ($route['route_type'] ?? ''))) ?></div>
        </div>
        <span class="trip-view-route-status"><?= e($routeStatusLabel) ?></span>
      </div>

      <section class="trip-view-section trip-view-section-main">
        <div class="trip-view-section-title">Основное</div>
        <div class="trip-view-summary-grid">
          <div class="trip-view-summary-item trip-view-summary-item-wide">
            <span class="trip-view-label">Заказчик</span>
            <strong><?= e((string) ($route['client_name'] ?? $na)) ?></strong>
          </div>

          <div class="trip-view-summary-item trip-view-summary-item-wide">
            <span class="trip-view-label">Исполнитель рейса</span>
            <strong><?= $executorName !== '' ? e($executorName) : e($na) ?></strong>
            <?php if ($executorPlates !== ''): ?>
            <span class="trip-view-hint"><?= e($executorPlates) ?></span>
            <?php endif; ?>
          </div>

          <div class="trip-view-summary-item">
            <span class="trip-view-label">Плановая дата загрузки</span>
            <strong><?= e(ui_date($route['planned_loading_date'] ?? null)) ?></strong>
          </div>

          <?php if (($route['route_type'] ?? '') === LinearRouteService::ROUTE_TYPE_AGENCY): ?>
          <div class="trip-view-summary-item">
            <span class="trip-view-label">Агентский договор</span>
            <strong>Да</strong>
          </div>
          <?php endif; ?>
        </div>

        <?php if (($route['route_type'] ?? '') === LinearRouteService::ROUTE_TYPE_AGENCY && !empty($route['principal_items'])): ?>
        <div class="trip-view-principals">
          <span class="trip-view-label">Принципалы</span>
          <div class="trip-view-principal-list">
            <?php foreach (($route['principal_items'] ?? []) as $principal): ?>
            <span><?= e((string) ($principal['display_name'] ?? $na)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </section>

      <?php if ($isFinanceRealm): ?>
      <section class="trip-view-section trip-view-section-finance">
        <div class="trip-view-section-title">Финансовые условия</div>

        <div class="trip-view-finance-columns">
          <div class="trip-view-finance-column">
            <div class="trip-view-finance-head">
              <span>Заказчик</span>
              <strong><?= e(LinearRouteService::formatAmount($customerTotal)) ?> ₽</strong>
            </div>
            <div class="trip-view-payment-list">
              <?php if ($customerPayments !== []): ?>
                <?php foreach ($customerPayments as $payment): ?>
                  <?php $renderPayment($payment); ?>
                <?php endforeach; ?>
              <?php else: ?>
              <div class="trip-view-empty">Оплаты не заданы</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="trip-view-finance-column">
            <div class="trip-view-finance-head">
              <span>Перевозчик</span>
              <strong><?= e(LinearRouteService::formatAmount($carrierTotal)) ?> ₽</strong>
            </div>
            <div class="trip-view-payment-list">
              <?php if ($carrierPayments !== []): ?>
                <?php foreach ($carrierPayments as $payment): ?>
                  <?php $renderPayment($payment); ?>
                <?php endforeach; ?>
              <?php else: ?>
              <div class="trip-view-empty">Оплаты не заданы</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if (($route['route_type'] ?? '') === LinearRouteService::ROUTE_TYPE_AGENCY): ?>
          <?php foreach (($route['principal_items'] ?? []) as $principal): ?>
            <?php $principalPayments = $route['payments']['principals'][(int) ($principal['id'] ?? 0)] ?? []; ?>
            <?php if ($principalPayments === []) continue; ?>
          <div class="trip-view-principal-finance">
            <div class="trip-view-finance-head">
              <span>Принципал · <?= e((string) ($principal['display_name'] ?? $na)) ?></span>
              <strong><?= e(LinearRouteService::formatAmount($paymentTotal($principalPayments))) ?> ₽</strong>
            </div>
            <div class="trip-view-payment-list trip-view-payment-list-principal">
              <?php foreach ($principalPayments as $payment): ?>
                <?php $renderPayment($payment); ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="trip-view-totals">
          <div>
            <span>Заказчик</span>
            <strong><?= e(LinearRouteService::formatAmount($customerTotal)) ?> ₽</strong>
          </div>
          <div>
            <span>Перевозчик</span>
            <strong><?= e(LinearRouteService::formatAmount($carrierTotal)) ?> ₽</strong>
          </div>
          <div class="trip-view-margin <?= $margin < 0 ? 'is-negative' : '' ?>">
            <span>Маржа</span>
            <strong><?= e(LinearRouteService::formatAmount($margin)) ?> ₽</strong>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($route['comments'])): ?>
      <section class="trip-view-section trip-view-section-comment">
        <div class="trip-view-section-title">Комментарий</div>
        <div class="trip-view-comment-text"><?= nl2br(e((string) $route['comments'])) ?></div>
      </section>
      <?php endif; ?>
    </div>

    <div class="driver-modal-docs trip-view-docs">
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