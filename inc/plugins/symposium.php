<?php

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined('PLUGINLIBRARY')) {
	define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
}

function symposium_info()
{
	symposium_plugin_edit();

	$description = '';

	if (symposium_is_installed()) {

		global $PL, $mybb, $cache;

		$PL or require_once PLUGINLIBRARY;

		$pluginInfo = (array)$cache->read('shade_plugins');

		$count = (int)($pluginInfo['Symposium']['notConvertedCount'] ?? 0);

		if ($count > 0) {

			$convert = $PL->url_append('index.php', [
				'module' => 'config-plugins',
			]);

			$description = "<br><br>You have {$count} private messages to convert. <a href='{$convert}' id='symposiumConversion'>Start conversion.</a>";

			$description .= <<<HTML
<script type="text/javascript">
$(document).ready(() => {
	var total = parseInt('{$count}');
	var iterations = 0, totalRate = 0, previous = 0;
	function convertPage() {
		var t0 = performance.now();
		return $.ajax({
			type: 'POST',
			url: '{$convert}',
			data: {
				'symposium': 'convert',
				'my_post_key': '{$mybb->post_code}'
			},
			complete: (xhr) => {
				var response = parseInt(xhr.responseText);
				var textField = $('#symposiumConversion');
				var seconds = (performance.now() - t0) / 1000;
				iterations++;
				var processedPms = (total - response);
				totalRate += ((processedPms - previous) / seconds);
				var averageRate = (totalRate / iterations);
				var remaining = (response / averageRate).toFixed();
				previous = processedPms;

				var label = ' seconds';
				if (remaining >= (60*60*2)) label = ' hours';
				else if (remaining >= (60*60)) label = ' hour';
				else if (remaining >= 120) label = ' minutes';
				else if (remaining >= 60) label = ' minute';

				if (response > 0) {
					textField.text('Converting... ' + processedPms + '/' + total + ' @' + averageRate.toFixed() + ' pms/s. ETA: ' + remaining + label + '. DO NOT CLOSE THE PAGE!');
					return convertPage();
				} else if (response === 0) {
					return textField.text('Conversion successful. ' + total + '/' + total + ' private messages have been converted into conversations.');
				} else {
					console.log(xhr.responseText);
					return textField.text('Conversion failed. Please open your browser console and report the issue at https://www.mybboost.com/forum-symposium.');
				}
			}
		});
	}

	$('#symposiumConversion').on('click', function(e) {
		e.preventDefault();
		$(this).replaceWith($('<span id=' + this.id + '>Converting... 0/' + total + '</span>'));
		return convertPage();
	});
});
</script>
HTML;
		}

		if (symposium_apply_core_edits() !== true) {
			$apply = $PL->url_append('index.php', [
				'module' => 'config-plugins',
				'symposium' => 'apply',
				'my_post_key' => $mybb->post_code,
			]);
			$description .= "<br><br>Core edits missing. <a href='{$apply}'>Apply core edits.</a>";
		} else {
			$revert = $PL->url_append('index.php', [
				'module' => 'config-plugins',
				'symposium' => 'revert',
				'my_post_key' => $mybb->post_code,
			]);
			$description .= "<br><br>Core edits in place. <a href='{$revert}'>Revert core edits.</a>";
		}
	}

	return [
		'name' => 'Symposium',
		'description' => 'Conversation system replacement for email-style private messages.' . $description,
		'website' => 'https://www.mybboost.com/forum-symposium',
		'author' => 'Shade',
		'authorsite' => 'https://www.mybboost.com',
		'version' => 'beta 3',
		'compatibility' => '18*'
	];
}

function symposium_is_installed()
{
	global $db, $cache;

	if (isset($db) && method_exists($db, 'table_exists') && $db->table_exists('symposium_conversations')) {
		return true;
	}

	$installed = (array)$cache->read('shade_plugins');
	return !empty($installed['Symposium']);
}

function symposium_activate()
{
	symposium_apply_core_edits(true);
}

function symposium_deactivate()
{
	symposium_revert_core_edits(true);
}

