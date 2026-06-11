<?php

echo ui_page_header(
    'UI foundation',
    'Техническая demo-страница дизайн-фундамента ERP PLANEX. Бизнес-логика, БД и авторизация не подключены.',
    'Основное действие'
);

echo ui_alert('Дизайн-фундамент подключён. Эта страница нужна только для проверки layout и компонентов.', 'info');
?>

<section class="grid two-columns">
    <div class="panel">
        <h2>Форма</h2>
        <form>
            <?= ui_input('company', 'Компания', 'PLANEX', true) ?>
            <?= ui_input('contact', 'Контактное лицо', '', false) ?>
            <?= ui_form_actions('Сохранить') ?>
        </form>
    </div>

    <div class="panel">
        <h2>Статусы</h2>
        <div class="status-stack">
            <?= ui_status_badge('Черновик') ?>
            <?= ui_status_badge('Активно', 'success') ?>
            <?= ui_status_badge('Требует внимания', 'warning') ?>
            <?= ui_status_badge('Ошибка', 'danger') ?>
        </div>

        <?= ui_empty_state('Данных пока нет', 'Пустое состояние объясняет, что делать дальше.', 'Добавить запись') ?>
    </div>
</section>

<section class="panel">
    <h2>Таблица</h2>
    <?php
    echo ui_table(
        ['Название', 'Тип', 'Статус', 'Действие'],
        [
            [e('Клиенты'), e('Будущий модуль'), ui_status_badge('Ожидает', 'warning'), e('Недоступно')],
            [e('Рейсы'), e('Будущий модуль'), ui_status_badge('Ожидает', 'warning'), e('Недоступно')],
            [e('SUPERADMIN'), e('Будущая панель'), ui_status_badge('Не начат'), e('Недоступно')],
        ]
    );
    ?>
</section>
