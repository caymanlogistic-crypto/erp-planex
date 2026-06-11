<?php

function ui_table(array $headers, array $rows): string
{
    $html = '<div class="table-wrap"><table><thead><tr>';

    foreach ($headers as $header) {
        $html .= '<th>' . e((string) $header) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}