function symposium_install()
{
	global $db, $PL, $lang, $mybb, $cache;

	if (!$lang->symposium) {
		$lang->load('symposium');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->symposium_pluginlibrary_missing, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	$settingsToAdd = [
		'group_conversations' => [
			'title' => $lang->setting_symposium_group_conversations,
			'description' => $lang->setting_symposium_group_conversations_desc,
			'value' => 1
		],
		'move_to_trash' => [
			'title' => $lang->setting_symposium_move_to_trash,
			'description' => $lang->setting_symposium_move_to_trash_desc,
			'value' => 0
		]
	];

	$PL->settings('symposium', $lang->setting_group_symposium, $lang->setting_group_symposium_desc, $settingsToAdd);

	$templates = [];
	$tplDirPath = dirname(__FILE__) . '/Symposium/templates';
	if (is_dir($tplDirPath)) {
		try {
			$dir = new DirectoryIterator($tplDirPath);
			foreach ($dir as $file) {
				if (!$file->isDot() && !$file->isDir() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'html') {
					$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
				}
			}
		} catch (Throwable $e) {
		}
	}
	if ($templates) {
		$PL->templates('symposium', 'Symposium', $templates);
	}

	symposium_apply_core_edits(true);

	$cssPath = dirname(__FILE__) . '/Symposium/stylesheets/symposium.css';
	$stylesheet = is_file($cssPath) ? file_get_contents($cssPath) : '';
	if (is_string($stylesheet) && $stylesheet !== '') {
		$PL->stylesheet('symposium.css', $stylesheet, [
			'private.php' => 0
		]);
	}

	if (!$db->field_exists('convid', 'privatemessages')) {
		$db->add_column('privatemessages', 'convid', 'varchar(32)');
	}

	if (!$db->field_exists('lastread', 'privatemessages')) {
		$db->add_column('privatemessages', 'lastread', 'TEXT');
	}

	if (!$db->field_exists('symposium_pm', 'users')) {
		$db->add_column('users', 'symposium_pm', "tinyint(1) NOT NULL DEFAULT '1'");
	}

	$collation = $db->build_create_table_collation();

	if (!$db->table_exists('symposium_conversations')) {
		$db->write_query("CREATE TABLE " . TABLE_PREFIX . "symposium_conversations (
			convid VARCHAR(32) NOT NULL,
			uid INT NOT NULL,
			lastpmid INT NOT NULL DEFAULT 0,
			lastuid INT NOT NULL DEFAULT 0,
			lastmessage MEDIUMTEXT,
			lastdateline INT UNSIGNED NOT NULL DEFAULT 0,
			lastread INT UNSIGNED NOT NULL DEFAULT 0,
			unread INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (convid, uid),
			KEY uid_lastdateline (uid, lastdateline)
		) ENGINE=MyISAM{$collation};");
	} else {
		$prefix = TABLE_PREFIX;
		$table = $prefix . "symposium_conversations";
		$q = $db->write_query("SHOW COLUMNS FROM {$table} LIKE 'lastdateline'");
		$col = $db->fetch_array($q);
		if (!empty($col['Type']) && stripos($col['Type'], 'int') === false) {
			$db->write_query("ALTER TABLE {$table} MODIFY lastdateline INT UNSIGNED NOT NULL DEFAULT 0");
		}
	}

	if (!$db->table_exists('symposium_conversations_metadata')) {
		$db->write_query("CREATE TABLE " . TABLE_PREFIX . "symposium_conversations_metadata (
			convid VARCHAR(32) PRIMARY KEY,
			participants TEXT,
			name TEXT,
			admins TEXT
		) ENGINE=MyISAM{$collation};");
	}

	$query = $db->simple_select('privatemessages', 'COUNT(pmid) AS total', "folder IN (1,2) AND (convid IS NULL OR convid='')");
	$count = (int)$db->fetch_field($query, 'total');

	$info = symposium_info();
	$shadePlugins = (array)$cache->read('shade_plugins');
	$shadePlugins[$info['name']] = [
		'title' => $info['name'],
		'version' => $info['version'],
		'notConvertedCount' => $count
	];
	$cache->update('shade_plugins', $shadePlugins);
}

function symposium_uninstall()
{
	global $db, $PL, $cache, $lang;

	if (!$lang->symposium) {
		$lang->load('symposium');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->symposium_pluginlibrary_missing, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$PL or require_once PLUGINLIBRARY;

	$PL->settings_delete('symposium');

	symposium_revert_core_edits(true);

	$info = symposium_info();
	$shadePlugins = (array)$cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);

	$db->drop_table('symposium_conversations');
	$db->drop_table('symposium_conversations_metadata');

	if ($db->field_exists('convid', 'privatemessages')) {
		$db->drop_column('privatemessages', 'convid');
	}

	if ($db->field_exists('lastread', 'privatemessages')) {
		$db->drop_column('privatemessages', 'lastread');
	}

	if ($db->field_exists('symposium_pm', 'users')) {
		$db->drop_column('users', 'symposium_pm');
	}

	$PL->templates_delete('symposium');
	$PL->stylesheet_delete('symposium.css');
}

function symposium_admin_config_plugins_begin()
{
	global $mybb, $db, $cache, $PL;

	if (($mybb->input['my_post_key'] ?? '') != $mybb->post_code || ($mybb->input['symposium'] ?? '') != 'convert') {
		return;
	}

	$pluginInfo = (array)$cache->read('shade_plugins');

	$PL or require_once PLUGINLIBRARY;

	$symposiumCache = (array)$PL->cache_read('symposium_converted');

	$perpage = 250;

	$insert = [];
	$participants = [];
	$updateMessages = [];

	$query = $db->simple_select('privatemessages', '*', "folder IN (1,2) AND (convid IS NULL OR convid='')", [
		'order_by' => 'pmid DESC',
		'limit' => $perpage
	]);

	while ($message = $db->fetch_array($query)) {

		$recipients = (array)my_unserialize($message['recipients']);
		$recipients = array_filter(array_unique((array)($recipients['to'] ?? [])));

		$hash = get_conversation_id($message['fromid'], $recipients);

		$groupConversation = ($recipients && count($recipients) >= 2);

		$recipient = (int)$message['uid'];

		$notConverted = !isset($symposiumCache[$hash]);

		if ($notConverted || (!$notConverted && !in_array($recipient, (array)$symposiumCache[$hash], true))) {

			if ($notConverted && $hash) {

				$symposiumCache[$hash] = [$recipient];

				if (!in_array((int)$message['fromid'], $recipients, true)) {
					$recipients[] = (int)$message['fromid'];
				}

				sort($recipients);

				$participants[$hash] = [
					'convid' => $hash,
					'participants' => implode(',', $recipients),
					'name' => ($groupConversation && !empty($message['subject'])) ? (string)$message['subject'] : ''
				];

			} else {
				$symposiumCache[$hash][] = $recipient;
			}

			$insert[$hash][$recipient] = [
				'convid' => $hash,
				'uid' => $recipient,
				'lastpmid' => (int)$message['pmid'],
				'lastmessage' => (string)$message['message'],
				'lastdateline' => (int)$message['dateline'],
				'lastuid' => (int)$message['fromid'],
				'lastread' => (int)$message['readtime'],
				'unread' => 0
			];
		}

		$whereStatement = "fromid = " . (int)$message['fromid'] . " AND recipients = '" . $db->escape_string($message['recipients']) . "' AND folder IN (1,2) AND (convid IS NULL OR convid='')";
		$fingerprint = md5($whereStatement);

		if (!isset($updateMessages[$hash][$fingerprint])) {
			$updateMessages[$hash][$fingerprint] = $whereStatement;
		}
	}

	if ($updateMessages) {
		foreach ($updateMessages as $hash => $statement) {
			foreach ($statement as $where) {
				$db->update_query('privatemessages', ['convid' => $hash], $where);
			}
		}
	}

	if ($insert) {

		$flatInsert = [];
		foreach ($insert as $hash => $rows) {
			foreach ($rows as $row) {
				$flatInsert[] = $row;
			}
		}

		if ($flatInsert) {
			$db->insert_query_multiple('symposium_conversations', $flatInsert);
		}

		if ($participants) {
			$db->insert_query_multiple('symposium_conversations_metadata', array_values($participants));
		}

		if ($symposiumCache) {
			$PL->cache_update('symposium_converted', $symposiumCache);
		}

		$affected = [];
		foreach ($insert as $hash => $rows) {
			foreach ($rows as $uid => $row) {
				$affected[(int)$uid][] = $hash;
			}
		}
		foreach ($affected as $uid => $convids) {
			update_conversations_counters((int)$uid, (array)$convids);
			update_conversations_meta((int)$uid, (array)$convids);
		}

		$query = $db->simple_select('privatemessages', 'COUNT(pmid) AS total', "folder IN (1,2) AND (convid IS NULL OR convid='')");
		$total = (int)$db->fetch_field($query, 'total');

		$pluginInfo['Symposium']['notConvertedCount'] = $total;
		$cache->update('shade_plugins', $pluginInfo);

		if ($total <= 0) {
			$PL->cache_delete('symposium_converted');
		}

		echo (int)$pluginInfo['Symposium']['notConvertedCount'];
		exit;
	}

	$PL->cache_delete('symposium_converted');
	echo 0;
	exit;
}

function symposium_plugin_edit()
{
	global $mybb;

	if (($mybb->input['my_post_key'] ?? '') != $mybb->post_code) {
		return;
	}

	if (($mybb->input['symposium'] ?? '') === 'apply') {
		if (symposium_apply_core_edits(true) === true) {
			flash_message('Successfully applied core edits.', 'success');
		} else {
			flash_message('There was an error applying core edits.', 'error');
		}
		admin_redirect('index.php?module=config-plugins');
	}

	if (($mybb->input['symposium'] ?? '') === 'revert') {
		if (symposium_revert_core_edits(true) === true) {
			flash_message('Successfully reverted core edits.', 'success');
		} else {
			flash_message('There was an error reverting core edits.', 'error');
		}
		admin_redirect('index.php?module=config-plugins');
	}
}

function symposium_apply_core_edits($apply = false)
{
	global $PL;

	$PL or require_once PLUGINLIBRARY;

	$errors = [];

	$edits = [
		[
			'search' => '$remote_avatar_notice = \'\';',
			'before' => '$plugins->run_hooks(\'global_symposium_pm_notice\');'
		]
	];

	$result = $PL->edit_core('symposium', 'global.php', $edits, $apply);
	if ($result !== true) {
		$errors[] = $result;
	}

	if ($apply) {
		$legacy = $PL->edit_core('symposium', 'inc/functions_user.php', [], true);
		if ($legacy !== true) {
			$errors[] = $legacy;
		}
	}

	if ($errors) {
		return $errors;
	}
	return true;
}

function symposium_revert_core_edits($apply = false)
{
	global $PL;

	$PL or require_once PLUGINLIBRARY;

	$PL->edit_core('symposium', 'inc/functions_user.php', [], $apply);
	return $PL->edit_core('symposium', 'global.php', [], $apply);
}

global $mybb;

$hooks = [
	'global_start',
	'global_symposium_pm_notice',
	'private_inbox',
	'private_read',
	'private_start',
	'private_do_send_end',
	'datahandler_pm_validate',
	'datahandler_pm_insert',
	'datahandler_pm_insert_commit',
	'datahandler_pm_insert_savedcopy_commit',
	'private_send_start',
	'private_send_do_send',
	'xmlhttp_get_users_end',
	'admin_user_users_delete_commit',
	'admin_config_plugins_begin',
	'xmlhttp',
	'pre_output_page',
	'private_read_end',
	'private_delete_end',
	'private_end',
	'usercp_options_end',
	'usercp_do_options_end'
];

foreach ($hooks as $hook) {
	$plugins->add_hook($hook, 'symposium_' . $hook);
}

$plugins->add_hook('usercp_menu', 'symposium_usercp_menu_messenger', 20);

function symposium_global_start()
{
	global $mybb, $lang, $templatelist;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	if ($templatelist) {
		$templatelist = explode(',', $templatelist);
	} else {
		$templatelist = [];
	}

	$lang->load('symposium');

	$templatelist[] = 'symposium_pm_notice';

	if (THIS_SCRIPT === 'private.php') {

		$templatelist[] = 'symposium_seen_icon';
		$templatelist[] = 'symposium_unseen_icon';

		if (empty($mybb->input['action'])) {
			$templatelist[] = 'symposium_conversations';
			$templatelist[] = 'symposium_conversations_empty';
			$templatelist[] = 'symposium_conversations_conversation';
			$templatelist[] = 'symposium_conversations_search_not_found';
			$templatelist[] = 'symposium_unread_count';
		}

		if (($mybb->input['action'] ?? '') === 'read') {
			$templatelist[] = 'symposium_conversation';
			$templatelist[] = 'symposium_conversation_date_divider';
			$templatelist[] = 'symposium_conversation_message_external';
			$templatelist[] = 'symposium_conversation_message_external_group';
			$templatelist[] = 'symposium_conversation_message_external_group_avatar';
			$templatelist[] = 'symposium_conversation_message_personal';
			$templatelist[] = 'symposium_conversation_posting_area';
			$templatelist[] = 'symposium_conversation_no_messages';
		}

		if (($mybb->input['action'] ?? '') === 'send') {
			$templatelist[] = 'symposium_create_autocomplete';
			$templatelist[] = 'symposium_create_conversation';
		}
	}

	if (in_array(THIS_SCRIPT, ['usercp.php', 'misc.php', 'private.php'], true)) {
		$templatelist[] = 'symposium_usercp_menu';
	}

	$mybb->user['s_pmnotice'] = $mybb->user['pmnotice'] ?? 0;
	unset($mybb->user['pmnotice']);

	$templatelist = implode(',', array_filter($templatelist));
}

function symposium_pm_ui_enabled()
{
	global $mybb;

	if ((int)$mybb->user['uid'] <= 0) {
		return false;
	}

	if (isset($mybb->user['symposium_pm'])) {
		return ((int)$mybb->user['symposium_pm'] === 1);
	}

	return true;
}

function symposium_pre_output_page(&$page)
{
	if (THIS_SCRIPT !== 'private.php') {
		return;
	}

	if (symposium_pm_ui_enabled()) {
		return;
	}

	$page = preg_replace('~<link[^>]+symposium\.css[^>]*>\s*~i', '', $page);
}

function symposium_private_read_end()
{
	global $mybb, $db;

	if (symposium_pm_ui_enabled()) {
		return;
	}

	$pmid = (int)$mybb->get_input('pmid', MyBB::INPUT_INT);
	if ($pmid <= 0) {
		return;
	}

	$query = $db->simple_select('privatemessages', 'convid', "pmid='{$pmid}' AND uid='" . (int)$mybb->user['uid'] . "'", ['limit' => 1]);
	$convid = $db->fetch_field($query, 'convid');

	if (!$convid) {
		return;
	}

	update_conversations_counters((int)$mybb->user['uid'], [$convid]);
	update_conversations_meta((int)$mybb->user['uid'], [$convid]);
}

function symposium_private_delete_end()
{
	global $mybb, $db;

	if (symposium_pm_ui_enabled()) {
		return;
	}

	$uid = (int)$mybb->user['uid'];
	if ($uid <= 0) {
		return;
	}

	update_conversations_counters($uid);

	$convids = [];
	$query = $db->simple_select('symposium_conversations', 'convid', "uid='{$uid}'");
	while ($convid = $db->fetch_field($query, 'convid')) {
		$convids[] = $convid;
	}

	if ($convids) {
		update_conversations_meta($uid, $convids);
	}
}

function symposium_private_end()
{
	global $mybb, $db;

	if (symposium_pm_ui_enabled()) {
		return;
	}

	if ($mybb->request_method !== 'post') {
		return;
	}

	if (!in_array($mybb->input['action'] ?? '', ['do_stuff', 'do_empty'], true)) {
		return;
	}

	$uid = (int)$mybb->user['uid'];
	if ($uid <= 0) {
		return;
	}

	update_conversations_counters($uid);

	$convids = [];
	$query = $db->simple_select('symposium_conversations', 'convid', "uid='{$uid}'");
	while ($convid = $db->fetch_field($query, 'convid')) {
		$convids[] = $convid;
	}

	if ($convids) {
		update_conversations_meta($uid, $convids);
	}
}

function symposium_usercp_options_end()
{
	global $mybb, $user, $pms, $lang;

	if ((int)$mybb->user['uid'] <= 0) {
		return;
	}

	if (!$lang->symposium) {
		$lang->load('symposium');
	}

	if (!is_string($pms) || $pms === '') {
		return;
	}

	$current = 1;
	if (isset($user['symposium_pm'])) {
		$current = (int)$user['symposium_pm'];
	} elseif (isset($mybb->user['symposium_pm'])) {
		$current = (int)$mybb->user['symposium_pm'];
	}

	$checked = ($current === 1) ? ' checked="checked"' : '';

	$label = $lang->symposium_usercp_enable;
	$desc = $lang->symposium_usercp_enable_desc;

	$row = '<tr>
<td class="trow1" valign="top" width="1"><input type="checkbox" class="checkbox" name="symposium_pm" value="1"' . $checked . ' /></td>
<td class="trow1"><span class="smalltext"><label>' . $label . '</label><br />' . $desc . '</span></td>
</tr>';

	if (stripos($pms, '</tbody>') !== false) {
		$pms = preg_replace('~</tbody>~i', $row . '</tbody>', $pms, 1);
	} elseif (stripos($pms, '</table>') !== false) {
		$pms = preg_replace('~</table>~i', $row . '</table>', $pms, 1);
	} else {
		$pms .= $row;
	}
}

function symposium_usercp_do_options_end()
{
	global $mybb, $db;

	if ((int)$mybb->user['uid'] <= 0) {
		return;
	}

	if (!$db->field_exists('symposium_pm', 'users')) {
		return;
	}

	$val = ($mybb->get_input('symposium_pm', MyBB::INPUT_INT) === 1) ? 1 : 0;

	$db->update_query('users', ['symposium_pm' => $val], "uid='" . (int)$mybb->user['uid'] . "'");
	$mybb->user['symposium_pm'] = $val;
}

function symposium_global_symposium_pm_notice()
{
	global $mybb, $lang, $db, $pm_notice, $templates;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	$prefix = TABLE_PREFIX;

	$query = $db->query("
		SELECT c.convid, c.unread, m.participants, m.name
		FROM {$prefix}symposium_conversations c
		LEFT JOIN {$prefix}symposium_conversations_metadata m ON (c.convid = m.convid)
		WHERE c.uid = " . (int)$mybb->user['uid'] . " AND c.unread > 0
		ORDER BY c.unread DESC
	");

	$conversationsToRead = [];
	$conversations = [];
	$participants = [];
	$users = [];
	$sum = 0;

	while ($conversation = $db->fetch_array($query)) {

		$localParticipants = (array)explode(',', (string)$conversation['participants']);

		if (($key = array_search((string)$mybb->user['uid'], $localParticipants, true)) !== false) {
			unset($localParticipants[$key]);
		}
		if (($key = array_search((int)$mybb->user['uid'], $localParticipants, true)) !== false) {
			unset($localParticipants[$key]);
		}

		$localParticipants = array_filter(array_map('intval', $localParticipants));

		if ($localParticipants) {
			$conversation['recipient'] = (int)reset($localParticipants);
			$participants = array_merge($participants, $localParticipants);
		}

		$sum += (int)$conversation['unread'];
		$conversations[] = $conversation;
	}

	if (!$conversations) {
		return;
	}

	$participants = array_values(array_unique(array_filter($participants)));

	if ($participants) {
		$query = $db->simple_select('users', 'uid, username, usergroup, displaygroup', 'uid IN (' . implode(',', $participants) . ')');
		while ($u = $db->fetch_array($query)) {
			$users[(int)$u['uid']] = $u;
		}
	}

	foreach ($conversations as $conversation) {

		$name = '';

		if (!empty($conversation['name'])) {
			$name = htmlspecialchars_uni($conversation['name']);
		} elseif (!empty($conversation['recipient']) && !empty($users[(int)$conversation['recipient']])) {
			$target = $users[(int)$conversation['recipient']];
			$name = format_name($target['username'], $target['usergroup'], $target['displaygroup']);
		}

		if ($name !== '') {
			$conversationsToRead[] = '<a href="private.php?action=read&amp;convid=' . $conversation['convid'] . '">' . $name . ' (' . (int)$conversation['unread'] . ')</a>';
		}
	}

	if (!$conversationsToRead) {
		return;
	}

	$count = count($conversations);
	$convLabel = ($count > 1) ? $lang->symposium_pm_notice_conversations : $lang->symposium_pm_notice_conversation;
	$messagesLabel = ($sum > 1) ? $lang->symposium_pm_notice_messages : $lang->symposium_pm_notice_message;

	$text = $lang->sprintf($lang->symposium_pm_notice, $count, $convLabel, $sum, $messagesLabel, implode(', ', $conversationsToRead));

	eval("\$pm_notice = \"" . $templates->get('symposium_pm_notice') . "\";");
}

function symposium_private_inbox()
{
	global $mybb, $templates, $header, $headerinclude, $theme, $footer, $db, $usercpnav, $lang;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;
	$parserOptions = [
		'allow_html' => $mybb->settings['pmsallowhtml'],
		'allow_mycode' => $mybb->settings['pmsallowmycode'],
		'allow_smilies' => $mybb->settings['pmsallowsmilies'],
		'allow_imgcode' => $mybb->settings['pmsallowimgcode'],
		'allow_videocode' => $mybb->settings['pmsallowvideocode'],
		'filter_badwords' => 1
	];

	$where = '';
	$searchMultipage = '';
	$search = false;

	if (!empty($mybb->input['search'])) {

		verify_post_check($mybb->input['my_post_key']);

		$conversationsInSearch = [];
		$rawSearch = (string)$mybb->get_input('search');
		$keyword = $db->escape_string($rawSearch);

		$query = $db->simple_select('users', 'uid', "username LIKE '%{$keyword}%'");
		while ($matchedUid = $db->fetch_field($query, 'uid')) {
			$conversationsInSearch[] = get_conversation_id($mybb->user['uid'], (int)$matchedUid);
		}

		if (stripos($rawSearch, 'MyBB Engine') !== false) {
			$conversationsInSearch[] = get_conversation_id($mybb->user['uid'], 0);
		}

		$query = $db->simple_select('symposium_conversations_metadata', 'convid', "name LIKE '%{$keyword}%'");
		while ($convid = $db->fetch_field($query, 'convid')) {
			$conversationsInSearch[] = $convid;
		}

		$where = ($conversationsInSearch)
			? " AND convid IN ('" . implode("','", array_map([$db, 'escape_string'], $conversationsInSearch)) . "')"
			: " AND 1=0";

		$searchMultipage = '?my_post_key=' . $mybb->post_code . '&search=' . urlencode($rawSearch);
		$search = true;
	}

	$query = $db->simple_select('symposium_conversations', 'COUNT(convid) AS total', 'uid = ' . (int)$mybb->user['uid'] . $where);
	$total = (int)$db->fetch_field($query, 'total');

	$perpage = (int)($mybb->settings['pmspage'] ?? 0);
	if ($perpage <= 0) {
		$perpage = (int)$mybb->settings['threadsperpage'];
	}

	$page = (int)$mybb->get_input('page', MyBB::INPUT_INT);

	if ($page > 0) {
		$start = ($page - 1) * $perpage;
		$pages = ($perpage > 0) ? (int)ceil($total / $perpage) : 1;
		if ($page > $pages) {
			$start = 0;
			$page = 1;
		}
	} else {
		$start = 0;
		$page = 1;
	}

	$multipage = multipage($total, $perpage, $page, 'private.php' . $searchMultipage);

	$rawConversations = [];
	$convids = [];
	$prefix = TABLE_PREFIX;

	$query = $db->query("
		SELECT *
		FROM {$prefix}symposium_conversations
		WHERE uid = " . (int)$mybb->user['uid'] . "{$where}
		ORDER BY lastdateline DESC
		LIMIT {$start}, {$perpage}
	");

	while ($conversation = $db->fetch_array($query)) {
		$rawConversations[] = $conversation;
		$convids[] = $conversation['convid'];
	}

	$participants = [];
	$conversationNames = [];
	$uids = [];

	if ($convids) {
		$query = $db->simple_select('symposium_conversations_metadata', 'convid, participants, name', "convid IN ('" . implode("','", array_map([$db, 'escape_string'], $convids)) . "')");
		while ($conversationMetadata = $db->fetch_array($query)) {
			$localParticipants = array_filter(array_map('intval', explode(',', (string)$conversationMetadata['participants'])));
			if ($localParticipants) {
				$participants[$conversationMetadata['convid']] = $localParticipants;
				$uids = array_merge($uids, $localParticipants);
			}
			if (!empty($conversationMetadata['name'])) {
				$conversationNames[$conversationMetadata['convid']] = htmlspecialchars_uni($conversationMetadata['name']);
			}
		}
	}

	$uids = array_values(array_unique(array_filter($uids)));

	$users = [
		0 => [
			'uid' => 0,
			'username' => 'MyBB Engine',
			'usergroup' => 2,
			'displaygroup' => 2,
			'avatar' => 'images/default_avatar.png'
		],
		(int)$mybb->user['uid'] => [
			'uid' => (int)$mybb->user['uid'],
			'username' => $mybb->user['username'],
			'usergroup' => $mybb->user['usergroup'],
			'displaygroup' => $mybb->user['displaygroup'],
			'avatar' => !empty($mybb->user['avatar']) ? $mybb->user['avatar'] : 'images/default_avatar.png'
		]
	];

	if ($uids) {
		$query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, avatar', 'uid IN (' . implode(',', $uids) . ')');
		while ($u = $db->fetch_array($query)) {
			$u['avatar'] = !empty($u['avatar']) ? (string)$u['avatar'] : 'images/default_avatar.png';
			$users[(int)$u['uid']] = $u;
		}
	}

	$groupPmids = [];
	foreach ($rawConversations as $c) {
		$local = $participants[$c['convid']] ?? [];
		if ($local && count($local) > 2 && (int)$c['lastuid'] === (int)$mybb->user['uid'] && (int)$c['lastpmid'] > 0) {
			$groupPmids[] = (int)$c['lastpmid'];
		}
	}
	$groupPmids = array_values(array_unique(array_filter($groupPmids)));

	$groupLastReads = [];
	if ($groupPmids) {
		$q = $db->simple_select(
			'privatemessages',
			'pmid, lastread',
			'pmid IN (' . implode(',', $groupPmids) . ") AND uid='" . (int)$mybb->user['uid'] . "' AND folder=2"
		);
		while ($row = $db->fetch_array($q)) {
			$groupLastReads[(int)$row['pmid']] = (string)$row['lastread'];
		}
	}

	$selfHash = get_conversation_id((int)$mybb->user['uid'], (int)$mybb->user['uid']);

	$conversations = '';
	if ($rawConversations) {

		foreach ($rawConversations as $conversation) {

			$currentUsers = [];
			$groupConversationSender = '';
			$local = $participants[$conversation['convid']] ?? [];
			$groupConversation = ($local && count($local) > 2);

			if ($local && $selfHash !== $conversation['convid']) {
				foreach ((array)$local as $participant) {
					$participant = (int)$participant;
					if (!empty($users[$participant]) && $participant !== (int)$mybb->user['uid']) {
						$currentUsers[] = $users[$participant];
					}
				}
			}

			if ($selfHash === $conversation['convid']) {
				$currentUsers[] = $users[(int)$mybb->user['uid']];
			}

			if (!$currentUsers) {
				$currentUsers[] = [
					'uid' => 0,
					'username' => 'Deleted user',
					'usergroup' => 2,
					'displaygroup' => 2,
					'avatar' => 'images/default_avatar.png'
				];
			}

			if (!empty($conversationNames[$conversation['convid']])) {
				$convoTitle = $conversationNames[$conversation['convid']];
				$convoAvatar = 'images/default_avatar.png';
			} elseif ($local && $groupConversation) {
				$convoTitle = implode(', ', array_map(function ($u) {
					return format_name($u['username'], $u['usergroup'], $u['displaygroup']);
				}, $currentUsers));
				$convoAvatar = 'images/default_avatar.png';
			} else {
				$convoTitle = format_name($currentUsers[0]['username'], $currentUsers[0]['usergroup'], $currentUsers[0]['displaygroup']);
				$convoAvatar = !empty($currentUsers[0]['avatar']) ? $currentUsers[0]['avatar'] : 'images/default_avatar.png';
			}

			if ($groupConversation) {
				if (!empty($conversation['lastmessage']) && (int)$conversation['lastuid'] !== (int)$mybb->user['uid'] && !empty($users[(int)$conversation['lastuid']])) {
					$senderU = $users[(int)$conversation['lastuid']];
					$groupConversationSender = format_name($senderU['username'], $senderU['usergroup'], $senderU['displaygroup']);
					$groupConversationSender = $lang->sprintf($lang->symposium_group_conversation_sender, $groupConversationSender);
				}
			}

			$date = my_date('relative', (int)$conversation['lastdateline']);

			$lastRead = '';
			if ((int)$conversation['lastuid'] === (int)$mybb->user['uid']) {

				$read = false;

				if ($groupConversation) {
					$lr = (string)($groupLastReads[(int)$conversation['lastpmid']] ?? '');
					$readers = array_values(array_unique(array_filter(array_map('intval', explode(',', $lr)))));
					$needed = array_values(array_unique(array_filter(array_map('intval', (array)$local))));
					$needed = array_diff($needed, [(int)$mybb->user['uid'], 0]);
					$remaining = array_diff($needed, $readers);
					$read = (count($remaining) === 0);
				} else {
					$read = ((int)$conversation['lastread'] >= (int)$conversation['lastdateline']);
				}

				if ($read) {
					eval("\$lastRead = \"" . $templates->get('symposium_seen_icon') . "\";");
				} else {
					eval("\$lastRead = \"" . $templates->get('symposium_unseen_icon') . "\";");
				}
			}

			$unreadCount = '';
			$highlight = '';
			if ((int)$conversation['unread'] > 0) {
				eval("\$unreadCount = \"" . $templates->get('symposium_unread_count') . "\";");
				$highlight = ' highlight';
			}

			$conversation['lastmessage'] = strip_tags($parser->text_parse_message((string)$conversation['lastmessage'], $parserOptions));

			eval("\$conversations .= \"" . $templates->get('symposium_conversations_conversation') . "\";");
		}

	} elseif ($search) {
		eval("\$conversations .= \"" . $templates->get('symposium_conversations_search_not_found') . "\";");
	} else {
		eval("\$conversations .= \"" . $templates->get('symposium_conversations_empty') . "\";");
	}

	eval("\$page = \"" . $templates->get('symposium_conversations') . "\";");
	output_page($page);
	exit;
}

function symposium_private_read()
{
	global $mybb, $templates, $header, $headerinclude, $theme, $footer, $db, $usercpnav, $lang, $errors;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	$convid = $db->escape_string((string)($mybb->input['convid'] ?? ''));

	$query = $db->simple_select('symposium_conversations', 'convid', 'convid = "' . $convid . '" AND uid = ' . (int)$mybb->user['uid'], ['limit' => 1]);
	if (!$convid || !$db->fetch_field($query, 'convid')) {
		if (!$lang->symposium) {
			$lang->load('symposium');
		}
		error($lang->symposium_error_conversation_doesnt_exist);
	}

	if ($errors) {
		$errors = inline_error($errors);
	}

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;
	$parserOptions = [
		'allow_html' => $mybb->settings['pmsallowhtml'],
		'allow_mycode' => $mybb->settings['pmsallowmycode'],
		'allow_smilies' => $mybb->settings['pmsallowsmilies'],
		'allow_imgcode' => $mybb->settings['pmsallowimgcode'],
		'allow_videocode' => $mybb->settings['pmsallowvideocode'],
		'me_username' => $mybb->user['username'],
		'filter_badwords' => 1
	];

	$query = $db->simple_select('privatemessages', 'COUNT(convid) AS total', 'convid = "' . $convid . '" AND uid = ' . (int)$mybb->user['uid']);
	$total = (int)$db->fetch_field($query, 'total');

	$perpage = (int)($mybb->settings['pmspage'] ?? 0);
	if ($perpage <= 0) {
		$perpage = (int)$mybb->settings['threadsperpage'];
	}

	$page = (!isset($mybb->input['page']) && ($mybb->input['from'] ?? '') === 'multipage')
		? 1
		: (int)$mybb->get_input('page', MyBB::INPUT_INT);

	$pages = ($perpage > 0) ? (int)ceil($total / $perpage) : 1;

	if ($page > 0) {
		if ($page > $pages) {
			$page = $pages;
		}
		$start = ($pages - $page) * $perpage;
	} else {
		$start = 0;
		$page = $pages;
	}

	$multipage = multipage($total, $perpage, $page, 'private.php?action=read&from=multipage&convid=' . $convid);

	$selfHash = get_conversation_id((int)$mybb->user['uid'], (int)$mybb->user['uid']);

	$query = $db->simple_select('symposium_conversations_metadata', '*', 'convid = "' . $convid . '"', ['limit' => 1]);
	$metadata = (array)$db->fetch_array($query);

	$users = [];
	$conversationParticipants = [];

	if (!empty($metadata['participants'])) {
		$conversationParticipants = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$metadata['participants'])))));
		if ($conversationParticipants) {
			$query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, avatar', 'uid IN (' . implode(',', $conversationParticipants) . ')');
			while ($u = $db->fetch_array($query)) {
				$u['avatar'] = !empty($u['avatar']) ? (string)$u['avatar'] : 'images/default_avatar.png';
				$users[(int)$u['uid']] = $u;
			}
		}
	}

	if (in_array(0, $conversationParticipants, true)) {
		$users[0] = [
			'uid' => 0,
			'username' => 'MyBB Engine',
			'usergroup' => 2,
			'displaygroup' => 2,
			'avatar' => 'images/default_avatar.png'
		];
	}

	$groupConversation = ($users && count($users) > 2);
	$participants = '';
	$convoTitle = '';
	$convoAvatar = '';
	$user = null;

	if ($groupConversation) {
		$usernames = [];
		foreach ($users as $_user) {
			if ((int)$_user['uid'] === (int)$mybb->user['uid']) {
				continue;
			}
			$t_username = format_name($_user['username'], $_user['usergroup'], $_user['displaygroup']);
			$usernames[] = build_profile_link($t_username, $_user['uid']);
		}

		$convoTitle = htmlspecialchars_uni((string)($metadata['name'] ?? ''));
		$convoAvatar = 'images/default_avatar.png';
		$participants = implode(', ', $usernames);
	} elseif ($selfHash !== ($metadata['convid'] ?? '')) {
		unset($users[(int)$mybb->user['uid']]);
		$user = reset($users);
	} else {
		$user = reset($users);
	}

	$replyMeta = $users;
	unset($replyMeta[(int)$mybb->user['uid']]);
	$replyUsernames = implode(', ', array_column($replyMeta, 'username'));

	if (is_array($user) && $user) {
		$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$convoTitle = build_profile_link($username, (int)$user['uid']);
		$convoAvatar = !empty($user['avatar']) ? $user['avatar'] : 'images/default_avatar.png';
	}

	if (!is_array($user)) {
		$user = [];
	}

	$user['avatar'] = !empty($user['avatar']) ? $user['avatar'] : 'images/default_avatar.png';

	add_breadcrumb($convoTitle);

	$rawMessages = [];
	$messages = '';

	$query = $db->simple_select(
		'privatemessages',
		'*',
		'((folder = 2 AND fromid = ' . (int)$mybb->user['uid'] . ') OR (folder = 1 AND uid = ' . (int)$mybb->user['uid'] . ')) AND convid = "' . $convid . '"',
		[
			'order_by' => 'pmid DESC',
			'limit_start' => $start,
			'limit' => $perpage
		]
	);

	while ($m = $db->fetch_array($query)) {
		$rawMessages[] = $m;
	}

	$rawMessages = array_reverse($rawMessages);

	$previous = ['midnight' => 0];
	$updateReadStatus = false;
	$messagesCount = count($rawMessages);
	$lastUid = 0;
	$sendersRead = [];

	foreach ($rawMessages as $message) {

		$date = my_date($mybb->settings['dateformat'], (int)$message['dateline']);
		$divider = '';
		$lastRead = '';

		if ((int)$message['dateline'] > (int)$previous['midnight']) {
			eval("\$divider = \"" . $templates->get('symposium_conversation_date_divider') . "\";");
		}

		$message['message'] = $parser->parse_message((string)$message['message'], $parserOptions);
		$time = my_date($mybb->settings['timeformat'], (int)$message['dateline']);

		$mode = ((int)$message['folder'] === 1) ? 'external' : 'personal';
		if ($groupConversation && (int)$message['folder'] === 1) {
			$mode = 'external_group';
		}

		if ($groupConversation) {

			$sender = '';
			$avatar = '';
			$u = $users[(int)$message['fromid']] ?? null;

			if ($u && (!$lastUid || $lastUid !== (int)$u['uid'] || $divider)) {

				eval("\$avatar = \"" . $templates->get('symposium_conversation_message_external_group_avatar') . "\";");

				$sender = format_name($u['username'], $u['usergroup'], $u['displaygroup']);
				$sender = build_profile_link($sender, (int)$u['uid']);
			}

			$lastUid = $u ? (int)$u['uid'] : 0;
		}

		$read = ((int)$message['readtime'] > 0);

		if ($mode === 'personal') {

			if ($groupConversation) {

				$readers = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$message['lastread'])))));
				$remaining = array_diff($conversationParticipants, $readers);
				$remaining = array_diff($remaining, [(int)$message['fromid']]);
				$read = (count($remaining) === 0);

			} else {
				$read = ((int)$message['readtime'] > 0);
			}

			if ($read) {
				eval("\$lastRead = \"" . $templates->get('symposium_seen_icon') . "\";");
			} else {
				eval("\$lastRead = \"" . $templates->get('symposium_unseen_icon') . "\";");
			}
		}

		eval("\$messages .= \"" . $templates->get("symposium_conversation_message_{$mode}") . "\";");

		$previous = [
			'midnight' => strtotime('0:00', (int)$message['dateline'] + 60 * 60 * 24)
		];

		if ((int)$message['status'] === 0 && (int)$message['folder'] === 1) {
			$updateReadStatus = true;

			if (!$groupConversation) {
				$fromid = (int)$message['fromid'];
				if ($fromid > 0 && ($fromid !== (int)$mybb->user['uid'] || $selfHash === ($metadata['convid'] ?? ''))) {
					$sendersRead[$fromid] = true;
				}
			}
		}
	}

	if ($messagesCount === 0) {
		eval("\$messages = \"" . $templates->get('symposium_conversation_no_messages') . "\";");
	}

	if ($updateReadStatus) {

		$db->update_query(
			'privatemessages',
			['status' => 1, 'readtime' => TIME_NOW],
			'convid = "' . $convid . '" AND readtime = 0 AND toid = ' . (int)$mybb->user['uid']
		);

		if ($sendersRead) {
			foreach (array_keys($sendersRead) as $fromid) {
				$db->update_query(
					'symposium_conversations',
					['lastread' => TIME_NOW],
					'convid = "' . $convid . '" AND uid = ' . (int)$fromid
				);
			}
		}

		if ($groupConversation) {
			$uid = (int)$mybb->user['uid'];
			$expr = "IF(IFNULL(lastread,'')='', '{$uid}', IF(FIND_IN_SET('{$uid}', IFNULL(lastread,'')), IFNULL(lastread,''), CONCAT(IFNULL(lastread,''), ',{$uid}')))";
			$db->update_query('privatemessages', ['lastread' => $expr], 'convid = "' . $convid . '" AND toid = 0 AND folder = 2', '', true);
		}

		if (function_exists('update_pm_count')) {
			try {
				$rf = new ReflectionFunction('update_pm_count');
				if ($rf->getNumberOfParameters() >= 1) {
					update_pm_count((int)$mybb->user['uid']);
				} else {
					update_pm_count();
				}
			} catch (Throwable $e) {
				update_pm_count();
			}
		}

		update_conversations_counters((int)$mybb->user['uid']);
		update_conversations_meta((int)$mybb->user['uid'], [$convid]);
	}

	$postingArea = '';
	if (!in_array(0, $conversationParticipants, true)) {

		$codebuttons = '';
		if ($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0) {
			$codebuttons = build_mycode_inserter('message', $mybb->settings['pmsallowsmilies']);
		}

		$message = htmlspecialchars_uni((string)($mybb->input['message'] ?? ''));

		eval("\$postingArea = \"" . $templates->get('symposium_conversation_posting_area') . "\";");
	}

	eval("\$page = \"" . $templates->get('symposium_conversation') . "\";");
	output_page($page);
	exit;
}

