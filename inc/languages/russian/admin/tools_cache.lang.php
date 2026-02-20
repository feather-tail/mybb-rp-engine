<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['cache'] = "Кэш:";
$l['cache_manager'] = "Управление кэшем";
$l['cache_manager_description'] = "Здесь вы можете управлять кэшем, который используются в качестве метода оптимизации MyBB. Перепостроение кэша будет принимать все необходимые данные, используемые для создания кэш-памяти и выполнит повторную синхронизацию. Перезагрузка кэша повторно загрузит его в выбранный обработчик кэша (disk, eaccelerator, memcache, и т.п.). Перезагрузка полезна при переключении  из базы данных или файловой системы в xcache, eaccelerator или другой обработчик кэша.";
$l['rebuild_cache'] = "Перестроить кэш";
$l['reload_cache'] = "Перегрузить кэш";
$l['rebuild_reload_all'] = "(Перестроить & Перегрузить Всё)";

$l['error_cannot_rebuild'] = "Кэш перестроить невозможно.";
$l['error_empty_cache'] = "Кэш пуст.";
$l['error_incorrect_cache'] = "Некорректное указание кэша .";
$l['error_no_cache_specified'] = "Вы не указали кэш для отображения.";

$l['success_cache_rebuilt'] = "Кэш был перестроен успешно.";
$l['success_cache_reloaded'] = "Кэш был перегружен успешно.";
