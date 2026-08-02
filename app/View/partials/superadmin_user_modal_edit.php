<div class="modal-body">
<div class="user-edit-modal-content">
<?php require base_path('app/View/partials/superadmin_user_edit_form.php'); ?>
</div>
</div>
<div class="modal-foot is-spaced">
    <div class="modal-required-note"><span class="req">*</span> — обязательные поля</div>
    <div class="modal-foot-actions">
        <button type="button" class="btn btn-ghost" data-user-cancel-edit-btn>Отмена</button>
        <button type="submit" form="user-edit-form" class="btn btn-primary">Сохранить</button>
    </div>
</div>
