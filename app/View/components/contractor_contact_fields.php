<?php

if (!function_exists('renderContractorContactFields')) {
    function renderContractorContactFields(array $contacts, array $errors = []): void
    {
        $contacts = $contacts !== [] ? array_values($contacts) : [[
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

                        <div class="contact-input-suffix contact-person-cell<?= !empty($rowErrors['contact_person']) ? ' is-error' : '' ?>">
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

                        <input type="text"
                               name="contacts[<?= $index ?>][phone]"
                               class="field-input contact-phone-cell"
                               placeholder="Телефон"
                               value="<?= e($contact['phone'] ?? '') ?>"
                               data-contact-phone>

                        <div class="contact-input-suffix contact-email-cell<?= !empty($rowErrors['email']) ? ' is-error' : '' ?>">
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

                        <input type="text"
                               name="contacts[<?= $index ?>][comment]"
                               class="field-input contact-comment-cell"
                               placeholder="Комментарий"
                               value="<?= e($contact['comment'] ?? '') ?>"
                               data-contact-comment>

                        <button type="button" class="file-remove contractor-contact-remove" data-remove-contact aria-label="Удалить контакт">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-ghost contact-add-btn" data-add-contact>+ Добавить контакт</button>
        </div>

        <template data-contact-template>
            <div class="contractor-contact-row" data-contact-row>

                <div class="contact-input-suffix contact-person-cell">
                    <input type="text" class="field-input" placeholder="Контактное лицо" data-contact-person>
                    <label class="checkbox-item contact-inline-check" title="Сделать основным контактом" aria-label="Сделать основным контактом">
                        <input type="checkbox" value="1" data-contact-primary>
                        <span class="checkbox-mark"></span>
                    </label>
                </div>

                <input type="text" class="field-input contact-phone-cell" placeholder="Телефон" data-contact-phone>

                <div class="contact-input-suffix contact-email-cell">
                    <input type="text" class="field-input" placeholder="Email" data-contact-email>
                    <label class="checkbox-item contact-inline-check" title="Email для официальной переписки" aria-label="Email для официальной переписки">
                        <input type="checkbox" value="1" data-contact-document-email>
                        <span class="checkbox-mark"></span>
                    </label>
                </div>

                <input type="text" class="field-input contact-comment-cell" placeholder="Комментарий" data-contact-comment>

                <button type="button" class="file-remove contractor-contact-remove" data-remove-contact aria-label="Удалить контакт">&times;</button>
            </div>
        </template>
        <?php
    }
}
