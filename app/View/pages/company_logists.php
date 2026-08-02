<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Логисты</h1>
        <div class="page-summary"><span>Сотрудники компании с доступом к системе</span></div>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание пользователей недоступно.
</div>

<?php elseif (isset($dbError)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Логисты</h1>
        <div class="page-summary"><span>Сотрудники компании с доступом к системе</span></div>
    </div>
</div>

<div class="notice warn">
    <?= e($dbError) ?>
</div>

<?php else: ?><?php if (empty($logists)): ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Логисты</h1>
        <div class="page-summary"><span>Сотрудники компании с доступом к системе</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" data-company-user-create-modal>Создать пользователя</button>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <p class="empty-title">Логисты ещё не созданы.</p>
            <p class="empty-desc">Создайте пользователей и назначьте им роли для работы в системе.</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div class="page-head-left">
        <h1 class="page-title">Логисты</h1>
        <div class="page-summary"><span>Сотрудники компании с доступом к системе</span></div>
    </div>
    <div class="page-head-actions">
        <button type="button" class="btn btn-primary" data-company-user-create-modal>Создать пользователя</button>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logists as $l): ?>
                    <tr data-company-user-id="<?= (int)$l['id'] ?>">
                        <td class="cell-double">
                            <span class="cell-main"><?= e($l['full_name']) ?></span>
                            <span class="cell-sub">@<?= e($l['login']) ?></span>
                        </td>
                        <td>
                            <?php $rl = $l['role_code'] ?? 'logist'; ?>
                            <?php if ($rl === 'senior_logist'): ?>
                            <span class="badge">Логист+</span>
                            <?php else: ?>
                            <span class="badge badge-neutral">Логист</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['status'] === 'active'): ?>
                            <span class="badge badge-ok"><span class="dot"></span>Активен</span>
                            <?php elseif ($l['status'] === 'blocked'): ?>
                            <span class="badge"><span class="dot"></span>Заблокирован</span>
                            <?php elseif ($l['status'] === 'archived'): ?>

                            <?php else: ?>
                            <span class="badge"><span class="dot"></span><?= e($l['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-actions">
                            <div class="row-actions">
                                <a href="<?= app_url('/company/logists/' . $l['id']) ?>" class="btn btn-toolbar">Просмотр</a>
                                <a href="<?= app_url('/company/logists/' . $l['id'] . '/edit') ?>" class="btn btn-toolbar">Редактировать</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Company user view modal (ModalShell) -->
<div class="modal-overlay driver-view-overlay" id="company-user-view-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1">
  <div class="modal modal-lg user-view-modal-inner">
    <div class="modal-head">
      <span class="modal-title">Пользователь</span>
      <button type="button" class="modal-close" data-company-user-view-close>✕</button>
    </div>
    <div class="modal-body"><div class="driver-modal-loading">...</div></div>
  </div>
</div>

<!-- Company user create modal -->
<div class="modal-overlay" id="company-user-create-modal" data-close-on-overlay="0" data-close-on-escape="0" data-reset-on-close="1">
  <div class="modal modal-lg">
    <div class="modal-head">
      <span class="modal-title">Создать пользователя</span>
      <button type="button" class="modal-close" data-company-user-create-close>✕</button>
    </div>
    <?php
    $generatedPassword = generatePassword();
    $formError = null;
    $errors = [];
    $old = [];
    ?>
    <?php require base_path('app/View/partials/company_user_create_form.php'); ?>
  </div>
</div>

<?php endif; ?>
