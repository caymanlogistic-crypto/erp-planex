<?php
$predefDocs = $predefDocs ?? [
    ['name' => 'Паспорт',                  'code' => 'passport',       'ext' => 'PDF'],
    ['name' => 'Водительское удостоверение', 'code' => 'driver_license', 'ext' => 'PDF'],
    ['name' => 'СНИЛС',                     'code' => 'snils',          'ext' => 'PDF'],
];
$formError = $formError ?? null;
$errors = $errors ?? [];
$old = $old ?? [];
$docTypes = $docTypes ?? [];
$driverCreateFormMode = $driverCreateFormMode ?? 'page';
$driverFormId = $driverFormId ?? 'driver-create-form';
$driverFormAction = $driverFormAction ?? '/company/drivers/create';
$driverFormShowDocs = $driverFormShowDocs ?? true;
$driverFormDocContent = $driverFormDocContent ?? null;
$driverExistingDocsByCode = $driverExistingDocsByCode ?? [];
$domPrefix = ($driverCreateFormMode === 'edit') ? ($driverFormDomPrefix ?? $driverFormId) : '';
$driverFormClass = $driverFormClass ?? 'panel';
?>
<form id="<?= $driverFormId ?>" method="post" action="<?= $driverFormAction ?>" class="<?= $driverFormClass ?>" enctype="multipart/form-data">
<script type="application/json" data-doc-types><?= json_encode($docTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<div class="entity-form-layout driver-layout">

    <!-- ════ Левая колонка: данные водителя ════ -->
    <div class="entity-form-main driver-layout-main">

        <?php if ($driverCreateFormMode === 'page'): ?>
        <div class="section-title">Данные водителя</div>
        <?php endif; ?>

        <?php if ($formError): ?>
        <div class="form-alert alert-error">
            <div class="alert-mark">
                <svg width="11" height="11" viewBox="0 0 18 18" fill="none"><path d="M9 2L16.5 15H1.5L9 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 7V11M9 13V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </div>
            <div class="alert-body">
                <div class="alert-body-title">Ошибка при сохранении</div>
                <div class="alert-body-sub"><?= e($formError) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="driver-fields">

            <!-- ФИО + Телефон + кнопка (одна строка, 3 колонки) -->
            <div class="driver-contact-top-row">
                <div class="field field-w-name<?= !empty($errors['full_name']) ? ' is-error' : '' ?>">
                    <label class="field-label">ФИО <span class="req">*</span></label>
                    <input type="text" name="full_name" class="field-input"
                           placeholder="Фамилия Имя Отчество"
                           value="<?= e($old['full_name'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['full_name']) ? e($errors['full_name']) : '' ?></div>
                </div>

                <div class="driver-contact-stack">
                    <div class="driver-contact-main-row">
                        <div class="field field-w-phone<?= !empty($errors['phone']) ? ' is-error' : '' ?>">
                            <label class="field-label">Телефон</label>
                            <input type="text" name="phone" class="field-input"
                                   placeholder="+7 900 000-00-00"
                                   value="<?= e($old['phone'] ?? '') ?>">
                            <div class="field-msg"><?= !empty($errors['phone']) ? e($errors['phone']) : '' ?></div>
                        </div>

                        <button type="button" class="btn btn-ghost phone-add-btn" id="<?= e($domPrefix) ?>add-extra-phone-btn" data-add-extra-phone-btn>+ Доп. телефон</button>
                    </div>

                    <div class="driver-extra-phones" id="<?= e($domPrefix) ?>extra-phones-container" data-extra-phones-container>
                        <?php if (!empty($old['extra_phones']) && is_array($old['extra_phones'])): ?>
                            <?php foreach ($old['extra_phones'] as $idx => $ep): ?>
                            <div class="driver-extra-phone-row">
                                <div class="field driver-extra-phone-field">
                                    <input type="text" name="extra_phones[]" class="field-input"
                                           placeholder="+7 900 000-00-00"
                                           value="<?= e($ep) ?>">
                                    <div class="field-msg"></div>
                                </div>
                                <div class="driver-extra-phone-comment-wrap">
                                    <input type="text" name="extra_phone_comments[]" class="field-input"
                                           placeholder="Комментарий к телефону"
                                           value="<?= e($old['extra_phone_comments'][$idx] ?? '') ?>">
                                    <button type="button" class="driver-extra-phone-remove" title="Удалить">×</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Паспорт: серия + код + кем выдан + дата -->
            <div class="field-row field-row-group">
                <div class="field field-w-passport<?= !empty($errors['passport_number']) ? ' is-error' : '' ?>">
                    <label class="field-label">Серия и номер</label>
                    <input type="text" name="passport_number" class="field-input"
                           placeholder="0000 000000"
                           value="<?= e($old['passport_number'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['passport_number']) ? e($errors['passport_number']) : '' ?></div>
                </div>

                <div class="field field-w-code<?= !empty($errors['passport_department_code']) ? ' is-error' : '' ?>">
                    <label class="field-label">Код подразделения</label>
                    <input type="text" name="passport_department_code" class="field-input"
                           placeholder="000-000"
                           value="<?= e($old['passport_department_code'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['passport_department_code']) ? e($errors['passport_department_code']) : '' ?></div>
                </div>

                <div class="field field-w-issued-by">
                    <label class="field-label">Кем выдан</label>
                    <input type="text" name="passport_issued_by" class="field-input"
                           placeholder="Орган, выдавший паспорт"
                           value="<?= e($old['passport_issued_by'] ?? '') ?>">
                    <div class="field-msg"></div>
                </div>

                <div class="field field-w-date<?= !empty($errors['passport_issue_date']) ? ' is-error' : '' ?>">
                    <label class="field-label">Дата выдачи</label>
                    <input type="text" name="passport_issue_date" class="field-input js-erp-date-picker"
                           placeholder="дд.мм.гггг"
                           inputmode="numeric"
                           autocomplete="off"
                           value="<?= e($old['passport_issue_date'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['passport_issue_date']) ? e($errors['passport_issue_date']) : '' ?></div>
                </div>
            </div>

            <!-- ВУ + СНИЛС + Email -->
            <div class="field-row field-row-group">
                <div class="field field-w-license<?= !empty($errors['license_number']) ? ' is-error' : '' ?>">
                    <label class="field-label">Номер ВУ</label>
                    <input type="text" name="license_number" class="field-input"
                           placeholder="0000 000000"
                           value="<?= e($old['license_number'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['license_number']) ? e($errors['license_number']) : '' ?></div>
                </div>

                <div class="field field-w-date<?= !empty($errors['license_issue_date']) ? ' is-error' : '' ?>">
                    <label class="field-label">Дата выдачи ВУ</label>
                    <input type="text" name="license_issue_date" class="field-input js-erp-date-picker"
                           placeholder="дд.мм.гггг"
                           inputmode="numeric"
                           autocomplete="off"
                           value="<?= e($old['license_issue_date'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['license_issue_date']) ? e($errors['license_issue_date']) : '' ?></div>
                </div>

                <div class="field field-w-snils<?= !empty($errors['snils']) ? ' is-error' : '' ?>">
                    <label class="field-label">СНИЛС</label>
                    <input type="text" name="snils" class="field-input"
                           placeholder="000-000-000 00"
                           value="<?= e($old['snils'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['snils']) ? e($errors['snils']) : '' ?></div>
                </div>

                <div class="field field-w-email field-grow<?= !empty($errors['email']) ? ' is-error' : '' ?>">
                    <label class="field-label">Email</label>
                    <input type="text" name="email" class="field-input"
                           placeholder="driver@example.ru"
                           value="<?= e($old['email'] ?? '') ?>">
                    <div class="field-msg"><?= !empty($errors['email']) ? e($errors['email']) : '' ?></div>
                </div>
            </div>

            <!-- Комментарий -->
            <div class="field field-w-comment field-row-group">
                <label class="field-label">Комментарий</label>
                <textarea name="comments" class="field-textarea driver-textarea"
                          placeholder="Примечания по водителю..."><?= e($old['comments'] ?? '') ?></textarea>
                <div class="field-msg"></div>
            </div>

        </div><!-- /.driver-fields -->

        <!-- Action footer — visible only on standalone page, hidden in modal -->
        <?php if ($driverCreateFormMode === 'page'): ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать водителя</button>
        </div>
        <?php endif; ?>

    </div><!-- /.driver-layout-main -->

    <?php if ($driverFormShowDocs): ?>
    <!-- ════ Правая колонка: документы ════ -->
    <div class="entity-form-docs driver-layout-docs">

        <?php if ($driverFormDocContent !== null): ?>
            <?= $driverFormDocContent ?>
        <?php else: ?>
        <div>
            <div class="section-title">Документы</div>
            <?php if ($driverCreateFormMode === 'page'): ?>
            <div class="field-msg">Документы можно загрузить сейчас или позже в карточке водителя</div>
            <?php endif; ?>
        </div>

        <div class="file-list">
            <?php foreach ($predefDocs as $pdoc):
                $code = $pdoc['code'];
                $existingDocs = $driverExistingDocsByCode[$code] ?? [];
                $hasExisting = !empty($existingDocs);
                $isEdit = ($driverCreateFormMode === 'edit');

                if ($isEdit && $hasExisting) {
                    // Existing document — show file info
                    $existingCount = count($existingDocs);
                    $firstName = $existingDocs[0]['original_name'] ?? $existingDocs[0]['stored_name'] ?? '';
                    $firstMime = $existingDocs[0]['mime_type'] ?? '';
                    $metaText = $existingCount > 1
                        ? 'Выбрано файлов: ' . $existingCount
                        : ($firstName ?: 'Файл');
                    $badgeCls = 'file-type-badge';
                    $badgeTxt = '—';
                    // Determine badge from extension
                    $parts = explode('.', $firstName);
                    $ext = count($parts) > 1 ? strtoupper(end($parts)) : '';
                    if (empty($ext)) {
                        if (strpos($firstMime, 'pdf') !== false) $ext = 'PDF';
                        elseif (strpos($firstMime, 'image') !== false) $ext = 'IMG';
                    }
                    if ($ext === 'PDF')      { $badgeCls .= ' is-pdf'; $badgeTxt = 'PDF'; }
                    elseif ($ext === 'DOC' || $ext === 'DOCX' || $ext === 'RTF' || $ext === 'ODT') { $badgeCls .= ' is-doc'; $badgeTxt = 'DOC'; }
                    elseif ($ext === 'XLS' || $ext === 'XLSX' || $ext === 'CSV' || $ext === 'ODS') { $badgeCls .= ' is-xls'; $badgeTxt = 'XLS'; }
                    elseif (in_array($ext, ['JPG','JPEG','PNG','WEBP','GIF','BMP','TIF','TIFF','HEIC','HEIF'])) { $badgeCls .= ' is-img'; $badgeTxt = 'IMG'; }
                    else { $badgeCls .= ' is-other'; $badgeTxt = $ext ?: 'FILE'; }
                    $rowCls = 'file-item file-item-predef document-file-row has-file has-existing-file';
                    $buttonText = 'Заменить';
                    $clearCls = 'predef-file-clear';
                } else {
                    // No existing document — neutral state
                    $metaText = 'Файл не выбран';
                    $badgeCls = 'file-type-badge file-type-badge-empty';
                    $badgeTxt = '—';
                    $rowCls = 'file-item file-item-predef document-file-row is-empty';
                    $buttonText = 'Выбрать';
                    $clearCls = 'predef-file-clear is-hidden';
                }
            ?>
            <div class="<?= $rowCls ?>" id="<?= e($domPrefix) ?>frow-<?= $code ?>"
                 data-file-row="predef"
                 data-file-code="<?= $code ?>"
                 <?php if ($isEdit && $hasExisting): ?>
                 data-has-existing="1"
                 data-existing-badge-class="<?= e($badgeCls) ?>"
                 data-existing-badge-text="<?= e($badgeTxt) ?>"
                 data-existing-meta="<?= e($metaText) ?>"
                 data-existing-button-text="<?= e($buttonText) ?>"
                 <?php endif; ?>>
                <div class="<?= $badgeCls ?>" id="<?= e($domPrefix) ?>fbadge-<?= $code ?>"><?= $badgeTxt ?></div>
                <div class="file-info">
                    <div class="file-name"><?= e($pdoc['name']) ?></div>
                    <div class="file-meta" id="<?= e($domPrefix) ?>fname-<?= $code ?>"><?= e($metaText) ?></div>
                </div>
                <button type="button"
                        class="btn btn-secondary file-action-btn js-file-pick-btn"
                        data-file-input="<?= e($domPrefix) ?>predef-file-<?= $code ?>">
                    <span id="<?= e($domPrefix) ?>fbtn-<?= $code ?>"><?= $buttonText ?></span>
                </button>
                <button type="button"
                        class="<?= $clearCls ?>"
                        id="<?= e($domPrefix) ?>fclear-<?= $code ?>"
                        title="Очистить файл">×</button>
                <input type="file"
                       id="<?= e($domPrefix) ?>predef-file-<?= $code ?>"
                       class="file-input-hidden js-predef-file-input"
                       name="predef_doc[<?= $code ?>][]"
                       multiple
                       data-label="<?= e($domPrefix) ?>fname-<?= $code ?>"
                       data-badge="<?= e($domPrefix) ?>fbadge-<?= $code ?>"
                       data-button-label="<?= e($domPrefix) ?>fbtn-<?= $code ?>"
                       data-clear="<?= e($domPrefix) ?>fclear-<?= $code ?>">
                <input type="hidden" name="delete_predef_doc[<?= $code ?>]" value="0" data-delete-predef-doc>
                <input type="hidden" name="predef_doc_type[<?= $code ?>]" value="<?= e($pdoc['name']) ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-section">
            <?php if ($driverCreateFormMode === 'page'): ?>
            <div class="section-title">Произвольные документы</div>
            <div class="field-msg docs-section-hint">
                Введите название документа и выберите файл
            </div>
            <?php endif; ?>
            <div id="<?= e($domPrefix) ?>custom-docs-container" class="file-list" data-custom-docs-container></div>
            <button type="button" class="btn btn-ghost" id="<?= e($domPrefix) ?>add-custom-doc-btn" data-add-custom-doc-btn>+ Добавить документ</button>
        </div>
        <?php endif; ?>

    </div><!-- /.driver-layout-docs -->
    <?php endif; ?>

</div><!-- /.driver-layout -->
</form>

<script>
(function () {
    var form = document.getElementById('<?= $driverFormId ?>');
    if (form && window.initDriverForm) {
        window.initDriverForm(form);
    }
})();
</script>
