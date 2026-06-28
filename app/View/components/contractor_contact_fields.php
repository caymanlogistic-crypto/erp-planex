<?php

require_once __DIR__ . '/contact_fields.php';

if (!function_exists('renderContractorContactFields')) {
    function renderContractorContactFields(array $contacts, array $errors = []): void
    {
        renderContactFields([
            'entity_type' => 'contractor',
            'field_prefix' => 'contacts',
            'contacts' => $contacts,
            'errors' => $errors,
            'allow_primary' => true,
            'allow_document_email' => true,
        ]);
    }
}
