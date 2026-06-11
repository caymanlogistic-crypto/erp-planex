<?php

function ui_alert(string $message, string $type = 'info'): string
{
    $allowed = ['info', 'success', 'warning', 'danger'];
    $type = in_array($type, $allowed, true) ? $type : 'info';

    return '<div class="alert alert-' . e($type) . '">' . e($message) . '</div>';
}
