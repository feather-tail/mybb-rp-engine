<?php
if(!defined("IN_MYBB")) die();

define('QUICKSCROLL_VER', '1.0.0');

$plugins->add_hook('pre_output_page', 'quickscroll_pre_output_page');

function quickscroll_info()
{
	return array(
		"name"			=> "QuickScroll (Up/Down arrows)",
		"description"	=> "Adds configurable quick scroll arrows (up/down) without template edits.",
		"website"		=> "",
		"author"		=> "Feathertail",
		"authorsite"	=> "",
		"version"		=> "1.0",
		"compatibility"	=> "18*"
	);
}

function quickscroll_is_installed()
{
	global $db;
	$q = $db->simple_select('settinggroups', 'gid', "name='quickscroll'", array('limit' => 1));
	return (bool)$db->num_rows($q);
}

function quickscroll_install()
{
	global $db;

	$group = array(
		"name" => "quickscroll",
		"title" => "QuickScroll",
		"description" => "Settings for quick scroll arrows (up/down).",
		"disporder" => 1,
		"isdefault" => 0
	);
	$gid = (int)$db->insert_query("settinggroups", $group);

	$settings = array();

	$settings[] = array( // Включить/выключить
		"name" => "quickscroll_enabled",
		"title" => "Enable QuickScroll",
		"description" => "Turn the arrows on/off.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 1,
		"gid" => $gid
	);

	$settings[] = array( // Позиция
		"name" => "quickscroll_side",
		"title" => "Side",
		"description" => "Where to place the buttons.",
		"optionscode" => "select\nright=Right\nleft=Left",
		"value" => "right",
		"disporder" => 2,
		"gid" => $gid
	);

	$settings[] = array( // Отступ снизу
		"name" => "quickscroll_bottom",
		"title" => "Bottom offset (px)",
		"description" => "Distance from bottom edge.",
		"optionscode" => "text",
		"value" => "24",
		"disporder" => 3,
		"gid" => $gid
	);

	$settings[] = array( // Отступ сбоку
		"name" => "quickscroll_side_offset",
		"title" => "Side offset (px)",
		"description" => "Distance from left/right edge.",
		"optionscode" => "text",
		"value" => "24",
		"disporder" => 4,
		"gid" => $gid
	);

	$settings[] = array( // Размер кнопок
		"name" => "quickscroll_size",
		"title" => "Button size (px)",
		"description" => "Width/height of buttons.",
		"optionscode" => "text",
		"value" => "44",
		"disporder" => 5,
		"gid" => $gid
	);

	$settings[] = array( // Расстояние между кнопками
		"name" => "quickscroll_gap",
		"title" => "Gap (px)",
		"description" => "Distance between up/down buttons.",
		"optionscode" => "text",
		"value" => "10",
		"disporder" => 6,
		"gid" => $gid
	);

	$settings[] = array( // Z-index
		"name" => "quickscroll_z",
		"title" => "z-index",
		"description" => "Stacking order for the buttons.",
		"optionscode" => "text",
		"value" => "9999",
		"disporder" => 7,
		"gid" => $gid
	);

	$settings[] = array( // Порог появления “вверх”
		"name" => "quickscroll_show_up_after",
		"title" => "Show UP after scroll (px)",
		"description" => "UP button appears after this scroll amount.",
		"optionscode" => "text",
		"value" => "200",
		"disporder" => 8,
		"gid" => $gid
	);

	$settings[] = array( // Скрывать “вниз” у низа
		"name" => "quickscroll_hide_down_near_bottom",
		"title" => "Hide DOWN near bottom",
		"description" => "Hide DOWN button when near the page bottom.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 9,
		"gid" => $gid
	);

	$settings[] = array( // Зона “у низа”
		"name" => "quickscroll_bottom_gap",
		"title" => "Bottom gap (px)",
		"description" => "How close to bottom counts as “near bottom”.",
		"optionscode" => "text",
		"value" => "80",
		"disporder" => 10,
		"gid" => $gid
	);

	$settings[] = array( // Плавная прокрутка
		"name" => "quickscroll_smooth",
		"title" => "Smooth scroll",
		"description" => "Use smooth scrolling when supported.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 11,
		"gid" => $gid
	);

	$settings[] = array( // Действие вниз
		"name" => "quickscroll_down_action",
		"title" => "DOWN action",
		"description" => "Scroll to bottom or one screen down.",
		"optionscode" => "select\nbottom=To bottom\npage=One screen",
		"value" => "bottom",
		"disporder" => 12,
		"gid" => $gid
	);

	$settings[] = array( // Включить UP
		"name" => "quickscroll_enable_up",
		"title" => "Enable UP button",
		"description" => "Show the UP button.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 13,
		"gid" => $gid
	);

	$settings[] = array( // Включить DOWN
		"name" => "quickscroll_enable_down",
		"title" => "Enable DOWN button",
		"description" => "Show the DOWN button.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 14,
		"gid" => $gid
	);

	$settings[] = array( // Mobile
		"name" => "quickscroll_enable_mobile",
		"title" => "Enable on mobile",
		"description" => "If disabled, buttons are hidden below the breakpoint.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => 15,
		"gid" => $gid
	);

	$settings[] = array( // Брейкпоинт
		"name" => "quickscroll_mobile_breakpoint",
		"title" => "Mobile breakpoint (px)",
		"description" => "Below this width buttons will hide (if mobile disabled).",
		"optionscode" => "text",
		"value" => "768",
		"disporder" => 16,
		"gid" => $gid
	);

	$settings[] = array( // Произвольный CSS
		"name" => "quickscroll_custom_css",
		"title" => "Custom CSS",
		"description" => "Extra CSS appended after the default stylesheet.",
		"optionscode" => "textarea",
		"value" => "",
		"disporder" => 17,
		"gid" => $gid
	);

	$settings[] = array( // HTML иконки UP
		"name" => "quickscroll_icon_up",
		"title" => "UP icon HTML",
		"description" => "Optional HTML/SVG for UP icon (leave empty for default).",
		"optionscode" => "textarea",
		"value" => "",
		"disporder" => 18,
		"gid" => $gid
	);

	$settings[] = array( // HTML иконки DOWN
		"name" => "quickscroll_icon_down",
		"title" => "DOWN icon HTML",
		"description" => "Optional HTML/SVG for DOWN icon (leave empty for default).",
		"optionscode" => "textarea",
		"value" => "",
		"disporder" => 19,
		"gid" => $gid
	);

	$db->insert_query_multiple("settings", $settings);

	require_once MYBB_ROOT."inc/functions.php";
	rebuild_settings();
}

