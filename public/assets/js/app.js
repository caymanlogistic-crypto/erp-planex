document.documentElement.classList.add('js-ready');

// ============================================================
// Подменю: раскрытие / закрытие группы «Подрядчики»
// ============================================================
(function () {
    // Клик по родительскому элементу — переключение is-open
    document.querySelectorAll('.nav-item.is-parent').forEach(function (parent) {
        parent.addEventListener('click', function () {
            parent.classList.toggle('is-open');
            var sub = parent.nextElementSibling;
            if (sub && sub.classList.contains('nav-sub')) {
                sub.classList.toggle('is-open');
            }
        });
    });

    // При загрузке: если любой подпункт активен — раскрыть родителя
    document.querySelectorAll('.nav-sub-item.is-active').forEach(function (activeItem) {
        var sub = activeItem.closest('.nav-sub');
        if (sub) {
            sub.classList.add('is-open');
            var parentEl = sub.previousElementSibling;
            if (parentEl && parentEl.classList.contains('is-parent')) {
                parentEl.classList.add('is-open');
            }
        }
    });
})();
