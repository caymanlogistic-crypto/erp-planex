<?php if (!empty($formError)): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="<?= app_url('/superadmin/companies/' . (int)$company['id'] . '/create-owner') ?>" class="panel" id="owner-create-form">
    <div class="panel-body">
        <input type="hidden" name="is_modal" value="<?= !empty($isModal) ? '1' : '0' ?>">

        <div class="form-section">
            <p class="section-title">Основные данные</p>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">ФИО <span class="req">*</span></label>
                    <input type="text" name="full_name" class="field-input" required
                           value="<?= e($old['full_name'] ?? '') ?>"
                           placeholder="Иванов Иван Иванович">
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="field-msg is-error"><?= e($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">Логин <span class="req">*</span></label>
                    <input type="text" name="login" class="field-input" required
                           value="<?= e($old['login'] ?? '') ?>"
                           placeholder="Латинские буквы, цифры, подчёркивание">
                    <?php if (!empty($errors['login'])): ?>
                        <div class="field-msg is-error"><?= e($errors['login']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">Должность</label>
                    <input type="text" name="position" class="field-input"
                           value="<?= e($old['position'] ?? '') ?>"
                           placeholder="Генеральный директор">
                </div>

                <div class="field">
                    <label class="field-label">Пароль</label>
                    <div class="field-inline-group">
                        <input type="password" name="password" class="field-input field-inline-grow code-hi"
                               data-owner-password-field
                               value="<?= e($old['password'] ?? $generatedPassword ?? '') ?>"
                               placeholder="Оставьте пустым для автогенерации">
                        <button type="button" class="btn btn-ghost btn-sm" data-owner-password-toggle title="Показать/скрыть пароль">Показать</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-owner-password-copy title="Копировать пароль">Копировать</button>
                        <button type="button" class="btn btn-toolbar btn-nowrap" data-owner-password-generate>Сгенерировать</button>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-msg is-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Контакты (опционально)</p>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="field-input"
                           value="<?= e($old['email'] ?? '') ?>">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-msg is-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">Телефон</label>
                    <input type="text" name="phone" class="field-input"
                           value="<?= e($old['phone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <p class="section-title">Дополнительно</p>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</form>
