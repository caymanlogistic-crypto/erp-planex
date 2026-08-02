<?php

/**
 * Renders a standardized page header block.
 *
 * @param string $title       Page title (required)
 * @param string $description Short description below title
 * @param string $actionHtml  Pre-rendered action HTML (buttons, links) for the right side
 * @return string HTML
 */
function ui_page_header(string $title, string $description, string $actionHtml = ''): string
{
    $actions = $actionHtml !== ''
        ? '<div class="page-head-actions">' . $actionHtml . '</div>'
        : '';

    return '<div class="page-head">'
        . '<div class="page-head-left">'
        . '<h1 class="page-title">' . e($title) . '</h1>'
        . '<div class="page-summary"><span>' . e($description) . '</span></div>'
        . '</div>'
        . $actions
        . '</div>';
}
