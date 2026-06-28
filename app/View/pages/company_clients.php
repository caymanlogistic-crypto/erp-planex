<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание клиентов недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php elseif (empty($clients)): ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('le-client-modal')">Создать клиента</button>
    </div>
</div>

<div class="table-card table-card--toolbar-only">
    <div class="empty-state">
        <p class="empty-title">Клиенты ещё не созданы.</p>
        <a href="/company/clients/create" class="btn btn-primary">Создать первого клиента</a>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Клиенты</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('le-client-modal')">Создать клиента</button>
    </div>
</div>

<div class="table-card table-card--standard" data-erp-grid>
    <div class="table-toolbar">
        <div class="found-label">Найдено: <b><?= count($clients) ?></b> клиентов</div>
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
                    <th>ID</th>
                    <th>Название</th>
                    <th>ИНН</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr data-erp-sort-date="<?= $c['id'] ?>" data-client-id="<?= $c['id'] ?>">
                    <td class="col-mono"><?= $c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="col-mono"><?= e($c['inn']) ?></td>
                    <td>
                        <?php if ($c['status'] === 'active'): ?>
                        <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                        <?php elseif ($c['status'] === 'inactive'): ?>
                        <span class="badge"><span class="dot"></span>Неактивен</span>
                        <?php elseif ($c['status'] === 'archived'): ?>
                        <span class="badge badge-warn"><span class="dot"></span>Архив</span>
                        <?php else: ?>
                        <span class="badge"><span class="dot"></span><?= e($c['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="col-muted"><?= e($c['created_at']) ?></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <a href="/company/clients/<?= $c['id'] ?>" class="btn btn-toolbar">Просмотр</a>
                            <a href="/company/clients/<?= $c['id'] ?>/edit" class="btn btn-toolbar">Редактировать</a>
                            <a href="/company/documents?entity_type=client&entity_id=<?= $c['id'] ?>" class="btn btn-toolbar">Документы</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span class="footer-label">Показано <b class="footer-range">1–<?= count($clients) ?></b> из <b class="footer-total"><?= count($clients) ?></b></span>
    </div>
</div>

<?php endif; ?>

<div class="modal-overlay driver-view-overlay" id="le-client-modal" data-close-on-overlay="0" data-close-on-escape="0">
  <div class="modal modal-lg driver-view-modal-inner">
    <div class="modal-head">
      <span class="modal-title">Создать клиента</span>
      <button type="button" class="modal-close" onclick="closeModal('le-client-modal')">&times;</button>
    </div>
    <div class="modal-body" id="le-client-modal-body">
      <div class="driver-modal-loading">Загрузка...</div>
    </div>
  </div>
</div>
<script>
(function initClientLegalEntityModal() {
    var config = { modalId: 'le-client-modal', formAction: '/company/clients/create', typeField: 'entity_type' };
    if (window.LegalEntityModal && typeof window.LegalEntityModal.init === 'function') {
        window.LegalEntityModal.init(config);
        return;
    }

    window.addEventListener('load', function () {
        if (window.LegalEntityModal && typeof window.LegalEntityModal.init === 'function') {
            window.LegalEntityModal.init(config);
        }
    }, { once: true });
})();
</script>
