<?php

require_once __DIR__ . '/../components/status_badge.php';

?>
<div class="modal-body">
<div class="user-view-card">
<div class="user-view-details">
    <?php if ($user['role'] === 'company_owner'): ?>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">ФИО</div>
        <div class="user-view-value"><?= e($user['full_name']) ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Логин</div>
        <div class="user-view-value"><code><?= e($user['login']) ?></code></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Должность</div>
        <div class="user-view-value"><?= e($user['position'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Email</div>
        <div class="user-view-value"><?= e($user['email'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Телефон</div>
        <div class="user-view-value"><?= e($user['phone'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">Роль</div>
        <div class="user-view-value">Руководитель</div>
    </div>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">Статус</div>
        <div class="user-view-value"><?= renderStatusBadge($user['status']) ?></div>
    </div>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">Комментарий</div>
        <div class="user-view-value"><?= e($user['comments'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Создан</div>
        <div class="user-view-value"><?= e($user['created_at'] ?? '') ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Обновлён</div>
        <div class="user-view-value"><?= e($user['updated_at'] ?? '') ?></div>
    </div>
    <?php else: ?>
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
        <div class="user-view-value"><?= ($user['role'] ?? 'logist') === 'senior_logist' ? 'Логист+' : 'Логист' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Статус</div>
        <div class="user-view-value"><?= renderStatusBadge($user['status']) ?></div>
    </div>
    <div class="user-view-item user-view-item--wide">
        <div class="user-view-label">Комментарий</div>
        <div class="user-view-value"><?= e($user['comments'] ?? '') ?: '—' ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Создан</div>
        <div class="user-view-value"><?= e($user['created_at'] ?? '') ?></div>
    </div>
    <div class="user-view-item">
        <div class="user-view-label">Обновлён</div>
        <div class="user-view-value"><?= e($user['updated_at'] ?? '') ?></div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-foot-actions">
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-primary" data-user-edit-btn>Редактировать</button>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost" data-user-view-close-btn>Закрыть</button>
    </div>
</div>
