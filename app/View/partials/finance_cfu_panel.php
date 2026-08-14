<?php
/*
 * ЦФУ теперь управляются в отдельном рабочем экране «Финансовая структура».
 * На странице правил разнесения дублирующий справочник намеренно не выводится:
 * правила используют тот же единый список ЦФУ, который передаётся в формы создания/редактирования.
 */
?>
<template id="rule-remove-template">
    <form method="post" action="<?= app_url('/company/finance/settings/matching-rules/remove') ?>" class="inline-form" data-rule-remove>
        <?= csrfField() ?>
        <input type="hidden" name="id">
        <button type="submit" class="btn btn-ghost btn-sm">Удалить</button>
    </form>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tpl = document.getElementById('rule-remove-template');
    if (!tpl) return;
    document.querySelectorAll('[data-edit-id]').forEach(function (edit) {
        var actions = edit.closest('.ux-actions');
        if (!actions || actions.querySelector('[data-rule-remove]')) return;
        var form = tpl.content.firstElementChild.cloneNode(true);
        form.querySelector('input[name="id"]').value = edit.dataset.editId;
        form.addEventListener('submit', function (event) {
            if (!confirm('Удалить правило?')) event.preventDefault();
        });
        actions.appendChild(form);
    });
});
</script>
