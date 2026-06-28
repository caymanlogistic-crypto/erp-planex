<?php

require_once __DIR__ . '/contact_fields.php';

if (!function_exists('renderClientContactFields')) {
    function renderClientContactFields(array $contacts, array $errors = []): void
    {
        renderContactFields([
            'entity_type' => 'client',
            'field_prefix' => 'contacts',
            'contacts' => $contacts,
            'errors' => $errors,
            'allow_primary' => true,
            'allow_document_email' => true,
        ]);
    }
}
