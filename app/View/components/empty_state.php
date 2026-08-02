<?php

/**
 * Renders a standardized empty state block.
 * No CTA button inside — create actions belong in page-head-actions only.
 *
 * @param string $title   Short title like "Клиенты ещё не созданы."
 * @param string $text    Helpful explanation
 * @return string HTML
 */
function ui_empty_state(string $title, string $text): string
{
    return '<section class="empty-state">'
        . '<p class="empty-title">' . e($title) . '</p>'
        . '<p class="empty-desc">' . e($text) . '</p>'
        . '</section>';
}

/**
 * Renders a compact "no results for filters" empty state.
 */
function ui_empty_filtered(string $text = ''): string
{
    $text = $text !== '' ? $text : 'По заданным условиям ничего не найдено. Попробуйте изменить параметры поиска.';
    return '<section class="empty-state compact">'
        . '<p>' . e($text) . '</p>'
        . '</section>';
}
