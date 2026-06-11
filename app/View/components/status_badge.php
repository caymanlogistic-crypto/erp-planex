<?php

function ui_status_badge(string $label, string $tone = 'neutral'): string
{
    $allowed = ['neutral', 'success', 'warning', 'danger'];
    $tone = in_array($tone, $allowed, true) ? $tone : 'neutral';

    return '<span class="status status-' . e($tone) . '">' . e($label) . '</span>';
}
