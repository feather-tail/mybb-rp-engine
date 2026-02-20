<?php
if (!defined('IN_MYBB')) {
	die('This file cannot be accessed');
}

$plugins->add_hook('parse_message', 'indent_run');

function indent_info()
{
	global $lang;
	indent_load_lang(true);

	$name = isset($lang->indent_plugin_name) ? $lang->indent_plugin_name : 'Отступы и табуляция';
	$desc = isset($lang->indent_plugin_desc) ? $lang->indent_plugin_desc : 'Одиночный тег [indent] в начале строки + [tab].';

	return array(
		'name' => $name,
		'description' => $desc,
		'website' => 'https://github.com/feather-tail',
		'author' => 'Feathertail',
		'authorsite' => 'https://github.com/feather-tail',
		'version' => '1.1.2',
		'compatibility' => '18*',
		'guid' => '8a5e3a6d8e6d4a2fa2c0f7c1b5f5a91c'
	);
}

function indent_is_installed()
{
	global $db;
	$gid = (int)$db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='indent'"),
		'gid'
	);
	return $gid > 0;
}

function indent_install()
{
	global $db, $lang;

	indent_load_lang(true);

	if (!function_exists('rebuild_settings')) {
		require_once MYBB_ROOT . 'inc/functions.php';
	}

	$gid = (int)$db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='indent'"),
		'gid'
	);

	$title = isset($lang->indent_settings_title) ? $lang->indent_settings_title : 'Отступы и табуляция';
	$descr = isset($lang->indent_settings_desc) ? $lang->indent_settings_desc : 'Настройки плагина [indent] и [tab].';

	if ($gid <= 0) {
		$group = array(
			'name' => 'indent',
			'title' => $db->escape_string($title),
			'description' => $db->escape_string($descr),
			'disporder' => 50,
			'isdefault' => 0
		);
		$db->insert_query('settinggroups', $group);
		$gid = (int)$db->insert_id();
	}

	$defaults = array(
		'indent_step' => '1em', // SETTINGS
		'indent_max_level' => '9' // SETTINGS
	);

	foreach ($defaults as $name => $value) {
		$exists = (int)$db->fetch_field(
			$db->simple_select('settings', 'sid', "name='" . $db->escape_string($name) . "'"),
			'sid'
		);
		if ($exists > 0) continue;

		if ($name === 'indent_step') {
			$st_title = isset($lang->indent_setting_step_title) ? $lang->indent_setting_step_title : 'Шаг отступа';
			$st_desc  = isset($lang->indent_setting_step_desc) ? $lang->indent_setting_step_desc : 'Например: 1em или 16px.';
			$options  = 'text';
			$order    = 1;
		} else {
			$st_title = isset($lang->indent_setting_max_title) ? $lang->indent_setting_max_title : 'Максимальный уровень';
			$st_desc  = isset($lang->indent_setting_max_desc) ? $lang->indent_setting_max_desc : 'Максимум для [indent=N] и [tab=N].';
			$options  = 'numeric';
			$order    = 2;
		}

		$setting = array(
			'name' => $name,
			'title' => $db->escape_string($st_title),
			'description' => $db->escape_string($st_desc),
			'optionscode' => $options,
			'value' => $value,
			'disporder' => $order,
			'gid' => $gid
		);
		$db->insert_query('settings', $setting);
	}

	rebuild_settings();
}

function indent_uninstall()
{
	global $db;

	if (!function_exists('rebuild_settings')) {
		require_once MYBB_ROOT . 'inc/functions.php';
	}

	$gid = (int)$db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='indent'"),
		'gid'
	);

	if ($gid > 0) {
		$db->delete_query('settings', "gid={$gid}");
		$db->delete_query('settinggroups', "gid={$gid}");
	}

	rebuild_settings();
}

function indent_activate() {}
function indent_deactivate() {}

function indent_load_lang($is_admin = true)
{
	global $lang;
	if (!isset($lang)) return;
	if (!empty($lang->indent_loaded)) return;

	$base = MYBB_ROOT . 'inc/languages/' . $lang->language . '/';
	$path = $is_admin ? ($base . 'admin/indent.lang.php') : ($base . 'indent.lang.php');

	if (file_exists($path)) {
		$l = array();
		include $path;
		if (isset($l) && is_array($l) && method_exists($lang, 'set_properties')) {
			$lang->set_properties($l);
		}
	}

	$lang->indent_loaded = 1;
}