function quickscroll_uninstall()
{
	global $db;

	$db->delete_query("settings", "name LIKE 'quickscroll_%'");
	$db->delete_query("settinggroups", "name='quickscroll'");

	require_once MYBB_ROOT."inc/functions.php";
	rebuild_settings();
}

function quickscroll_activate()
{
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
}

function quickscroll_deactivate()
{
}

function quickscroll_pre_output_page($contents)
{
	global $mybb;

	if(defined('IN_ADMINCP') || defined('IN_TASK') || defined('IN_ARCHIVE'))
	{
		return $contents;
	}

	if(empty($mybb->settings['quickscroll_enabled']))
	{
		return $contents;
	}

	if(stripos($contents, '</head>') === false || stripos($contents, '</body>') === false)
	{
		return $contents;
	}

	$bburl = rtrim((string)$mybb->settings['bburl'], '/');
	$ver = QUICKSCROLL_VER;

	$side = ($mybb->settings['quickscroll_side'] === 'left') ? 'left' : 'right';
	$bottom = (int)$mybb->settings['quickscroll_bottom'];
	$side_offset = (int)$mybb->settings['quickscroll_side_offset'];
	$size = (int)$mybb->settings['quickscroll_size'];
	$gap = (int)$mybb->settings['quickscroll_gap'];
	$z = (int)$mybb->settings['quickscroll_z'];

	$show_up_after = max(0, (int)$mybb->settings['quickscroll_show_up_after']);
	$hide_down_near_bottom = !empty($mybb->settings['quickscroll_hide_down_near_bottom']) ? 1 : 0;
	$bottom_gap = max(0, (int)$mybb->settings['quickscroll_bottom_gap']);
	$smooth = !empty($mybb->settings['quickscroll_smooth']) ? 1 : 0;
	$down_action = ($mybb->settings['quickscroll_down_action'] === 'page') ? 'page' : 'bottom';

	$enable_up = !empty($mybb->settings['quickscroll_enable_up']) ? 1 : 0;
	$enable_down = !empty($mybb->settings['quickscroll_enable_down']) ? 1 : 0;

	$enable_mobile = !empty($mybb->settings['quickscroll_enable_mobile']) ? 1 : 0;
	$mobile_bp = max(240, (int)$mybb->settings['quickscroll_mobile_breakpoint']);

	$custom_css = (string)$mybb->settings['quickscroll_custom_css'];
	$icon_up = trim((string)$mybb->settings['quickscroll_icon_up']);
	$icon_down = trim((string)$mybb->settings['quickscroll_icon_down']);

	if($icon_up === '')
	{
		$icon_up = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5l7 7-1.4 1.4L13 8.8V20h-2V8.8L6.4 13.4 5 12z"/></svg>';
	}
	if($icon_down === '')
	{
		$icon_down = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19l-7-7 1.4-1.4L11 15.2V4h2v11.2l4.6-4.6L19 12z"/></svg>';
	}

	$config = array(
		"showUpAfter" => $show_up_after,
		"hideDownNearBottom" => $hide_down_near_bottom,
		"bottomGap" => $bottom_gap,
		"smooth" => $smooth,
		"downAction" => $down_action,
		"enableUp" => $enable_up,
		"enableDown" => $enable_down
	);

	$cfg_json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$head_inject = '';
	if(stripos($contents, 'quickscroll.css') === false)
	{
		$head_inject .= "\n<link rel=\"stylesheet\" href=\"{$bburl}/jscripts/quickscroll.css?v={$ver}\" />\n";
	}

	if(!$enable_mobile)
	{
		$head_inject .= "<style>#qs-wrap{display:none!important;}@media (min-width: {$mobile_bp}px){#qs-wrap{display:flex!important;}}</style>\n";
	}

	if(trim($custom_css) !== '')
	{
		$head_inject .= "<style>\n{$custom_css}\n</style>\n";
	}

	$contents = preg_replace('~</head>~i', $head_inject.'</head>', $contents, 1);

	$style_vars = "--qs-bottom: {$bottom}px; --qs-side: {$side_offset}px; --qs-size: {$size}px; --qs-gap: {$gap}px; --qs-z: {$z};";
	$wrap_class = "qs-{$side}";

	$buttons = '';
	if($enable_up)
	{
		$buttons .= "<a href=\"#\" class=\"qs-btn qs-up\" role=\"button\" aria-label=\"Scroll to top\" title=\"Up\">{$icon_up}</a>";
	}
	if($enable_down)
	{
		$buttons .= "<a href=\"#\" class=\"qs-btn qs-down\" role=\"button\" aria-label=\"Scroll down\" title=\"Down\">{$icon_down}</a>";
	}

	$body_inject = "\n<div id=\"qs-wrap\" class=\"{$wrap_class}\" style=\"{$style_vars}\">{$buttons}</div>\n";
	$body_inject .= "<script>window.QuickScrollConfig={$cfg_json};</script>\n";

	if(stripos($contents, 'quickscroll.js') === false)
	{
		$body_inject .= "<script src=\"{$bburl}/jscripts/quickscroll.js?v={$ver}\"></script>\n";
	}

	$contents = preg_replace('~</body>~i', $body_inject.'</body>', $contents, 1);

	return $contents;
}
