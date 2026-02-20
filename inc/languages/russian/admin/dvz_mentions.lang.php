<?php

$l['dvz_mentions_description'] = 'Преобразует упоминания <i>@username</i> в ссылки на профиль. Интегрируется с <i>MyAlerts</i>.';
$l['dvz_mentions_alerts'] = '<br><br><b>Интеграция с MyAlerts:</b>';
$l['dvz_mentions_alerts_install'] = 'установить';
$l['dvz_mentions_alerts_uninstall'] = 'удалить';
$l['dvz_mentions_alerts_installed'] = 'Интеграция с MyAlerts была установлена.';
$l['dvz_mentions_alerts_uninstalled'] = 'Интеграция с MyAlerts была удалена.';

$l['dvz_mentions_admin_pluginlibrary_missing'] = 'Добавьте <a href="https://mods.mybb.com/view/pluginlibrary">PluginLibrary</a>, чтобы использовать плагин.';

$l['setting_group_dvz_mentions'] = 'DVZ Mentions';
$l['setting_group_dvz_mentions_desc'] = 'Настройки плагина DVZ Mentions.';

$l['setting_dvz_mentions_keep_prefix'] = 'Сохранять префикс обращения';
$l['setting_dvz_mentions_keep_prefix_desc'] = 'Укажите, нужно ли отображать префикс "@" в сообщениях.';

$l['setting_dvz_mentions_apply_username_style'] = 'Применять стиль имени пользователя';
$l['setting_dvz_mentions_apply_username_style_desc'] = 'Укажите, нужно ли применять стиль имени пользователя, заданный для группы.';

$l['setting_dvz_mentions_links_to_new_tabs'] = 'Открывать ссылки профиля в новой вкладке';
$l['setting_dvz_mentions_links_to_new_tabs_desc'] = 'Укажите, должны ли ссылки на профиль открываться в отдельной вкладке.';

$l['setting_dvz_mentions_cs_collation'] = 'Учитывать регистр имени пользователя';
$l['setting_dvz_mentions_cs_collation_desc'] = 'Укажите, должен ли плагин выполнять дополнительные преобразования регистра символов для совместимости с чувствительной к регистру сортировкой столбца <code>username</code>.';

$l['setting_dvz_mentions_ignored_values'] = 'Игнорируемые имена пользователей';
$l['setting_dvz_mentions_ignored_values_desc'] = 'Введите значения, которые будут игнорироваться парсером упоминаний, каждое с новой строки.';

$l['setting_dvz_mentions_min_value_length'] = 'Минимальная длина имени пользователя';
$l['setting_dvz_mentions_min_value_length_desc'] = 'Укажите минимальную длину значения, которое может быть распознано.';

$l['setting_dvz_mentions_max_value_length'] = 'Максимальная длина значения';
$l['setting_dvz_mentions_max_value_length_desc'] = 'Укажите максимальную длину значения, которое может быть распознано.';

$l['setting_dvz_mentions_match_limit'] = 'Лимит упоминаний в сообщении';
$l['setting_dvz_mentions_match_limit_desc'] = 'Укажите максимальное количество упоминаний в одном сообщении, при превышении которого упоминания не будут обрабатываться.';

$l['setting_dvz_mentions_query_limit'] = 'Лимит упоминаний в запросе';
$l['setting_dvz_mentions_query_limit_desc'] = 'Укажите максимальное количество упоминаний в запросе к базе данных (обычно одно на страницу), при превышении которого упоминания не будут обрабатываться.';
