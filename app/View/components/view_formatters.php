<?php

/**
 * Small presentation-only formatters for ERP views.
 * They do not change stored values or request handling.
 */

function ui_date(?string $value): string
{
    $value = trim((string) $value);
    return $value !== '' ? mb_substr($value, 0, 10) : '—';
}

function ui_actor(?string $role, mixed $id, ?string $name = null): string
{
    $name = trim((string) $name);
    if ($name !== '') {
        return $name;
    }

    $idValue = trim((string) $id);
    if ($idValue === '') {
        return '—';
    }

    $roleLabel = match ((string) $role) {
        'company_owner' => 'Руководитель',
        'logist' => 'Логист',
        'superadmin' => 'Суперадминистратор',
        default => 'Пользователь',
    };

    return $roleLabel . ' #' . $idValue;
}

function ui_access_level(?string $level): string
{
    return match ((string) $level) {
        'view' => 'Просмотр',
        'edit' => 'Редактирование',
        default => trim((string) $level) !== '' ? (string) $level : '—',
    };
}

function ui_contractor_type(?string $type): string
{
    return match ((string) $type) {
        'legal_entity' => 'Юридическое лицо',
        'individual' => 'Физическое лицо',
        'individual_entrepreneur' => 'ИП',
        default => trim((string) $type) !== '' ? (string) $type : '—',
    };
}
