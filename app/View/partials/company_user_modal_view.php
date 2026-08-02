<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<div class="modal-body">
<div class="user-view-card">
<div class="user-view-details">
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">ФИО</div>
        <div class="user-view-value"><?= e($user['full_name']) ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Логин</div>
        <div class="user-view-value"><code><?= e($user['login']) ?></code></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Email</div>
        <div class="user-view-value"><?= e($user['email'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Телефон</div>
        <div class="user-view-value"><?= e($user['phone'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Роль</div>
        <div class="user-view-value"><?php $rc = $user['role_code'] ?? 'logist'; echo e($rc === 'logist' ? 'Логист' : ($rc === 'senior_logist' ? 'Логист+' : $rc)) ?></div>
    </div>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">Статус</div>
        <div class="user-view-value"><?= renderStatusBadge($user['status']) ?></div>
    </div>
</div>
</div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions">
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-primary" data-company-user-edit-btn>Редактировать</button>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost" data-company-user-view-close-btn>Закрыть</button>
    </div>
</div>
