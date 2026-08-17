<?php $fmt = static fn($v) => \App\Service\FinanceEmployeePaymentService::formatMoney($v); ?>
<div class="table-card table-card--standard employee-personal-expense-card" style="margin-top:14px;">
    <div class="table-toolbar">
        <div class="table-toolbar-left"><strong>Расходы, оплаченные сотрудником</strong></div>
        <div class="table-toolbar-right"><span class="text-muted">Связано с Основной кассой</span></div>
    </div>
    <?php if (empty($events)): ?>
        <div class="empty-state" style="padding:18px;"><p class="empty-title">Операций нет.</p><p class="empty-desc">Расходы компании из личных средств этого сотрудника ещё не проводились.</p></div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Дата</th><th>Получатель / назначение</th><th>ЦФУ</th><th>Статья ДДС</th><th>Рейс</th><th class="text-right">Сумма</th><th>Статус</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($events as $row): $cancelled = ($row['status'] ?? '') === 'CANCELLED'; ?>
                    <tr class="<?= $cancelled ? 'is-muted' : '' ?>">
                        <td><?= e(date('d.m.Y', strtotime((string)$row['operation_date']))) ?></td>
                        <td><strong><?= e((string)($row['counterparty_name'] ?: $row['purpose'])) ?></strong><?php if (!empty($row['counterparty_name']) && !empty($row['purpose'])): ?><div class="text-muted"><?= e($row['purpose']) ?></div><?php endif; ?></td>
                        <td><?= e((string)($row['cfu_name'] ?? '—')) ?></td>
                        <td><?= e((string)($row['dds_name'] ?? '—')) ?></td>
                        <td><?= !empty($row['linear_route_id']) ? 'Рейс #' . (int)$row['linear_route_id'] : '—' ?></td>
                        <td class="col-mono text-right">−<?= e($fmt($row['amount'])) ?> ₽</td>
                        <td><?= $cancelled ? '<span class="badge badge-neutral">Отменена</span>' : '<span class="badge badge-ok">Проведена</span>' ?></td>
                        <td class="text-right"><?php if (!$cancelled): ?><button type="button" class="btn btn-ghost btn--sm" data-personal-expense-edit="<?= (int)$row['id'] ?>">Изменить</button><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
