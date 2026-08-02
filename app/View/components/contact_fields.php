<?php

if (!function_exists('contactFieldsDefaultRows')) {
    function contactFieldsDefaultRows(): array
    {
        return [[
            'contact_person' => '',
            'phone' => '',
            'email' => '',
            'comment' => '',
            'is_primary' => '1',
            'is_document_email' => '0',
        ]];
    }
}

if (!function_exists('renderContactFields')) {
    function renderContactFields(array $options = []): void
    {
        $entityType = (string) ($options['entity_type'] ?? 'contractor');
        $fieldPrefix = (string) ($options['field_prefix'] ?? 'contacts');
        $contacts = $options['contacts'] ?? [];
        $errors = $options['errors'] ?? [];
        $allowPrimary = array_key_exists('allow_primary', $options) ? (bool) $options['allow_primary'] : true;
        $allowDocumentEmail = array_key_exists('allow_document_email', $options) ? (bool) $options['allow_document_email'] : true;

        $contacts = is_array($contacts) && $contacts !== [] ? $contacts : contactFieldsDefaultRows();

        $rootClass = $entityType . '-contacts';
        $rowClass = $entityType . '-contact-row';
        $removeClass = $entityType . '-contact-remove';
        $listAttr = 'data-' . $entityType . '-contacts-list';
        ?>
        <div class="<?= e($rootClass) ?>" data-contact-fields data-entity-type="<?= e($entityType) ?>" data-field-prefix="<?= e($fieldPrefix) ?>" data-allow-primary="<?= $allowPrimary ? '1' : '0' ?>" data-allow-document-email="<?= $allowDocumentEmail ? '1' : '0' ?>">
            <div class="<?= e($rootClass) ?>-list" <?= e($listAttr) ?>>
                <?php foreach ($contacts as $index => $contact): ?>
                    <?php $rowErrors = is_array($errors[$index] ?? null) ? $errors[$index] : []; ?>
                    <div class="<?= e($rowClass) ?>" data-contact-row>
                        <div class="contact-cell-wrap contact-person-cell<?= !empty($rowErrors['contact_person']) ? ' is-error' : '' ?>">
                        <input type="text"
                               name="<?= e($fieldPrefix) ?>[<?= $index ?>][contact_person]"
                               class="field-input<?= !empty($rowErrors['contact_person']) ? ' is-error' : '' ?>"
                               placeholder="Контактное лицо"
                               value="<?= e($contact['contact_person'] ?? '') ?>"
                               data-contact-person>
                        <div class="contact-error-msg" data-contact-error><?= e($rowErrors['contact_person'] ?? '') ?></div>
                    </div>

                    <div class="contact-cell-wrap contact-phone-cell<?= !empty($rowErrors['phone']) ? ' is-error' : '' ?>">
                        <input type="text"
                               name="<?= e($fieldPrefix) ?>[<?= $index ?>][phone]"
                               class="field-input<?= !empty($rowErrors['phone']) ? ' is-error' : '' ?>"
                               placeholder="Телефон"
                               value="<?= e($contact['phone'] ?? '') ?>"
                               data-contact-phone>
                        <div class="contact-error-msg" data-contact-error><?= e($rowErrors['phone'] ?? '') ?></div>
                    </div>

                    <div class="contact-cell-wrap contact-email-cell<?= !empty($rowErrors['email']) ? ' is-error' : '' ?>">
                        <input type="text"
                               name="<?= e($fieldPrefix) ?>[<?= $index ?>][email]"
                               class="field-input<?= !empty($rowErrors['email']) ? ' is-error' : '' ?>"
                               placeholder="Email"
                               value="<?= e($contact['email'] ?? '') ?>"
                               data-contact-email>
                        <div class="contact-error-msg" data-contact-error><?= e($rowErrors['email'] ?? '') ?></div>
                    </div>

                    <div class="contact-cell-wrap contact-comment-cell<?= !empty($rowErrors['comment']) ? ' is-error' : '' ?>">
                        <input type="text"
                               name="<?= e($fieldPrefix) ?>[<?= $index ?>][comment]"
                               class="field-input<?= !empty($rowErrors['comment']) ? ' is-error' : '' ?>"
                               placeholder="Комментарий"
                               value="<?= e($contact['comment'] ?? '') ?>"
                               data-contact-comment>
                        <div class="contact-error-msg" data-contact-error><?= e($rowErrors['comment'] ?? '') ?></div>
                    </div>

                    <button type="button" class="file-remove <?= e($removeClass) ?>" data-remove-contact aria-label="Удалить контакт">&times;</button>

                    <div class="contact-checkboxes">
                        <?php if ($allowPrimary): ?>
                        <label class="contact-checkbox-item checkbox-item">
                            <input type="checkbox"
                                   name="<?= e($fieldPrefix) ?>[<?= $index ?>][is_primary]"
                                   value="1"
                                   <?= !empty($contact['is_primary']) ? 'checked' : '' ?>
                                   data-contact-primary>
                            <span class="checkbox-mark"></span>
                            <span class="contact-checkbox-label">Основной контакт</span>
                        </label>
                        <?php endif; ?>
                        <?php if ($allowDocumentEmail): ?>
                        <label class="contact-checkbox-item checkbox-item">
                            <input type="checkbox"
                                   name="<?= e($fieldPrefix) ?>[<?= $index ?>][is_document_email]"
                                   value="1"
                                   <?= !empty($contact['is_document_email']) ? 'checked' : '' ?>
                                   data-contact-document-email>
                            <span class="checkbox-mark"></span>
                            <span class="contact-checkbox-label">Для документов</span>
                        </label>
                        <?php endif; ?>
                    </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-ghost contact-add-btn" data-add-contact>+ Добавить контакт</button>
        </div>

        <template data-contact-template>
            <div class="<?= e($rowClass) ?>" data-contact-row>
                <div class="contact-cell-wrap contact-person-cell">
                    <input type="text" class="field-input" placeholder="Контактное лицо" data-contact-person>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-phone-cell">
                    <input type="text" class="field-input" placeholder="Телефон" data-contact-phone>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-email-cell">
                    <input type="text" class="field-input" placeholder="Email" data-contact-email>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-comment-cell">
                    <input type="text" class="field-input" placeholder="Комментарий" data-contact-comment>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <button type="button" class="file-remove <?= e($removeClass) ?>" data-remove-contact aria-label="Удалить контакт">&times;</button>

                <div class="contact-checkboxes">
                    <?php if ($allowPrimary): ?>
                    <label class="contact-checkbox-item checkbox-item">
                        <input type="checkbox" value="1" data-contact-primary>
                        <span class="checkbox-mark"></span>
                        <span class="contact-checkbox-label">Основной контакт</span>
                    </label>
                    <?php endif; ?>
                    <?php if ($allowDocumentEmail): ?>
                    <label class="contact-checkbox-item checkbox-item">
                        <input type="checkbox" value="1" data-contact-document-email>
                        <span class="checkbox-mark"></span>
                        <span class="contact-checkbox-label">Для документов</span>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </template>
        <?php
    }
}
