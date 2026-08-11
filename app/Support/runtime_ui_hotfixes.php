<?php

// Small compatibility layer for UI corrections that must load after app.js
// without rewriting the main layout. The injected asset is inert outside
// driver forms and can be removed once app.js is refactored.
if (PHP_SAPI !== 'cli') {
    ob_start(static function (string $html): string {
        if (!str_contains($html, '</body>')) {
            return $html;
        }
        $src = app_url('/assets/js/driver-phone-optional.js') . '?v=' . filemtime(base_path('public/assets/js/driver-phone-optional.js'));
        return str_replace('</body>', '<script src="' . e($src) . '"></script></body>', $html);
    });
}