function symposium_private_start()
{
	global $mybb, $db, $lang, $session, $errors;

	if (!symposium_pm_ui_enabled()) {

				$action = (string)($mybb->input['action'] ?? '');

		if (in_array($action, ['new_message', 'delete_conversations'], true)) {
			header('Location: private.php');
			exit;
		}

		if ($action === 'read' && !empty($mybb->input['convid'])) {

			$convid = $db->escape_string((string)$mybb->get_input('convid'));
			$query = $db->simple_select(
				'privatemessages',
				'pmid',
				'uid=' . (int)$mybb->user['uid'] . ' AND convid="' . $convid . '"',
				['order_by' => 'pmid DESC', 'limit' => 1]
			);

			$pmid = (int)$db->fetch_field($query, 'pmid');
			if ($pmid > 0) {
				header('Location: private.php?action=read&pmid=' . $pmid);
				exit;
			}
			
			header('Location: private.php');
			exit;
		}

		return;
	}

	if (!$lang->symposium) {
		$lang->load('symposium');
	}

	$lang->nav_pms = $lang->symposium_nav_pms;

	if (!in_array(($mybb->input['action'] ?? ''), ['new_message', 'delete_conversations'], true)) {
		return;
	}

	if ((int)$mybb->usergroup['cansendpms'] === 0) {
		error_no_permission();
	}

	verify_post_check($mybb->get_input('my_post_key'));

	if (($mybb->input['action'] ?? '') === 'new_message') {

		$convid = $db->escape_string((string)($mybb->input['convid'] ?? ''));

		$timeCutoff = TIME_NOW - (5 * 60);
		$query = $db->simple_select('privatemessages', 'pmid', "convid = '{$convid}' AND dateline > {$timeCutoff} AND fromid='" . (int)$mybb->user['uid'] . "' AND subject='" . $db->escape_string((string)$mybb->get_input('subject')) . "' AND message='" . $db->escape_string((string)$mybb->get_input('message')) . "' AND folder!='3'", [
			'limit' => 1
		]);
		if ($db->fetch_field($query, 'pmid')) {
			error($lang->error_pm_already_submitted);
		}

		require_once MYBB_ROOT . 'inc/datahandlers/pm.php';
		$pmhandler = new PMDataHandler();

		$pm = [
			'subject' => $mybb->get_input('subject'),
			'message' => $mybb->get_input('message'),
			'fromid' => (int)$mybb->user['uid'],
			'do' => $mybb->get_input('do'),
			'pmid' => (int)$mybb->get_input('pmid', MyBB::INPUT_INT),
			'ipaddress' => $session->packedip,
			'convid' => $convid
		];

		$pm['to'] = array_unique(array_map('trim', explode(',', (string)$mybb->get_input('to'))));

		$mybb->input['options'] = $mybb->get_input('options', MyBB::INPUT_ARRAY);

		if ((int)$mybb->usergroup['cantrackpms'] === 0) {
			$mybb->input['options']['readreceipt'] = false;
		}

		$pm['options'] = [];
		$pm['options']['signature'] = !empty($mybb->input['options']['signature']) ? 1 : 0;
		$pm['options']['savecopy'] = 1;
		$pm['options']['disablesmilies'] = $mybb->input['options']['disablesmilies'] ?? 0;
		$pm['options']['readreceipt'] = $mybb->input['options']['readreceipt'] ?? 0;
		$pm['saveasdraft'] = $mybb->input['saveasdraft'] ?? 0;

		$pmhandler->set_data($pm);

		if (!$pmhandler->validate_pm()) {
			$errors = $pmhandler->get_friendly_errors();
			$mybb->input['action'] = 'read';
		} else {
			$pmhandler->insert_pm();
			redirect("private.php?action=read&convid={$convid}", $lang->redirect_pmsent);
		}
	}

	if (($mybb->input['action'] ?? '') === 'delete_conversations') {

		$conversationsToDelete = (array)($mybb->input['toDelete'] ?? []);

		if (!$conversationsToDelete) {
			error($lang->symposium_error_no_conversation_to_delete);
		}

		$conversationsToDelete = array_map([$db, 'escape_string'], array_keys($conversationsToDelete));
		$where = 'convid IN ("' . implode('","', $conversationsToDelete) . '") AND uid = ' . (int)$mybb->user['uid'];

		$db->delete_query('symposium_conversations', $where);

		if (!empty($mybb->settings['symposium_move_to_trash'])) {
			$db->update_query('privatemessages', ['folder' => 4, 'deletetime' => TIME_NOW], $where);
		} else {
			$db->delete_query('privatemessages', $where);
		}

		update_conversations_counters((int)$mybb->user['uid']);
		update_conversations_meta((int)$mybb->user['uid'], $conversationsToDelete);

		$page = !empty($mybb->input['page']) ? '?page=' . (int)$mybb->input['page'] : '';
		redirect("private.php{$page}", $lang->symposium_success_conversations_deleted);
	}
}

