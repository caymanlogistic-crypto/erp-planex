<?php

function ui_form_actions(string $primaryLabel, string $secondaryLabel = 'Отмена'): string
{
    return '<div class="form-actions">'
        . ui_button($secondaryLabel, 'secondary')
        . ui_button($primaryLabel, 'primary', 'submit')
        . '</div>';
}
