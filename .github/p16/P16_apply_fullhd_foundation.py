from pathlib import Path

root = Path(__file__).resolve().parents[2]
layout = root / 'app/View/layouts/main.php'
text = layout.read_text(encoding='utf-8')
css_anchor = "    <link rel=\"stylesheet\" href=\"<?= app_url('/assets/css/erp-ui.css') ?>?v=<?= filemtime(base_path('public/assets/css/erp-ui.css')) ?>\">\n"
css_insert = css_anchor + "    <link rel=\"stylesheet\" href=\"<?= app_url('/assets/css/p16-fullhd.css') ?>?v=<?= filemtime(base_path('public/assets/css/p16-fullhd.css')) ?>\">\n"
if "p16-fullhd.css" not in text:
    if text.count(css_anchor) != 1:
        raise RuntimeError('CSS anchor mismatch')
    text = text.replace(css_anchor, css_insert, 1)
js_anchor = "    <script src=\"<?= app_url('/assets/js/app.js') ?>?v=<?= filemtime(base_path('public/assets/js/app.js')) ?>\"></script>\n"
js_insert = js_anchor + "    <script src=\"<?= app_url('/assets/js/p16-ui.js') ?>?v=<?= filemtime(base_path('public/assets/js/p16-ui.js')) ?>\"></script>\n"
if "p16-ui.js" not in text:
    if text.count(js_anchor) != 1:
        raise RuntimeError('JS anchor mismatch')
    text = text.replace(js_anchor, js_insert, 1)
layout.write_text(text, encoding='utf-8', newline='\n')
print('P16_FULLHD_LAYOUT_PATCH=PASS')
