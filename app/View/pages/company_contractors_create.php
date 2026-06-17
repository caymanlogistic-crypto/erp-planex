<?php if ($company === null): ?>

<div class="notice warn">
    Компания не найдена. Укажите корректный company_id.
</div>

<?php elseif ($company['status'] !== 'active'): ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<div class="notice warn">
    Компания находится в статусе «<?= e($company['status']) ?>». Создание перевозчиков недоступно.
</div>

<?php elseif ($success): ?>

<div class="page-head">
    <div>
        <h1>Перевозчик создан</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-primary">← К списку</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="notice success">
            Перевозчик успешно создан.
        </div>

        <div class="kv mt-4">
            <div class="kv-row">
                <span class="kv-key">Наименование</span>
                <span class="kv-value"><?= e($createdContractor['name']) ?></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">ИНН</span>
                <span class="kv-value"><code><?= e($createdContractor['inn']) ?></code></span>
            </div>
            <div class="kv-row">
                <span class="kv-key">Статус</span>
                <span class="kv-value">Активен</span>
            </div>
        </div>

        <?php if (!empty($uploadedDocs)): ?>
        <div class="form-section mt-4">
            <h3 class="panel-head-title">Загруженные документы</h3>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Файл</th>
                            <th>Тип</th>
                            <th>Размер</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedDocs as $doc): ?>
                        <tr>
                            <td><?= e($doc['original_name']) ?></td>
                            <td><?= e($doc['mime_type']) ?></td>
                            <td><?= e(formatFileSize($doc['file_size'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($docErrors)): ?>
        <div class="notice warn mt-4">
            <p>Некоторые файлы не удалось загрузить:</p>
            <ul>
                <?php foreach ($docErrors as $de): ?>
                <li><?= e($de) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="form-actions mt-4">
            <a href="/company/contractors/<?= $createdContractor['id'] ?>" class="btn btn-primary">← К карточке перевозчика</a>
            <a href="/company/documents?entity_type=contractor&entity_id=<?= $createdContractor['id'] ?>" class="btn btn-ghost">Документы</a>
            <a href="/company/contractors/create" class="btn btn-ghost">Создать ещё</a>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-head">
    <div>
        <h1>Создать перевозчика</h1>
        <p class="text-muted">Компания: <?= e($company['name']) ?></p>
    </div>
    <div class="page-head-actions">
        <a href="/company/contractors" class="btn btn-ghost">← К списку</a>
    </div>
</div>

<?php if ($formError): ?>
    <div class="notice warn"><?= e($formError) ?></div>
<?php endif; ?>

<form method="post" action="/company/contractors/create" enctype="multipart/form-data" class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="panel-head-title">Основные данные</h3>

            <div class="field">
                <label class="field-label">Наименование <span class="req">*</span></label>
                <input type="text" name="name" class="field-input" required
                       value="<?= e($old['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-msg is-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">ИНН <span class="req">*</span></label>
                    <input type="text" name="inn" class="field-input"
                           value="<?= e($old['inn'] ?? '') ?>">
                    <?php if (!empty($errors['inn'])): ?>
                        <div class="field-msg is-error"><?= e($errors['inn']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label">КПП</label>
                    <input type="text" name="kpp" class="field-input"
                           value="<?= e($old['kpp'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">ОГРН</label>
                <input type="text" name="ogrn" class="field-input"
                       value="<?= e($old['ogrn'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Адреса</h3>

            <div class="field">
                <label class="field-label">Юридический адрес</label>
                <textarea name="legal_address" class="field-textarea" rows="2"><?= e($old['legal_address'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label">Фактический адрес</label>
                <textarea name="physical_address" class="field-textarea" rows="2"><?= e($old['physical_address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Контакты</h3>

            <div class="field">
                <label class="field-label">Контактное лицо</label>
                <input type="text" name="contact_person" class="field-input"
                       value="<?= e($old['contact_person'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Телефон</label>
                <input type="text" name="contact_phone" class="field-input"
                       value="<?= e($old['contact_phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="field-label">Email</label>
                <input type="email" name="contact_email" class="field-input"
                       value="<?= e($old['contact_email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Дополнительно</h3>

            <div class="field">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea" rows="3"><?= e($old['comments'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="panel-head-title">Документы перевозчика</h3>

            <div class="field">
                <label class="field-label">Тип документа</label>
                <input type="text" name="documents_type" class="field-input"
                       value="<?= e($old['documents_type'] ?? '') ?>"
                       placeholder="Например: Договор, Свидетельство, Устав">
            </div>

            <div class="field">
                <label class="field-label">Файлы</label>
                <input type="file" name="documents_file[]" class="field-input" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                <div class="field-msg">
                    Допустимые форматы: PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX. Максимальный размер: 20 МБ.
                </div>
                <?php if (!empty($errors['documents_file'])): ?>
                    <div class="field-msg is-error"><?= e($errors['documents_file']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать перевозчика</button>
            <a href="/company/contractors" class="btn btn-ghost">Отмена</a>
        </div>

    </div>
</form>

<?php endif; ?>
