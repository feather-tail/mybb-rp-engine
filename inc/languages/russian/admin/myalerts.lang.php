<?php
$l['myalerts'] = "MyAlerts";
$l['myalerts_pluginlibrary_missing'] = "Выбранный плагин не может быть установлен, потому что отсутствует <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a>.";

$l['setting_group_myalerts'] = "Настройки MyAlerts";
$l['setting_group_myalerts_desc'] = "Настройки плагина MyAlerts";
$l['setting_myalerts_perpage'] = "Уведомлений на странице";
$l['setting_myalerts_perpage_desc'] = "Сколько уведомлений отображать на странице списка уведомлений? (по умолчанию 10)";
$l['setting_myalerts_dropdown_limit'] = "Количество уведомлений в выпадающем списке";
$l['setting_myalerts_dropdown_limit_desc'] = "Сколько уведомлений отображать в глобальном выпадающем списке уведомлений? (по умолчанию 5)";
$l['setting_myalerts_autorefresh'] = "AJAX-автообновление страницы MyAlerts";
$l['setting_myalerts_autorefresh_desc'] = "Как часто (в секундах) обновлять список уведомлений на странице MyAlerts в панели управления пользователя через AJAX? (0 — без автообновления)";
$l['setting_myalerts_autorefresh_header_interval'] = "AJAX-автообновление счётчика в шапке";
$l['setting_myalerts_autorefresh_header_interval_desc'] = "Как часто (в секундах) обновлять количество уведомлений в шапке каждой страницы? (0 — без автообновления). Это обновление отключается на странице MyAlerts в панели управления пользователя, если включено автообновление выше, потому что оно уже обновляет и шапку (хотя, возможно, с другой частотой).";
$l['setting_myalerts_avatar_size'] = "Размер аватара";
$l['setting_myalerts_avatar_size_desc'] = "Размеры, которые будут использоваться при отображении аватаров в списках уведомлений. (В формате ширина|высота. Пример: 64|64.)";
$l['setting_myalerts_bc_mode'] = "Режим обратной совместимости";
$l['setting_myalerts_bc_mode_desc'] = "Нужен для поддержки клиентских плагинов, которые ещё не регистрируют свои форматтеры уведомлений через хук этого плагина `myalerts_register_client_alert_formatters`. Включение режима решает проблему пустых строк уведомлений в модальном окне для некоторых типов клиентских уведомлений после нажатия, например, «Отметить все как прочитанные».";

// For the task when run from the ACP.
// Duplicated in the user language file for when the task runs in a user context via the task image bottom of page.
$l['myalerts_task_cleanup_ran'] = 'Прочитанные уведомления старше {1} дней и непрочитанные уведомления старше {2} дней были успешно удалены!';
$l['myalerts_task_cleanup_error'] = 'При очистке уведомлений что-то пошло не так...';

$l['myalerts_task_title'] = 'Очистка MyAlerts';
$l['myalerts_task_description'] = 'Задача для очистки старых прочитанных уведомлений. Это необходимо, иначе таблица уведомлений может разрастись до огромных размеров.';

$l['myalerts_alert_types'] = 'Типы уведомлений';
$l['myalerts_can_manage_alert_types'] = 'Может управлять типами уведомлений?';

$l['myalerts_alert_type_code'] = 'Код';
$l['myalerts_alert_type_enabled'] = 'Включено?';
$l['myalerts_alert_type_can_be_user_disabled'] = 'Может быть отключено пользователями?';
$l['myalerts_alert_type_default_user_enabled'] = 'Включено по умолчанию для пользователей?';
$l['myalerts_no_alert_types'] = 'Типы уведомлений не найдены!';
$l['myalerts_update_alert_types'] = 'Обновить типы уведомлений';
$l['myalerts_alert_types_updated'] = 'Типы уведомлений обновлены!';

$l['myalerts_upgraded'] = 'MyAlerts был обновлён. Все старые пользовательские настройки уведомлений были потеряны — обязательно предупредите пользователей!';
