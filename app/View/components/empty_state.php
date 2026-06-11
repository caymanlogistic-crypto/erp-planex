<?php

function ui_empty_state(string $title, string $text, string $actionLabel = ''): string
{
    $action = $actionLabel !== ''
        ? '<div class="empty-action">' . ui_button($actionLabel, 'secondary') . '</div>'
        : '';

    return '<section class="empty-state">'
        . '<h3>' . e($title) . '</h3>'
        . '<p>' . e($text) . '</p>'
        . $action
        . '</section>';
}
