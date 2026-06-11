<?php

function ui_page_header(string $title, string $description, string $actionLabel = ''): string
{
    $action = $actionLabel !== ''
        ? '<div class="page-actions">' . ui_button($actionLabel) . '</div>'
        : '';

    return '<div class="page-header">'
        . '<div><h1>' . e($title) . '</h1><p>' . e($description) . '</p></div>'
        . $action
        . '</div>';
}
