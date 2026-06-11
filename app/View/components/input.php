<?php

function ui_input(string $name, string $label, string $value = '', bool $required = false): string
{
    $requiredMark = $required ? ' <span class="required">*</span>' : '';
    $requiredAttr = $required ? ' required' : '';

    return '<label class="field">'
        . '<span>' . e($label) . $requiredMark . '</span>'
        . '<input name="' . e($name) . '" value="' . e($value) . '"' . $requiredAttr . '>'
        . '</label>';
}