function indent_parse_length($value, $fallback_num = 1.0, $fallback_unit = 'em')
{
	$value = trim((string)$value);
	if (preg_match('/^(\d+(?:\.\d+)?)([a-z%]+)$/i', $value, $m)) {
		return array((float)$m[1], strtolower($m[2]));
	}
	return array((float)$fallback_num, (string)$fallback_unit);
}

function indent_fmt_length($num, $unit)
{
	$s = rtrim(rtrim(number_format($num, 4, '.', ''), '0'), '.');
	if ($s === '') $s = '0';
	return $s . $unit;
}

function indent_clamp_int($v, $min, $max)
{
	$v = (int)$v;
	if ($v < $min) return $min;
	if ($v > $max) return $max;
	return $v;
}

function indent_style_for_level($level, $step_num, $step_unit)
{
	$ml = indent_fmt_length($step_num * $level, $step_unit);
	return 'display:inline-block;margin-left:' . $ml . ';vertical-align:baseline;';
}

function indent_tab_style_for_level($level, $step_num, $step_unit)
{
	$w = indent_fmt_length($step_num * $level, $step_unit);
	return 'display:inline-block;width:' . $w . ';vertical-align:baseline;';
}

function indent_process_line($line, $step_num, $step_unit, $max_level)
{
	if (!preg_match('~^(\s*)\[indent(?:=([0-9]{1,2}))?\]\s*~i', $line, $m)) {
		return $line;
	}

	$lead = $m[1];
	$level = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1;
	$level = indent_clamp_int($level, 1, $max_level);

	$rest = preg_replace('~^(\s*)\[indent(?:=([0-9]{1,2}))?\]\s*~i', '', $line, 1);
	$style = indent_style_for_level($level, $step_num, $step_unit);

	return $lead . '<span style="' . $style . '">' . $rest . '</span>';
}

function indent_run($message, ...$args)
{
	if (!is_string($message) || (stripos($message, '[indent') === false && stripos($message, '[tab') === false)) {
		return $message;
	}

	global $mybb;

	$step_raw = isset($mybb->settings['indent_step']) ? $mybb->settings['indent_step'] : '1em';
	$max_raw = isset($mybb->settings['indent_max_level']) ? $mybb->settings['indent_max_level'] : '9';

	list($step_num, $step_unit) = indent_parse_length($step_raw, 1.0, 'em');
	$step_num = max(0.0, $step_num);
	$max_level = indent_clamp_int($max_raw, 1, 30);

	$blocks = array();
	$idx = 0;

	$message = preg_replace_callback('~\[(code|php)(=[^\]]*)?\](.*?)\[/\1\]~is', function ($m) use (&$blocks, &$idx) {
		$key = '{{INDENT_BLOCK_' . ($idx++) . '}}';
		$blocks[$key] = $m[0];
		return $key;
	}, $message);

	$has_br = (stripos($message, '<br') !== false);

	if ($has_br) {
		$parts = preg_split('~(<br\s*/?>)~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0; $i < count($parts); $i += 2) {
			$parts[$i] = indent_process_line($parts[$i], $step_num, $step_unit, $max_level);
		}
		$message = implode('', $parts);
	} else {
		$parts = preg_split('~(\r?\n)~', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0; $i < count($parts); $i += 2) {
			$parts[$i] = indent_process_line($parts[$i], $step_num, $step_unit, $max_level);
		}
		$message = implode('', $parts);
	}

	$message = preg_replace_callback('~\[tab(?:=([0-9]{1,2}))?\]~i', function ($m) use ($step_num, $step_unit, $max_level) {
		$level = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : 1;
		$level = indent_clamp_int($level, 1, $max_level);
		$style = indent_tab_style_for_level($level, $step_num, $step_unit);
		return '<span style="' . $style . '" aria-hidden="true"></span>';
	}, $message);

	if (!empty($blocks)) {
		$message = strtr($message, $blocks);
	}

	return $message;
}
?>
