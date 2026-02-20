<?php
if(!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('global_start', 'prlogin_global_start');

function prlogin_info()
{
	return array(
		'name' => 'PR Login',
		'description' => 'Добавляет гостям кнопку PR-вход и выполняет вход под заданным аккаунтом с редиректом в указанную тему.',
		'website' => '',
		'author' => 'Feathertail',
		'authorsite' => '',
		'version' => '1.0.2',
		'compatibility' => '18*'
	);
}

function prlogin_is_installed()
{
	global $db;
	return (bool)$db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='prlogin'", array('limit' => 1)),
		'gid'
	);
}

function prlogin_install()
{
	global $db;

	$group = array(
		'name' => 'prlogin',
		'title' => 'PR-вход для гостей',
		'description' => 'Настройки входа гостей под заранее созданным PR-аккаунтом.',
		'disporder' => 1,
		'isdefault' => 0
	);
	$gid = (int)$db->insert_query('settinggroups', $group);

	$settings = array();

	// Настройки
	$settings[] = array(
		'name' => 'prlogin_enabled',
		'title' => 'Включить PR-вход',
		'description' => 'Если выключено — кнопка не показывается и вход не выполняется.',
		'optionscode' => 'yesno',
		'value' => '1',
		'disporder' => 1,
		'gid' => $gid
	);

	$settings[] = array(
		'name' => 'prlogin_account_uid',
		'title' => 'UID PR-аккаунта',
		'description' => 'UID пользователя, под которым будут входить гости (например, 123).',
		'optionscode' => 'text',
		'value' => '0',
		'disporder' => 2,
		'gid' => $gid
	);

	$settings[] = array(
		'name' => 'prlogin_button_text',
		'title' => 'Текст кнопки',
		'description' => 'Например: PR-вход, Войти как PR, Гостевой вход и т.п.',
		'optionscode' => 'text',
		'value' => 'PR-вход',
		'disporder' => 3,
		'gid' => $gid
	);

	$settings[] = array(
		'name' => 'prlogin_redirect_tid',
		'title' => 'TID темы для редиректа',
		'description' => 'Куда перенаправлять после входа (tid). 0 — на главную.',
		'optionscode' => 'text',
		'value' => '0',
		'disporder' => 4,
		'gid' => $gid
	);

	foreach($settings as $s) {
		$db->insert_query('settings', $s);
	}

	rebuild_settings();
}

function prlogin_uninstall()
{
	global $db;

	$db->delete_query('settings', "name IN ('prlogin_enabled','prlogin_account_uid','prlogin_button_text','prlogin_redirect_tid')");
	$db->delete_query('settinggroups', "name='prlogin'");

	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('header_welcomeblock_guest', '#\{\$prlogin_welcome_link\}#i', '');

	rebuild_settings();
}

function prlogin_activate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	find_replace_templatesets(
		'header_welcomeblock_guest',
		'#</span>#i',
		'{$prlogin_welcome_link}</span>'
	);
}

function prlogin_deactivate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('header_welcomeblock_guest', '#\{\$prlogin_welcome_link\}#i', '');
}

function prlogin_global_start()
{
	global $mybb, $db, $prlogin_welcome_link;

	$prlogin_welcome_link = '';

	if((int)$mybb->settings['prlogin_enabled'] !== 1) {
		return;
	}

	$target_uid = (int)$mybb->settings['prlogin_account_uid'];

	if(defined('THIS_SCRIPT') && THIS_SCRIPT === 'member.php' && $mybb->get_input('action') === 'prlogin') {

		if((int)$mybb->user['uid'] !== 0) {
			prlogin_redirect_after_login();
		}

		verify_post_check($mybb->get_input('my_post_key'));

		if($target_uid <= 0) {
			error('PR-аккаунт не настроен.');
		}

		$user = get_user($target_uid);
		if(empty($user['uid'])) {
			error('PR-аккаунт не найден.');
		}

		if((int)$user['usergroup'] === 7) {
			error_no_permission();
		}

		require_once MYBB_ROOT.'inc/functions_user.php';

		if(empty($user['loginkey'])) {
			$user['loginkey'] = generate_loginkey();
			$db->update_query('users', array('loginkey' => $user['loginkey']), "uid='".(int)$user['uid']."'");
		}

		my_setcookie('mybbuser', (int)$user['uid'].'_'.$user['loginkey'], -1, true);

		prlogin_redirect_after_login();
	}

	if((int)$mybb->user['uid'] !== 0) {
		return;
	}

	if($target_uid <= 0) {
		return;
	}

	$text = trim((string)$mybb->settings['prlogin_button_text']);
	if($text === '') {
		$text = 'PR-вход';
	}

	$href = $mybb->settings['bburl'].'/member.php?action=prlogin&my_post_key='.$mybb->post_code;
	$prlogin_welcome_link = ' <a href="'.htmlspecialchars_uni($href).'" class="prlogin">'.htmlspecialchars_uni($text).'</a>';
}

function prlogin_redirect_after_login()
{
	global $mybb;

	$tid = (int)$mybb->settings['prlogin_redirect_tid'];
	if($tid > 0) {
		$link = get_thread_link($tid);
		$url = $mybb->settings['bburl'].'/'.ltrim($link, '/');
		redirect($url);
	}

	redirect($mybb->settings['bburl'].'/index.php');
}
