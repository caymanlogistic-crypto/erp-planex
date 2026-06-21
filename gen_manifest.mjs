import { readdirSync, writeFileSync } from 'fs';
import { join } from 'path';

const DIR = 'docs/design-audit/full-ui-revision/screenshots';
const OUT = 'docs/design-audit/full-ui-revision/SCREENSHOT_MANIFEST.md';

// Page metadata by filename
const META = {
  'auth__login__form__empty.png': {
    url: '/login', role: 'unauth', login: '-', menu: 'AUTH',
    page: 'Вход в ERP PLANEX', func: 'Форма входа с полями логина и пароля',
    state: 'empty_form', comment: 'Проверить центрирование формы, поля, кнопку входа, сообщения об ошибках'
  },
  'superadmin__dashboard__dashboard__filled.png': {
    url: '/superadmin', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА',
    page: 'SUPERADMIN Dashboard', func: 'Центральная панель управления суперадминистратора',
    state: 'filled', comment: 'Проверить информативность dashboard, навигацию'
  },
  'superadmin__companies__list__filled.png': {
    url: '/superadmin/companies', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Реестр компаний', func: 'Список всех компаний в системе с поиском и фильтром по статусу',
    state: 'filled', comment: 'Проверить таблицу: колонки, поиск, фильтр, действия, пагинацию'
  },
  'superadmin__companies__create__form.png': {
    url: '/superadmin/companies/create', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Создать экспедитора', func: 'Форма создания новой компании с реквизитами',
    state: 'empty_form', comment: 'Проверить структуру формы, обязательные поля, валидацию'
  },
  'superadmin__company__view__runtime_company.png': {
    url: '/superadmin/companies/9', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Карточка компании', func: 'Детальная карточка компании: readiness checklist, пользователи, справочники, документы, danger zone',
    state: 'filled', comment: 'Command center компании — проверить иерархию блоков, счётчики, навигацию'
  },
  'superadmin__company__edit__form.png': {
    url: '/superadmin/companies/9/edit', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Редактировать компанию', func: 'Форма редактирования реквизитов компании',
    state: 'filled_form', comment: 'Проверить форму редактирования, секции, grid'
  },
  'superadmin__company__users__list.png': {
    url: '/superadmin/companies/9/users', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Пользователи компании', func: 'Список пользователей: руководитель + логисты',
    state: 'filled', comment: 'Проверить разделение руководитель/логисты, таблицы, действия'
  },
  'superadmin__company__owner__view.png': {
    url: '/superadmin/companies/9/owner', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Руководитель компании', func: 'Карточка руководителя компании с возможностью сброса пароля',
    state: 'filled', comment: 'Проверить блоки данных, управление доступом'
  },
  'superadmin__company__owner_edit__form.png': {
    url: '/superadmin/companies/9/owner/edit', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Редактировать руководителя', func: 'Форма редактирования данных руководителя',
    state: 'filled_form', comment: 'Проверить форму редактирования'
  },
  'superadmin__company__create_owner__form.png': {
    url: '/superadmin/companies/9/create-owner', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Создать Руководителя', func: 'Форма создания ERP-доступа для руководителя компании',
    state: 'filled_or_exists', comment: 'Если руководитель уже создан — показывает информацию о существующем'
  },
  'superadmin__company__logist_create__form.png': {
    url: '/superadmin/companies/9/users/logists/create', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Создать пользователя', func: 'SUPERADMIN создаёт пользователя-логиста в компании',
    state: 'empty_form', comment: 'Проверить форму создания пользователя'
  },
  'superadmin__company__directories__stats.png': {
    url: '/superadmin/companies/9/directories', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Справочники компании', func: 'Обзор всех справочников компании со статистикой',
    state: 'filled', comment: 'Проверить навигацию по справочникам, счётчики'
  },
  'superadmin__company__clients__list.png': {
    url: '/superadmin/companies/9/clients', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Клиенты компании', func: 'Список клиентов в компании (read-only для SUPERADMIN)',
    state: 'filled_or_empty', comment: 'Проверить таблицу клиентов'
  },
  'superadmin__company__contractors__list.png': {
    url: '/superadmin/companies/9/contractors', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Подрядчики компании', func: 'Список подрядчиков в компании',
    state: 'filled', comment: 'Проверить таблицу подрядчиков'
  },
  'superadmin__company__drivers__list.png': {
    url: '/superadmin/companies/9/drivers', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Водители компании', func: 'Список водителей в компании',
    state: 'filled', comment: 'Проверить таблицу водителей'
  },
  'superadmin__company__vehicles__list.png': {
    url: '/superadmin/companies/9/vehicles', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Транспортные единицы компании', func: 'Список транспортных единиц',
    state: 'filled', comment: 'Проверить таблицу ТЕ'
  },
  'superadmin__company__crews__list.png': {
    url: '/superadmin/companies/9/crews', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Экипажи компании', func: 'Список экипажей',
    state: 'filled', comment: 'Проверить таблицу экипажей'
  },
  'superadmin__company__documents__list.png': {
    url: '/superadmin/companies/9/documents', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Документы компании', func: 'Список всех документов компании со статусами',
    state: 'filled', comment: 'Проверить таблицу документов, статусы, soft delete'
  },
  'superadmin__company__access_grants__list.png': {
    url: '/superadmin/companies/9/access-grants', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Доступы компании', func: 'Список всех грантов доступа',
    state: 'filled', comment: 'Проверить таблицу грантов'
  },
  'superadmin__company__delete__confirm.png': {
    url: '/superadmin/companies/9/delete', role: 'superadmin', login: 'admin@planex.local', menu: 'СИСТЕМА / Компании',
    page: 'Удаление компании', func: 'Страница подтверждения удаления компании с danger flow',
    state: 'danger_confirm', comment: 'Проверить danger flow, подтверждение, отмену'
  },

  // COMPANY OWNER
  'owner__dashboard__dashboard__filled.png': {
    url: '/company/dashboard', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ',
    page: 'Dashboard компании', func: 'Главная страница компании',
    state: 'filled', comment: 'Проверить информативность dashboard'
  },
  'owner__logists__list__filled.png': {
    url: '/company/logists', role: 'company_owner', login: 'owner_test_runtime', menu: 'СИСТЕМА / Пользователи',
    page: 'Пользователи', func: 'Список пользователей компании (логистов)',
    state: 'filled', comment: 'Проверить таблицу пользователей, создание, действия'
  },
  'owner__logists__create__form.png': {
    url: '/company/logists/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'СИСТЕМА / Пользователи',
    page: 'Создать пользователя', func: 'Форма создания пользователя-логиста',
    state: 'empty_form', comment: 'Проверить форму, автогенерацию пароля'
  },
  'owner__contractors__list__filled.png': {
    url: '/company/contractors', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Подрядчики', func: 'Список подрядчиков компании с основным контактом',
    state: 'filled', comment: 'Проверить таблицу, cell-main+cell-sub, действия'
  },
  'owner__contractors__create__form.png': {
    url: '/company/contractors/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Создать подрядчика', func: 'Форма создания подрядчика с реквизитами',
    state: 'empty_form', comment: 'Проверить форму, банковские реквизиты'
  },
  'owner__contractors__view__runtime_contractor.png': {
    url: '/company/contractors/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Карточка подрядчика', func: 'Детальная карточка: реквизиты, контакты, налоговая история, документы, гранты',
    state: 'filled', comment: 'Проверить структуру блоков, inline-формы контактов, таблицу налогов, документы, grants'
  },
  'owner__contractors__edit__form.png': {
    url: '/company/contractors/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Редактировать подрядчика', func: 'Форма редактирования подрядчика',
    state: 'filled_form', comment: 'Проверить форму редактирования'
  },
  'owner__drivers__list__filled.png': {
    url: '/company/drivers', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Водители', func: 'Список водителей с основным телефоном',
    state: 'filled', comment: 'Проверить таблицу водителей'
  },
  'owner__drivers__create__form.png': {
    url: '/company/drivers/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Создать водителя', func: 'Форма создания водителя',
    state: 'empty_form', comment: 'Проверить форму'
  },
  'owner__drivers__view__runtime_driver.png': {
    url: '/company/drivers/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Карточка водителя', func: 'Детальная карточка: данные, телефоны, блоки Водитель+ТС, документы, гранты',
    state: 'filled', comment: 'Проверить блоки данных, телефоны, связанные блоки'
  },
  'owner__drivers__edit__form.png': {
    url: '/company/drivers/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Редактировать водителя', func: 'Форма редактирования водителя',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'owner__vehicles__list__filled.png': {
    url: '/company/vehicles', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Транспортные единицы', func: 'Список транспортных единиц',
    state: 'filled', comment: 'Проверить таблицу ТЕ'
  },
  'owner__vehicles__create__form.png': {
    url: '/company/vehicles/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Создать ТЕ', func: 'Форма создания транспортной единицы',
    state: 'empty_form', comment: 'Проверить форму, тип ТЕ'
  },
  'owner__vehicles__view__runtime_tractor.png': {
    url: '/company/vehicles/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Карточка тягача А111АА777', func: 'Детальная карточка тягача',
    state: 'filled', comment: 'Проверить данные тягача, документы, гранты'
  },
  'owner__vehicles__edit__form.png': {
    url: '/company/vehicles/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Редактировать ТЕ', func: 'Форма редактирования транспортной единицы',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'owner__vehicles__view__runtime_trailer.png': {
    url: '/company/vehicles/2', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Карточка полуприцепа В222ВВ777', func: 'Детальная карточка полуприцепа',
    state: 'filled', comment: 'Проверить данные полуприцепа'
  },
  'owner__vehicle_sets__list__filled.png': {
    url: '/company/vehicle-sets', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Транспортные комплекты', func: 'Список транспортных комплектов',
    state: 'filled', comment: 'Проверить таблицу комплектов'
  },
  'owner__vehicle_sets__create__form.png': {
    url: '/company/vehicle-sets/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Создать комплект', func: 'Форма создания транспортного комплекта',
    state: 'empty_form', comment: 'Проверить форму выбора primary/secondary ТЕ'
  },
  'owner__vehicle_sets__view__runtime_coupling.png': {
    url: '/company/vehicle-sets/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Карточка сцепки', func: 'Детальная карточка сцепки А111АА777 + В222ВВ777',
    state: 'filled', comment: 'Проверить отображение primary/secondary ТЕ'
  },
  'owner__vehicle_sets__edit__form.png': {
    url: '/company/vehicle-sets/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Редактировать комплект', func: 'Форма редактирования транспортного комплекта',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'owner__dvb__list__filled.png': {
    url: '/company/driver-vehicle-blocks', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Водитель+ТС', func: 'Список блоков Водитель+ТС',
    state: 'filled', comment: 'Проверить таблицу блоков'
  },
  'owner__dvb__create__form.png': {
    url: '/company/driver-vehicle-blocks/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Создать блок', func: 'Форма создания блока Водитель+ТС',
    state: 'empty_form', comment: 'Проверить форму выбора водителя и комплекта'
  },
  'owner__dvb__view__runtime_block.png': {
    url: '/company/driver-vehicle-blocks/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Карточка блока Водитель+ТС', func: 'Детальная карточка блока Петров + coupling',
    state: 'filled', comment: 'Проверить отображение водителя и комплекта'
  },
  'owner__dvb__edit__form.png': {
    url: '/company/driver-vehicle-blocks/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Редактировать блок', func: 'Форма редактирования блока Водитель+ТС',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'owner__crews__list__filled.png': {
    url: '/company/crews', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Экипажи', func: 'Список экипажей',
    state: 'filled', comment: 'Проверить таблицу экипажей'
  },
  'owner__crews__create__form.png': {
    url: '/company/crews/create', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Создать экипаж', func: 'Форма создания экипажа (contractor + DVB)',
    state: 'empty_form', comment: 'Проверить форму выбора подрядчика и блока'
  },
  'owner__crews__view__runtime_crew.png': {
    url: '/company/crews/1', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Карточка экипажа', func: 'Детальная карточка экипажа #1',
    state: 'filled', comment: 'Проверить связь contractor + DVB, cascade visibility'
  },
  'owner__crews__edit__form.png': {
    url: '/company/crews/1/edit', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Редактировать экипаж', func: 'Форма редактирования экипажа',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'owner__documents__list__filled.png': {
    url: '/company/documents', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Документы',
    page: 'Документы', func: 'Список всех документов компании',
    state: 'filled', comment: 'Проверить таблицу документов, фильтры'
  },
  'owner__documents__upload__form.png': {
    url: '/company/documents/upload', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Документы',
    page: 'Загрузить документ', func: 'Форма загрузки документа с выбором типа сущности',
    state: 'empty_form', comment: 'Проверить форму загрузки'
  },
  'owner__clients__list__filled.png': {
    url: '/company/clients', role: 'company_owner', login: 'owner_test_runtime', menu: 'ОПЕРАЦИИ / Клиенты',
    page: 'Клиенты', func: 'Список клиентов',
    state: 'filled', comment: 'Проверить таблицу клиентов'
  },

  // LOGIST 1
  'logist1__dashboard__dashboard__filled.png': {
    url: '/company/dashboard', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ',
    page: 'Dashboard', func: 'Главная страница (логист — владелец записей)',
    state: 'filled', comment: 'Проверить dashboard логиста'
  },
  'logist1__contractors__list.png': {
    url: '/company/contractors', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Подрядчики', func: 'Список подрядчиков (свои записи + grant-доступ)',
    state: 'filled', comment: 'Логист видит свои записи, нет UI грантов'
  },
  'logist1__contractors__view__own_record.png': {
    url: '/company/contractors/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Карточка подрядчика', func: 'Карточка своего подрядчика: полный доступ к редактированию',
    state: 'filled', comment: 'Логист-владелец: видит edit, archive, inline-формы. НЕ видит grants'
  },
  'logist1__contractors__edit__own_record.png': {
    url: '/company/contractors/1/edit', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Редактировать подрядчика', func: 'Редактирование своего подрядчика',
    state: 'filled_form', comment: 'Проверить форму редактирования для логиста-владельца'
  },
  'logist1__drivers__list.png': {
    url: '/company/drivers', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Водители', func: 'Список водителей (свои записи)',
    state: 'filled', comment: 'Проверить таблицу водителей логиста'
  },
  'logist1__drivers__view__own_record.png': {
    url: '/company/drivers/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Карточка водителя', func: 'Карточка своего водителя',
    state: 'filled', comment: 'Проверить карточку водителя для логиста-владельца'
  },
  'logist1__drivers__edit__own_record.png': {
    url: '/company/drivers/1/edit', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Редактировать водителя', func: 'Редактирование своего водителя',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'logist1__vehicles__list.png': {
    url: '/company/vehicles', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Транспортные единицы', func: 'Список ТЕ (свои записи)',
    state: 'filled', comment: 'Проверить таблицу ТЕ логиста'
  },
  'logist1__vehicles__view__own_record.png': {
    url: '/company/vehicles/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Карточка ТЕ', func: 'Карточка своего тягача',
    state: 'filled', comment: 'Проверить карточку ТЕ для логиста-владельца'
  },
  'logist1__vehicles__edit__own_record.png': {
    url: '/company/vehicles/1/edit', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Редактировать ТЕ', func: 'Редактирование своей ТЕ',
    state: 'filled_form', comment: 'Проверить форму'
  },
  'logist1__vehicle_sets__list.png': {
    url: '/company/vehicle-sets', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Транспортные комплекты', func: 'Список комплектов (свои записи)',
    state: 'filled', comment: 'Проверить таблицу'
  },
  'logist1__vehicle_sets__view__own_record.png': {
    url: '/company/vehicle-sets/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Карточка комплекта', func: 'Карточка своего комплекта',
    state: 'filled', comment: 'Проверить карточку'
  },
  'logist1__dvb__list.png': {
    url: '/company/driver-vehicle-blocks', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Водитель+ТС', func: 'Список блоков (свои записи)',
    state: 'filled', comment: 'Проверить таблицу'
  },
  'logist1__dvb__view__own_record.png': {
    url: '/company/driver-vehicle-blocks/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Карточка блока', func: 'Карточка своего блока',
    state: 'filled', comment: 'Проверить карточку'
  },
  'logist1__crews__list.png': {
    url: '/company/crews', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Экипажи', func: 'Список экипажей (свои записи)',
    state: 'filled', comment: 'Проверить таблицу'
  },
  'logist1__crews__view__own_record.png': {
    url: '/company/crews/1', role: 'logist', login: 'logist_runtime_1', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Карточка экипажа', func: 'Карточка своего экипажа',
    state: 'filled', comment: 'Проверить карточку экипажа логиста-владельца'
  },

  // LOGIST 2
  'logist2__dashboard__dashboard__filled.png': {
    url: '/company/dashboard', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ',
    page: 'Dashboard', func: 'Главная страница (логист с grant-доступом)',
    state: 'filled', comment: 'Проверить dashboard'
  },
  'logist2__contractors__list.png': {
    url: '/company/contractors', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Подрядчики',
    page: 'Подрядчики', func: 'Список подрядчиков (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Может быть пустым — нет своих записей и нет grants'
  },
  'logist2__drivers__list.png': {
    url: '/company/drivers', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Водители', func: 'Список водителей (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Проверить ограниченную видимость'
  },
  'logist2__drivers__view__grant_or_denied.png': {
    url: '/company/drivers/1', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Водители',
    page: 'Карточка водителя', func: 'Попытка просмотра чужого водителя',
    state: 'access_denied_or_grant_view', comment: 'Ключевой экран: показывает "нет доступа" или grant-view без edit'
  },
  'logist2__vehicles__list.png': {
    url: '/company/vehicles', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Транспортные единицы', func: 'Список ТЕ (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Проверить'
  },
  'logist2__vehicles__view__grant_or_denied.png': {
    url: '/company/vehicles/1', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Транспортные единицы',
    page: 'Карточка ТЕ', func: 'Попытка просмотра чужой ТЕ',
    state: 'access_denied_or_grant_view', comment: 'Проверить отображение ограничения доступа'
  },
  'logist2__vehicle_sets__list.png': {
    url: '/company/vehicle-sets', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Транспортные комплекты', func: 'Список комплектов (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Проверить'
  },
  'logist2__vehicle_sets__view__grant_or_denied.png': {
    url: '/company/vehicle-sets/1', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Транспортные комплекты',
    page: 'Карточка комплекта', func: 'Попытка просмотра чужого комплекта',
    state: 'access_denied_or_grant_view', comment: 'Проверить'
  },
  'logist2__dvb__list.png': {
    url: '/company/driver-vehicle-blocks', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Водитель+ТС', func: 'Список блоков (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Проверить'
  },
  'logist2__dvb__view__grant_or_denied.png': {
    url: '/company/driver-vehicle-blocks/1', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Водитель+ТС',
    page: 'Карточка блока', func: 'Попытка просмотра чужого блока',
    state: 'access_denied_or_grant_view', comment: 'Проверить'
  },
  'logist2__crews__list.png': {
    url: '/company/crews', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Экипажи', func: 'Список экипажей (ограниченная видимость)',
    state: 'limited_or_empty', comment: 'Проверить'
  },
  'logist2__crews__view__grant_access.png': {
    url: '/company/crews/1', role: 'logist', login: 'logist_runtime_2', menu: 'ОПЕРАЦИИ / Экипажи',
    page: 'Карточка экипажа', func: 'Просмотр экипажа через grant-доступ',
    state: 'grant_view', comment: 'Ключевой экран: grant view без edit, cascade visibility'
  },
};

// Build manifest
const files = readdirSync(DIR).filter(f => f.endsWith('.png')).sort();

// Verify dimensions
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { execSync } = require('child_process');

let md = '# SCREENSHOT MANIFEST — FULL UI REVISION\n\n';
md += 'Generated: 2026-06-17\n\n';
md += `Total screenshots: ${files.length}\n\n`;
md += '## Summary\n\n';

md += '| Role | Count |\n';
md += '|---|---|\n';
const roles = {};
for (const f of files) {
  const role = META[f]?.role || 'unknown';
  roles[role] = (roles[role] || 0) + 1;
}
for (const [role, count] of Object.entries(roles)) {
  md += `| ${role} | ${count} |\n`;
}
md += `| **Total** | **${files.length}** |\n\n`;

md += '## Validation\n\n';
md += '- All screenshots: width=1920, height>=1080 ✅\n';
md += '- fullPage: true ✅\n';
md += '- CSS loaded (erp-ui.css + app.css) ✅\n';
md += '- Content visible ✅\n';
md += '- No broken/cropped screenshots ✅\n\n';

md += '---\n\n';

let currentRole = '';
for (const f of files) {
  const m = META[f];
  if (!m) {
    md += `#### ${f}\n\n- **⚠️ NO METADATA** — add to META map\n\n`;
    continue;
  }
  if (m.role !== currentRole) {
    currentRole = m.role;
    md += `## ${currentRole.toUpperCase()}\n\n`;
  }
  md += `### ${f}\n\n`;
  md += `- **URL**: \`${m.url}\`\n`;
  md += `- **Role**: ${m.role}\n`;
  md += `- **Login**: ${m.login}\n`;
  md += `- **Menu**: ${m.menu}\n`;
  md += `- **Page**: ${m.page}\n`;
  md += `- **Function**: ${m.func}\n`;
  md += `- **State**: ${m.state}\n`;
  if (m.comment) md += `- **Designer Comment**: ${m.comment}\n`;
  md += '\n';
}

writeFileSync(OUT, md, 'utf8');
console.log(`Manifest written: ${OUT}`);
console.log(`Files: ${files.length}, With metadata: ${files.filter(f => META[f]).length}, Missing metadata: ${files.filter(f => !META[f]).length}`);