function symposium_private_do_send_end()
{
	global $lang, $pmhandler;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	if (!empty($pmhandler->data['convid'])) {
		redirect('private.php?action=read&convid=' . $pmhandler->data['convid'], $lang->redirect_pmsent);
	}
}

function symposium_datahandler_pm_validate(&$argument)
{
	global $db, $mybb;

	if (empty($argument->data['recipients'])) {
		return;
	}

	$recipients = array_keys((array)$argument->data['recipients']);
	$groupConversation = ($recipients && count($recipients) >= 2);

	if ($groupConversation) {

		if (empty($mybb->settings['symposium_group_conversations'])) {
			$argument->set_error('symposium_group_conversations_disabled');
		}

		if (empty($mybb->input['conversationTitle']) && ($mybb->input['action'] ?? '') !== 'new_message') {
			if (!empty($mybb->input['subject'])) {
				$mybb->input['conversationTitle'] = $mybb->input['subject'];
			} else {
				$argument->set_error('symposium_missing_conversation_title');
			}
		}
	}

	$automaticConversationId = get_conversation_id($argument->data['fromid'], $recipients);

	if (!empty($argument->data['convid']) && $argument->data['convid'] !== $automaticConversationId) {
		$argument->set_error('symposium_tampered_data');
	}

	if (empty($argument->data['convid'])) {
		$argument->data['convid'] = $automaticConversationId;
	}

	return $argument;
}

