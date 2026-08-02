<?php
$cancelModalId = $cancelModalId ?? 'finance-cancel-modal';
$cancelActionUrl = $cancelActionUrl ?? '';
$cancelEntityLabel = $cancelEntityLabel ?? 'запись';
?>
<div id="<?= e($cancelModalId) ?>" class="modal-overlay" role="dialog" aria-modal="true" data-close-on-overlay="0" data-close-on-escape="0">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title">Отмена <?= e($cancelEntityLabel) ?></span>
            <button type="button" class="modal-close" data-close-modal="<?= e($cancelModalId) ?>">&times;</button>
        </div>
        <form action="<?= e($cancelActionUrl) ?>" method="post" class="finance-cancel-form" id="cancel-form-<?= e($cancelModalId) ?>">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-alert alert-warn">
                    <div class="alert-mark">
                        <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </div>
                    <div class="alert-body">
                        <div class="alert-body-title">Подтверждение отмены</div>
                        <div class="alert-body-sub">Вы уверены, что хотите отменить <?= e($cancelEntityLabel) ?>?</div>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="cancel-reason-<?= e($cancelModalId) ?>">Причина отмены <span class="req">*</span></label>
                    <textarea id="cancel-reason-<?= e($cancelModalId) ?>" name="reason" class="field-input" rows="3" placeholder="Укажите причину отмены" required></textarea>
                    <div class="field-msg"></div>
                </div>
            </div>
            <div class="modal-foot is-spaced">
                <div class="modal-foot-actions">
                    <button type="button" class="btn btn-ghost" data-close-modal="<?= e($cancelModalId) ?>">Отмена</button>
                </div>
                <div class="modal-foot-actions">
                    <button type="submit" class="btn btn-primary btn-danger">Подтвердить отмену</button>
                </div>
            </div>
        </form>
    </div>
</div>
