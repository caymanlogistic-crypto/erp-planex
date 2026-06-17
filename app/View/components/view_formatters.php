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

function ui_set_type(?string $type): string
{
    return match ((string) $type) {
        'single'     => 'Одиночка',
        'coupling'   => 'Сцепка',
        'road_train' => 'Автопоезд',
        default      => trim((string) $type) !== '' ? (string) $type : '—',
    };
}

function ui_unit_type(?string $type): string
{
    return match ((string) $type) {
        'single'       => 'Одиночное ТС',
        'tractor'      => 'Тягач',
        'semi_trailer' => 'Полуприцеп',
        'truck'        => 'Грузовик',
        'trailer'      => 'Прицеп',
        default        => trim((string) $type) !== '' ? (string) $type : '—',
    };
}

function ui_entity_type(?string $type): string
{
    return match ((string) $type) {
        'driver'               => 'Водитель',
        'vehicle'              => 'Транспортная единица',
        'vehicle_unit'         => 'Транспортная единица',
        'vehicle_set'          => 'Транспортный комплект',
        'crew'                 => 'Экипаж',
        'contractor'           => 'Подрядчик',
        'client'               => 'Клиент',
        'driver_vehicle_block' => 'Водитель + ТС',
        default                => trim((string) $type) !== '' ? (string) $type : '—',
    };
}

function ui_role(?string $role): string
{
    return match ((string) $role) {
        'company_owner' => 'Руководитель',
        'logist'        => 'Логист',
        'superadmin'    => 'Суперадминистратор',
        default         => trim((string) $role) !== '' ? (string) $role : '—',
    };
}

function ui_document_status(?string $status): string
{
    return match ((string) $status) {
        'uploaded' => 'Загружен',
        'pending'  => 'Ожидает проверки',
        'approved' => 'Принят',
        'rejected' => 'Отклонён',
        'archived' => 'Архивирован',
        'active'   => 'Активен',
        default    => trim((string) $status) !== '' ? (string) $status : '—',
    };
}
