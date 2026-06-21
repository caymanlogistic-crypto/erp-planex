<?php

if (!function_exists('renderContractorContactFields')) {
    function renderContractorContactFields(array $contacts, array $errors = []): void
    {
        $contacts = $contacts !== [] ? $contacts : [[
            'contact_person' => '',
            'phone' => '',
            'email' => '',
            'comment' => '',
            'is_primary' => '1',
            'is_document_email' => '0',
        ]];
        ?>
        <div class="contractor-contacts" data-contractor-contacts>
            <div class="contractor-contacts-list" data-contractor-contacts-list>
                <?php foreach ($contacts as $index => $contact): ?>
                    <?php $rowErrors = $errors[$index] ?? []; ?>
                    <div class="contractor-contact-row" data-contact-row>

                        <div class="contact-cell-wrap contact-person-cell<?= !empty($rowErrors['contact_person']) ? ' is-error' : '' ?>">
                            <div class="contact-input-suffix<?= !empty($rowErrors['contact_person']) ? ' is-error' : '' ?>">
                                <input type="text"
                                       name="contacts[<?= $index ?>][contact_person]"
                                       class="field-input"
                                       placeholder="Контактное лицо"
                                       value="<?= e($contact['contact_person'] ?? '') ?>"
                                       data-contact-person>
                                <label class="checkbox-item contact-inline-check" title="Сделать основным контактом" aria-label="Сделать основным контактом">
                                    <input type="checkbox"
                                           name="contacts[<?= $index ?>][is_primary]"
                                           value="1"
                                           <?= !empty($contact['is_primary']) ? 'checked' : '' ?>
                                           data-contact-primary>
                                    <span class="checkbox-mark"></span>
                                </label>
                            </div>
                            <div class="contact-error-msg" data-contact-error><?= e($rowErrors['contact_person'] ?? '') ?></div>
                        </div>

                        <div class="contact-cell-wrap contact-phone-cell<?= !empty($rowErrors['phone']) ? ' is-error' : '' ?>">
                            <input type="text"
                                   name="contacts[<?= $index ?>][phone]"
                                   class="field-input<?= !empty($rowErrors['phone']) ? ' is-error' : '' ?>"
                                   placeholder="Телефон"
                                   value="<?= e($contact['phone'] ?? '') ?>"
                                   data-contact-phone>
                            <div class="contact-error-msg" data-contact-error><?= e($rowErrors['phone'] ?? '') ?></div>
                        </div>

                        <div class="contact-cell-wrap contact-email-cell<?= !empty($rowErrors['email']) ? ' is-error' : '' ?>">
                            <div class="contact-input-suffix<?= !empty($rowErrors['email']) ? ' is-error' : '' ?>">
                                <input type="text"
                                       name="contacts[<?= $index ?>][email]"
                                       class="field-input"
                                       placeholder="Email"
                                       value="<?= e($contact['email'] ?? '') ?>"
                                       data-contact-email>
                                <label class="checkbox-item contact-inline-check" title="Email для официальной переписки" aria-label="Email для официальной переписки">
                                    <input type="checkbox"
                                           name="contacts[<?= $index ?>][is_document_email]"
                                           value="1"
                                           <?= !empty($contact['is_document_email']) ? 'checked' : '' ?>
                                           data-contact-document-email>
                                    <span class="checkbox-mark"></span>
                                </label>
                            </div>
                            <div class="contact-error-msg" data-contact-error><?= e($rowErrors['email'] ?? '') ?></div>
                        </div>

                        <div class="contact-cell-wrap contact-comment-cell<?= !empty($rowErrors['comment']) ? ' is-error' : '' ?>">
                            <input type="text"
                                   name="contacts[<?= $index ?>][comment]"
                                   class="field-input contact-comment-cell"
                                   placeholder="Комментарий"
                                   value="<?= e($contact['comment'] ?? '') ?>"
                                   data-contact-comment>
                            <div class="contact-error-msg" data-contact-error></div>
                        </div>

                        <button type="button" class="file-remove contractor-contact-remove" data-remove-contact aria-label="Удалить контакт">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-ghost contact-add-btn" data-add-contact>+ Добавить контакт</button>
        </div>

        <template data-contact-template>
            <div class="contractor-contact-row" data-contact-row>

                <div class="contact-cell-wrap contact-person-cell">
                    <div class="contact-input-suffix">
                        <input type="text" class="field-input" placeholder="Контактное лицо" data-contact-person>
                        <label class="checkbox-item contact-inline-check" title="Сделать основным контактом" aria-label="Сделать основным контактом">
                            <input type="checkbox" value="1" data-contact-primary>
                            <span class="checkbox-mark"></span>
                        </label>
                    </div>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-phone-cell">
                    <input type="text" class="field-input" placeholder="Телефон" data-contact-phone>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-email-cell">
                    <div class="contact-input-suffix">
                        <input type="text" class="field-input" placeholder="Email" data-contact-email>
                        <label class="checkbox-item contact-inline-check" title="Email для официальной переписки" aria-label="Email для официальной переписки">
                            <input type="checkbox" value="1" data-contact-document-email>
                            <span class="checkbox-mark"></span>
                        </label>
                    </div>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <div class="contact-cell-wrap contact-comment-cell">
                    <input type="text" class="field-input" placeholder="Комментарий" data-contact-comment>
                    <div class="contact-error-msg" data-contact-error></div>
                </div>

                <button type="button" class="file-remove contractor-contact-remove" data-remove-contact aria-label="Удалить контакт">&times;</button>
            </div>
        </template>
        <?php
    }
}
