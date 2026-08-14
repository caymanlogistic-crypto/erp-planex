<?php

// Compatibility layer for focused UI/runtime corrections.
if (PHP_SAPI !== 'cli') {
    $path = current_app_path();

    // Date pickers submit DD.MM.YYYY while DATE columns require YYYY-MM-DD.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('~^/company/drivers/\d+/(?:modal-edit|edit)$~', $path)) {
        foreach (['passport_issue_date', 'license_issue_date', 'passport_issue_date', 'license_expire_date'] as $field) {
            if (!isset($_POST[$field])) continue;
            $value = trim((string) $_POST[$field]);
            if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $value, $m)) {
                $day = (int) $m[1]; $month = (int) $m[2]; $year = (int) $m[3];
                if (checkdate($month, $day, $year)) {
                    $_POST[$field] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('~^/company/vehicle-sets(?:/create|/\d+/modal-edit)$~', $path)
        && !empty($_POST['units']) && is_array($_POST['units'])) {
        foreach ($_POST['units'] as $role => $unit) {
            if (!is_array($unit)) continue;
            foreach (['capacity_tons', 'volume_m3'] as $field) {
                if (!array_key_exists($field, $unit)) continue;
                $raw = preg_replace('/\s+/u', '', trim((string) $unit[$field])) ?? '';
                $_POST['units'][$role][$field] = $raw === '' ? '' : str_replace(',', '.', $raw);
            }
            if (array_key_exists('diagnostic_card_date', $unit)) {
                $value = trim((string) $unit['diagnostic_card_date']);
                if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $value, $m)) {
                    $day = (int) $m[1]; $month = (int) $m[2]; $year = (int) $m[3];
                    if (checkdate($month, $day, $year)) {
                        $_POST['units'][$role]['diagnostic_card_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                }
            }
        }
    }

    ob_start(static function (string $html): string {
        if (!str_contains($html, '</body>')) return $html;

        // The old menu slot is now the unified financial-structure editor.
        $html = str_replace('<span class="nav-label">Статьи ДДС</span>', '<span class="nav-label">Финансовая структура</span>', $html);

        // Employee settlements are a first-class finance workspace. Inject next
        // to Cash without duplicating the large legacy layout template.
        if (str_contains($html, '<span class="nav-label">Касса</span>') && !str_contains($html, '<span class="nav-label">Выплаты сотрудникам</span>')) {
            $employeeActive = str_starts_with(current_app_path(), '/company/finance/employee-payments') ? ' is-active' : '';
            $employeeHref = e(app_url('/company/finance/employee-payments'));
            $employeeItem = '<a class="nav-item' . $employeeActive . '" href="' . $employeeHref . '">' .
                '<svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="5.5" cy="5" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 13.5C1.5 10.8 3.5 8.8 5.5 8.8C7.5 8.8 9.5 10.8 9.5 13.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M10 5.5H14M12 3.5V7.5M10 11.5H14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>' .
                '<span class="nav-label">Выплаты сотрудникам</span></a>';
            $html = preg_replace('~(<a class="nav-item[^"]*" href="[^"]*/company/finance/cash">)~', $employeeItem . '$1', $html, 1) ?? $html;
        }

        // ERPv2 lives below /erpv2. A few legacy partials still emit root-relative
        // document links; scope the compatibility rewrite to ERPv2 so /erp is untouched.
        if (app_base_path() === '/erpv2') {
            $html = str_replace('href="/company/documents', 'href="/erpv2/company/documents', $html);
        }

        $src = app_url('/assets/js/driver-phone-optional.js') . '?v=' . filemtime(base_path('public/assets/js/driver-phone-optional.js'));
        $baseFix = <<<'HTML'
<script>
(function(){
  function fix(root){
    if(!root||!root.querySelectorAll)return;
    var base=window.getErpBasePath?window.getErpBasePath():'';
    var formSelector='#vehicle-set-create-form,#vehicle-set-edit-form,#driver-create-form,#driver-edit-form';
    var forms=[];
    if(root.matches&&root.matches(formSelector))forms.push(root);
    root.querySelectorAll(formSelector).forEach(function(form){forms.push(form);});
    forms.forEach(function(form){
      var action=form.getAttribute('action')||'';
      if(action.indexOf('/company/')===0&&base)form.setAttribute('action',base+action);
    });

    var links=[];
    if(root.matches&&root.matches('a[href^="/company/documents"]'))links.push(root);
    root.querySelectorAll('a[href^="/company/documents"]').forEach(function(link){links.push(link);});
    links.forEach(function(link){
      var href=link.getAttribute('href')||'';
      if(href.indexOf('/company/documents')===0&&base)link.setAttribute('href',base+href);
    });
  }
  fix(document);
  new MutationObserver(function(records){records.forEach(function(record){record.addedNodes.forEach(function(node){if(node.nodeType===1)fix(node);});});}).observe(document.body,{childList:true,subtree:true});
}());
</script>
HTML;
        return str_replace('</body>', '<script src="' . e($src) . '"></script>' . $baseFix . '</body>', $html);
    });
}
