<?php

function ui_button(string $label, string $variant = 'primary', string $type = 'button'): string
{
    $allowed = ['primary', 'secondary', 'danger'];
    $variant = in_array($variant, $allowed, true) ? $variant : 'primary';

    return '<button class="btn btn-' . e($variant) . '" type="' . e($type) . '">' . e($label) . '</button>';
}
