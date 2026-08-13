<?php

// Compatibility layer for focused UI/runtime corrections.
if (PHP_SAPI !== 'cli') {
    $path = current_app_path();

    // Date pickers submit DD.MM.YYYY while DATE columns require YYYY-MM-DD.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('~^/company/drivers/\d+/(?:modal-edit|edit)$~', $path)) {
        foreach (['passport_issue_date', 'license_issue_date', 'license_expire_date'] as $field) {
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

    // Modal views are fetched as HTML fragments, so server-side </body> rewriting
    // cannot see their links. Normalize document links when fragments enter the DOM.
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
