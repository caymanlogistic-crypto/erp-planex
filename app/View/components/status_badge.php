<?php

/**
 * Unified status badge component for ERP PLANEX.
 *
 * Single source of truth for ALL status badges across SUPERADMIN and company views.
 * Do not copy this function into individual view files — include this file instead.
 *
 * @param string      $status     Raw status value (e.g. 'active', 'blocked', 'archived')
 * @param string|null $label      Optional override label; if null, label is auto-mapped
 * @param string      $entityType Optional context for domain-specific statuses (e.g. 'company', 'user', 'document')
 * @return string HTML span with badge classes and dot
 */
function renderStatusBadge(string $status, ?string $label = null, string $entityType = 'default'): string
{
    // Unified status → [class, label] mapping
    $baseMap = [
        'active'       => ['class' => 'badge-ok',    'label' => 'Активен'],
        'inactive'     => ['class' => '',             'label' => 'Неактивен'],
        'blocked'      => ['class' => 'badge-danger', 'label' => 'Заблокирован'],
        'archived'     => ['class' => '',             'label' => 'Архивирован'],
        'provisioning' => ['class' => 'badge-warn',   'label' => 'Настройка'],
        'error'        => ['class' => 'badge-danger', 'label' => 'Ошибка'],
        'suspended'    => ['class' => 'badge-warn',   'label' => 'Приостановлен'],
    ];

    $item = $baseMap[$status] ?? ['class' => '', 'label' => $status];
    $displayLabel = $label ?? $item['label'];

    return '<span class="badge ' . $item['class'] . '"><span class="dot"></span>' . e($displayLabel) . '</span>';
}

/**
 * Legacy alias for backward compatibility with ui_status_badge().
 * Uses status-* classes (not badge-*) for neutral/success/warning/danger tones.
 */
function ui_status_badge(string $label, string $tone = 'neutral'): string
{
    $allowed = ['neutral', 'success', 'warning', 'danger'];
    $tone = in_array($tone, $allowed, true) ? $tone : 'neutral';

    return '<span class="status status-' . e($tone) . '">' . e($label) . '</span>';
}