function symposium_datahandler_pm_insert(&$argument)
{
	global $db;

	if (!empty($argument->data['convid'])) {
		$argument->pm_insert_data['convid'] = $db->escape_string($argument->data['convid']);
	}

	return $argument;
}

function symposium_datahandler_pm_insert_commit($argument)
{
	global $db, $mybb;

	if (empty($argument->pm_insert_data['convid'])) {
		return;
	}

	$prefix = TABLE_PREFIX;
	$now = TIME_NOW;

	$lastpm = 0;
	if (is_array($argument->pmid)) {
		$tmp = $argument->pmid;
		$lastpm = (int)end($tmp);
	} else {
		$lastpm = (int)$argument->pmid;
	}

	$uid = (int)$argument->pm_insert_data['uid'];
	$fromid = (int)$argument->pm_insert_data['fromid'];

	$lastmessage = $db->escape_string((string)($argument->data['message'] ?? $argument->pm_insert_data['message'] ?? ''));

	$db->query("
		INSERT INTO {$prefix}symposium_conversations
			(convid, uid, lastdateline, lastpmid, lastuid, lastmessage, unread)
		VALUES
			('{$argument->pm_insert_data['convid']}', '{$uid}', '{$now}', '{$lastpm}', '{$fromid}', '{$lastmessage}', 0)
		ON DUPLICATE KEY UPDATE
			lastdateline = '{$now}',
			lastpmid = '{$lastpm}',
			lastuid = '{$fromid}',
			lastmessage = '{$lastmessage}'
	");

	$participants = [$fromid];
	$recipients = array_keys((array)$argument->data['recipients']);
	$participants = array_merge($participants, array_map('intval', $recipients));
	$participants = array_values(array_unique(array_filter($participants)));
	sort($participants);

	$participantsStr = $db->escape_string(implode(',', $participants));

	$conversationTitle = !empty($mybb->input['conversationTitle'])
		? $db->escape_string((string)$mybb->input['conversationTitle'])
		: '';

	$db->query("
		INSERT INTO {$prefix}symposium_conversations_metadata
			(convid, participants, name)
		VALUES
			('{$argument->pm_insert_data['convid']}', '{$participantsStr}', '{$conversationTitle}')
		ON DUPLICATE KEY UPDATE
			participants = '{$participantsStr}'
	");

	update_conversations_counters($uid, [$argument->pm_insert_data['convid']]);
	update_conversations_meta($uid, [$argument->pm_insert_data['convid']]);
}

function symposium_datahandler_pm_insert_savedcopy_commit($argument)
{
	global $db;

	if (empty($argument->pm_insert_data['convid'])) {
		return;
	}

	$prefix = TABLE_PREFIX;
	$now = TIME_NOW;
	$lastpm = (int)$db->insert_id();

	$uid = (int)$argument->pm_insert_data['uid'];
	$fromid = (int)$argument->pm_insert_data['fromid'];
	$lastmessage = $db->escape_string((string)($argument->data['message'] ?? $argument->pm_insert_data['message'] ?? ''));

	$db->query("
		INSERT INTO {$prefix}symposium_conversations
			(convid, uid, lastdateline, lastpmid, lastuid, lastmessage, unread)
		VALUES
			('{$argument->pm_insert_data['convid']}', '{$uid}', '{$now}', '{$lastpm}', '{$fromid}', '{$lastmessage}', 0)
		ON DUPLICATE KEY UPDATE
			lastdateline = '{$now}',
			lastpmid = '{$lastpm}',
			lastuid = '{$fromid}',
			lastmessage = '{$lastmessage}'
	");
}

function symposium_private_send_start()
{
	global $mybb, $templates, $header, $headerinclude, $theme, $footer, $db, $usercpnav, $lang, $send_errors;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	$uid = (int)$mybb->get_input('uid', MyBB::INPUT_INT);

	$to = '';
	$errors = '';

	if ($uid) {

		$convid = get_conversation_id((int)$mybb->user['uid'], $uid);

		$query = $db->simple_select('symposium_conversations', 'uid', "uid = " . (int)$mybb->user['uid'] . " AND convid = '" . $db->escape_string($convid) . "'", ['limit' => 1]);
		if ($db->fetch_field($query, 'uid')) {
			header('Location: private.php?action=read&convid=' . $convid);
			exit;
		}
	}

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;
	$parserOptions = [
		'allow_html' => $mybb->settings['pmsallowhtml'],
		'allow_mycode' => $mybb->settings['pmsallowmycode'],
		'allow_smilies' => $mybb->settings['pmsallowsmilies'],
		'allow_imgcode' => $mybb->settings['pmsallowimgcode'],
		'allow_videocode' => $mybb->settings['pmsallowvideocode'],
		'filter_badwords' => 1
	];

	if ($send_errors) {
		$errors = $send_errors;
		$to = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', (string)$mybb->get_input('to'))))));
	}

	$message = htmlspecialchars_uni($parser->parse_badwords((string)$mybb->get_input('message')));
	$conversationTitle = htmlspecialchars_uni((string)$mybb->get_input('conversationTitle'));

	$codebuttons = '';
	if ($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0) {
		$codebuttons = build_mycode_inserter('message', $mybb->settings['pmsallowsmilies']);
	}

	if ($uid) {
		$query = $db->simple_select('users', 'username', "uid='" . (int)$uid . "'", ['limit' => 1]);
		$to = htmlspecialchars_uni((string)$db->fetch_field($query, 'username')) . ', ';
	}

	$groupConversationsAllowed = (int)($mybb->settings['symposium_group_conversations'] == 1);
	$maxParticipantsPerGroup = !empty($mybb->usergroup['maxpmrecipients']) ? (int)$mybb->usergroup['maxpmrecipients'] : 5;

	eval("\$autocompletejs = \"" . $templates->get('symposium_create_autocomplete') . "\";");
	eval("\$page = \"" . $templates->get('symposium_create_conversation') . "\";");
	output_page($page);
	exit;
}

function symposium_private_send_do_send()
{
	global $mybb, $lang;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	$mybb->input['subject'] = !empty($mybb->input['conversationTitle'])
		? $mybb->input['conversationTitle']
		: ($lang->symposium_conversation_with . ' ' . $mybb->user['username']);

	$mybb->input['options'] = [
		'savecopy' => 1
	];
}

function symposium_xmlhttp_get_users_end()
{
	global $data, $db, $mybb;

	if (!$data) {
		return;
	}

	$map = [];
	$convids = [];
	$conversationMap = [];

	foreach ($data as $key => $user) {
		$map[(int)$user['uid']] = $key;
		$convid = get_conversation_id((int)$mybb->user['uid'], (int)$user['uid']);
		$conversationMap[$convid] = (int)$user['uid'];
		$convids[] = $convid;
	}

	if (!$convids) {
		return;
	}

	$query = $db->simple_select('symposium_conversations', 'convid', 'convid IN ("' . implode('","', array_map([$db, 'escape_string'], $convids)) . '") AND uid = ' . (int)$mybb->user['uid']);
	while ($convid = $db->fetch_field($query, 'convid')) {

		$target = null;
		if (!empty($conversationMap[$convid])) {
			$uid = (int)$conversationMap[$convid];
			$target = $map[$uid] ?? null;
		}

		if ($target !== null && isset($data[$target])) {
			$data[$target]['convid'] = $convid;
		}
	}
}

function symposium_usercp_menu_messenger()
{
	global $templates, $usercpmenu, $mybb;

	static $done = false;
	if ($done) {
		return;
	}
	$done = true;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	if (isset($usercpmenu) && is_string($usercpmenu) && $usercpmenu !== '') {
		$usercpmenu = preg_replace('~<!--\s*start:\s*usercp_nav_messenger\s*-->.*?<!--\s*end:\s*usercp_nav_messenger\s*-->~is', '', $usercpmenu);
	}

	eval("\$usercpmenu .= \"" . $templates->get('symposium_usercp_menu') . "\";");
}

function symposium_admin_user_users_delete_commit()
{
	global $db, $user;

	$db->delete_query('symposium_conversations', "uid = '" . (int)$user['uid'] . "'");

	$prefix = TABLE_PREFIX;
	$db->query("
		DELETE m
		FROM {$prefix}symposium_conversations_metadata m
		LEFT JOIN {$prefix}symposium_conversations c ON (m.convid = c.convid)
		WHERE c.convid IS NULL
	");
}

function symposium_xmlhttp()
{
	global $db, $mybb, $lang, $charset;

	if (!symposium_pm_ui_enabled()) {
		return;
	}

	if ($mybb->request_method !== 'post' || ($mybb->input['action'] ?? '') !== 'symposium_delete_pms' || empty($mybb->input['pmids'])) {
		return;
	}

	if (!$lang->symposium) {
		$lang->load('symposium');
	}

	header("Content-type: application/json; charset={$charset}");

	if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
		xmlhttp_error($lang->invalid_post_code);
	}

	$pmids = array_values(array_unique(array_filter(array_map('intval', (array)$mybb->input['pmids']))));
	if (!$pmids) {
		echo json_encode(['errors' => [$lang->symposium_generic_error_deleting_messages]]);
		exit;
	}

	$search = implode(',', $pmids);
	$convid = $db->escape_string((string)($mybb->input['convid'] ?? ''));

	$deletepms = [];
	$query = $db->simple_select('privatemessages', 'pmid', "pmid IN ({$search}) AND uid='" . (int)$mybb->user['uid'] . "' AND convid = '{$convid}'", ['order_by' => 'pmid']);
	while ($delpm = $db->fetch_array($query)) {
		$deletepms[(int)$delpm['pmid']] = (int)$delpm['pmid'];
	}

	if (!$deletepms) {
		echo json_encode(['errors' => [$lang->symposium_generic_error_deleting_messages]]);
		exit;
	}

	$toDelete = implode(',', $deletepms);

	if (!empty($mybb->settings['symposium_move_to_trash'])) {
		$db->update_query('privatemessages', ['folder' => 4, 'deletetime' => TIME_NOW], "pmid IN ({$toDelete}) AND uid='" . (int)$mybb->user['uid'] . "' AND convid = '{$convid}'");
	} else {
		$db->delete_query('privatemessages', "pmid IN ({$toDelete}) AND uid='" . (int)$mybb->user['uid'] . "' AND convid = '{$convid}'");
	}

	require_once MYBB_ROOT . 'inc/functions_user.php';

	if (function_exists('update_pm_count')) {
		try {
			$rf = new ReflectionFunction('update_pm_count');
			if ($rf->getNumberOfParameters() >= 1) {
				update_pm_count((int)$mybb->user['uid']);
			} else {
				update_pm_count();
			}
		} catch (Throwable $e) {
			update_pm_count();
		}
	}

	update_conversations_meta((int)$mybb->user['uid'], [$convid]);
	update_conversations_counters((int)$mybb->user['uid'], [$convid]);

	echo json_encode([
		'success' => 1,
		'message' => $lang->symposium_messages_deleted_successfully
	]);
	exit;
}

function get_conversation_id()
{
	$arguments = func_get_args();
	$relationship = [];

	foreach ($arguments as $arg) {
		if (is_array($arg)) {
			$relationship = array_merge($relationship, array_map('intval', $arg));
		} else {
			$relationship[] = (int)$arg;
		}
	}

	$relationship = array_values(array_unique($relationship));
	sort($relationship);

	return md5(serialize($relationship));
}

function update_conversations_counters(int $uid, array $convids = [])
{
	global $db;

	$uid = (int)$uid;
	if ($uid <= 0) {
		return;
	}

	$convids = array_values(array_filter(array_unique(array_map([$db, 'escape_string'], $convids))));
	$extraSql = ($convids) ? " AND convid IN ('" . implode("','", $convids) . "')" : '';

	$resetWhere = 'uid = ' . $uid . $extraSql;
	$db->update_query('symposium_conversations', ['unread' => 0], $resetWhere);

	$query = $db->simple_select('privatemessages', 'COUNT(pmid) as unread, convid', 'uid = ' . $uid . ' AND folder = 1 AND status = 0' . $extraSql, [
		'group_by' => 'convid'
	]);

	while ($conversation = $db->fetch_array($query)) {
		$db->update_query('symposium_conversations', ['unread' => (int)$conversation['unread']], 'uid = ' . $uid . ' AND convid = "' . $db->escape_string((string)$conversation['convid']) . '"');
	}
}

function update_conversations_meta(int $uid, array $convids = [])
{
	global $db;

	$uid = (int)$uid;
	if ($uid <= 0) {
		return;
	}

	$convids = array_values(array_filter(array_unique(array_map([$db, 'escape_string'], $convids))));
	if (!$convids) {
		return;
	}

	$current = [];
	$q = $db->simple_select('symposium_conversations', 'convid, lastread', "uid={$uid} AND convid IN ('" . implode("','", $convids) . "')");
	while ($row = $db->fetch_array($q)) {
		$current[(string)$row['convid']] = (int)$row['lastread'];
	}

	$lastPmids = [];
	$q = $db->simple_select(
		'privatemessages',
		'convid, MAX(pmid) AS lastpmid',
		"uid={$uid} AND folder IN (1,2) AND convid IN ('" . implode("','", $convids) . "')",
		['group_by' => 'convid']
	);

	while ($row = $db->fetch_array($q)) {
		if (!empty($row['lastpmid'])) {
			$lastPmids[(string)$row['convid']] = (int)$row['lastpmid'];
		}
	}

	if ($lastPmids) {

		$toFetch = implode(',', array_values($lastPmids));
		$q = $db->simple_select('privatemessages', 'pmid, convid, message, fromid, dateline', "pmid IN ({$toFetch}) AND uid={$uid}");

		$processed = [];
		while ($lastPm = $db->fetch_array($q)) {

			$convid = (string)$lastPm['convid'];
			$lastuid = (int)$lastPm['fromid'];

			$lastread = 0;
			if ($lastuid === $uid) {
				$lastread = (int)($current[$convid] ?? 0);
			}

			$db->update_query('symposium_conversations', [
				'lastmessage' => (string)$lastPm['message'],
				'lastpmid' => (int)$lastPm['pmid'],
				'lastuid' => $lastuid,
				'lastdateline' => (int)$lastPm['dateline'],
				'lastread' => $lastread
			], "convid = '" . $db->escape_string($convid) . "' AND uid = {$uid}");

			$processed[] = $convid;
		}

		$diff = array_diff($convids, $processed);
		foreach ($diff as $convid) {
			$db->update_query('symposium_conversations', [
				'lastmessage' => '',
				'lastpmid' => 0,
				'lastuid' => 0,
				'lastdateline' => 0,
				'lastread' => 0
			], "convid = '" . $db->escape_string((string)$convid) . "' AND uid = {$uid}");
		}

	} else {

		foreach ($convids as $convid) {
			$db->update_query('symposium_conversations', [
				'lastmessage' => '',
				'lastpmid' => 0,
				'lastuid' => 0,
				'lastdateline' => 0,
				'lastread' => 0
			], "convid = '" . $db->escape_string((string)$convid) . "' AND uid = {$uid}");
		}
	}
}
