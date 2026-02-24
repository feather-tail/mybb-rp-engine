<?php
if(!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('global_start', 'positivity_global_start');

$plugins->add_hook('reputation_do_add_start', 'positivity_reputation_do_add_start');
$plugins->add_hook('reputation_do_add_end', 'positivity_reputation_do_add_end');
$plugins->add_hook('reputation_delete_end', 'positivity_reputation_delete_end');

$plugins->add_hook('class_moderation_delete_post_start', 'positivity_moderation_delete_post_start');
$plugins->add_hook('class_moderation_delete_post', 'positivity_moderation_delete_post_end');
$plugins->add_hook('class_moderation_delete_thread_start', 'positivity_moderation_delete_thread_start');
$plugins->add_hook('class_moderation_delete_thread', 'positivity_moderation_delete_thread_end');

$plugins->add_hook('postbit', 'positivity_postbit');
$plugins->add_hook('member_profile_end', 'positivity_member_profile_end');
$plugins->add_hook('postbit_prev', 'positivity_postbit');
$plugins->add_hook('memberlist_user', 'positivity_memberlist_user');
$plugins->add_hook('usercp_end', 'positivity_usercp_end');

function positivity_info()
{
	return array(
		'name'          => 'Positivity',
		'description'   => 'Добавляет показатель «Позитив» — сумму репутации, которую пользователь поставил другим (опционально: только плюсы). Включает страницу истории positivity.php.',
		'website'       => '',
		'author'        => 'Feathertail',
		'authorsite'    => '',
		'version'       => '1.0.4',
		'compatibility' => '18*'
	);
}

function positivity_is_installed()
{
	global $db;
	return (bool)$db->field_exists('positiv', 'users');
}

function positivity_install()
{
	global $db;

	if(!$db->field_exists('positiv', 'users'))
	{
		$db->add_column('users', 'positiv', "int NOT NULL DEFAULT '0'");
	}

	$query = $db->simple_select('settinggroups', 'gid', "name='Positivity'");
	$existing_gid = (int)$db->fetch_field($query, 'gid');

	if(!$existing_gid)
	{
		$group = array(
			'name'        => 'Positivity',
			'title'       => 'Positivity',
			'description' => 'Настройки плагина «Позитив».',
			'disporder'   => 50,
			'isdefault'   => 0
		);
		$gid = (int)$db->insert_query('settinggroups', $group);
	}
	else
	{
		$gid = $existing_gid;
	}

	$settings = array();

	$settings[] = array(
		'name'        => 'positivity_enabled',
		'title'       => 'Включить «Позитив»',
		'description' => 'Глобальное включение функционала.',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 10,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_count_mode',
		'title'       => 'Учитывать',
		'description' => 'Только плюсы или плюсы+минусы (signed).',
		'optionscode' => "select\npositive_only=Только плюсы\nsigned=Плюсы и минусы",
		'value'       => 'signed',
		'disporder'   => 20,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_page_access',
		'title'       => 'Доступ к странице истории',
		'description' => 'Кто может просматривать positivity.php?uid=...',
		'optionscode' => "select\nall=Все\nregistered=Только зарегистрированные\nmods=Только модераторы\nowner=Только владелец профиля",
		'value'       => 'all',
		'disporder'   => 30,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_show_postbit',
		'title'       => 'Показывать в постбите',
		'description' => 'Добавлять показатель позитива в блок репутации поста.',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 40,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_show_profile',
		'title'       => 'Показывать в профиле',
		'description' => 'Добавлять показатель позитива в профиле пользователя.',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 50,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_show_memberlist',
		'title'       => 'Показывать в списке пользователей',
		'description' => 'Добавлять показатель позитива рядом с репутацией на memberlist.php.',
		'optionscode' => 'yesno',
		'value'       => '0',
		'disporder'   => 60,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_show_plus_sign',
		'title'       => 'Показывать знак +',
		'description' => 'Добавлять + перед положительным значением.',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 70,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_history_perpage',
		'title'       => 'История: записей на страницу',
		'description' => 'Сколько записей показывать на странице истории.',
		'optionscode' => 'numeric',
		'value'       => '15',
		'disporder'   => 80,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_history_default_sort',
		'title'       => 'История: сортировка по умолчанию',
		'description' => 'Если sort не передан.',
		'optionscode' => "select\ndateline_desc=Сначала новые\ndateline_asc=Сначала старые\nvalue_desc=Сначала больший вклад\nvalue_asc=Сначала меньший вклад\nusername_asc=По имени получателя",
		'value'       => 'dateline_desc',
		'disporder'   => 90,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_history_show_comments',
		'title'       => 'История: показывать комментарии',
		'description' => 'Показывать текст комментария к репутации.',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 100,
		'gid'         => $gid
	);

	$settings[] = array(
		'name'        => 'positivity_history_show_postlink',
		'title'       => 'История: показывать ссылку на пост',
		'description' => 'Если запись привязана к pid, показывать ссылку на пост (с учётом прав просмотра).',
		'optionscode' => 'yesno',
		'value'       => '1',
		'disporder'   => 110,
		'gid'         => $gid
	);

	foreach($settings as $s)
	{
		$query = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s['name'])."'", array('limit' => 1));
		$sid = (int)$db->fetch_field($query, 'sid');
		if($sid)
		{
			$db->update_query('settings', $s, "sid='{$sid}'");
		}
		else
		{
			$db->insert_query('settings', $s);
		}
	}

	rebuild_settings();

	positivity_upsert_templates();
}

function positivity_uninstall()
{
	global $db;

	$db->delete_query('templates', "title IN ('positivity_block','positivity_profile_row','positivity_history','positivity_history_vote','positivity_history_no_votes')");

	$query = $db->simple_select('settinggroups', 'gid', "name='Positivity'", array('limit' => 1));
	$gid = (int)$db->fetch_field($query, 'gid');
	if($gid)
	{
		$db->delete_query('settings', "gid='{$gid}'");
		$db->delete_query('settinggroups', "gid='{$gid}'");
		rebuild_settings();
	}

	if($db->field_exists('positiv', 'users'))
	{
		$db->drop_column('users', 'positiv');
	}
}

function positivity_activate()
{
	positivity_upsert_templates();
}

function positivity_deactivate() {}

function positivity_upsert_templates()
{
	global $db;

	$templates = array();

	$templates['positivity_block'] =
'<br /><span class="smalltext"><strong>{$lang->positivity_label}:</strong> <a href="{$positivity_url}"><strong class="{$positivity_class}">{$positivity_value}</strong></a></span>';

	$templates['positivity_profile_row'] =
'<tr>
	<td class="trow1"><strong>{$lang->positivity_label}:</strong></td>
	<td class="trow1"><a href="{$positivity_url}"><strong class="{$positivity_class}">{$positivity_value}</strong></a></td>
</tr>';

	$templates['positivity_history'] =
'<html>
<head>
<title>{$lang->positivity_report_for_user}</title>
{$headerinclude}
</head>
<body>
{$header}
{$multipage}

<table border="0" cellspacing="0" cellpadding="5" class="tborder tfixed clear">
<tr>
	<td class="thead"><strong>{$lang->positivity_report_for_user}</strong></td>
</tr>
<tr>
	<td class="tcat"><strong>{$lang->positivity_summary}</strong></td>
</tr>
<tr>
	<td class="trow1">
	<table width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td>
				<span class="largetext"><strong>{$username}</strong></span><br />
				<span class="smalltext">
					({$usertitle})<br>
					<br>
					<strong>{$lang->positivity_total}:</strong> <span class="repbox {$total_class}">{$rep_total}</span><br><br>
					<strong>{$lang->positivity_given_members}: {$rep_members}</strong><br>
					<strong>{$lang->positivity_given_posts}: {$rep_posts}</strong>
				</span>
			</td>
			<td align="right" style="width: 300px;">
				<table border="0" cellspacing="0" cellpadding="5" class="tborder trow2">
					<tr>
						<td>&nbsp;</td>
						<td><span class="smalltext reputation_positive">{$lang->positivity_pos_plural}</span></td>
						<td><span class="smalltext reputation_neutral">{$lang->positivity_neu_plural}</span></td>
						<td><span class="smalltext reputation_negative">{$lang->positivity_neg_plural}</span></td>
					</tr>
					<tr>
						<td style="text-align: right;"><span class="smalltext">{$lang->positivity_period_week}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_positive_week}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_neutral_week}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_negative_week}</span></td>
					</tr>
					<tr>
						<td style="text-align: right;"><span class="smalltext">{$lang->positivity_period_month}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_positive_month}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_neutral_month}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_negative_month}</span></td>
					</tr>
					<tr>
						<td style="text-align: right;"><span class="smalltext">{$lang->positivity_period_6months}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_positive_6months}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_neutral_6months}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_negative_6months}</span></td>
					</tr>
					<tr>
						<td style="text-align: right;"><span class="smalltext">{$lang->positivity_period_all}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_positive_count}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_neutral_count}</span></td>
						<td style="text-align: center;"><span class="smalltext">{$f_negative_count}</span></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	</td>
</tr>

<tr>
	<td class="tcat"><strong>{$lang->positivity_comments}</strong></td>
</tr>

{$reputation_votes}

<tr>
	<td class="tfoot" align="right">
	<form action="positivity.php" method="get">
		<input type="hidden" name="uid" value="{$user_uid}">
		<select name="show">
			<option value="all" {$show_selected[\'all\']}>{$lang->positivity_show_all}</option>
			<option value="positive" {$show_selected[\'positive\']}>{$lang->positivity_show_positive}</option>
			<option value="neutral" {$show_selected[\'neutral\']}>{$lang->positivity_show_neutral}</option>
			<option value="negative" {$show_selected[\'negative\']}>{$lang->positivity_show_negative}</option>
		</select>
		<select name="sort">
			<option value="dateline" {$sort_selected[\'dateline\']}>{$lang->positivity_sort_dateline}</option>
			<option value="username" {$sort_selected[\'username\']}>{$lang->positivity_sort_username}</option>
			<option value="value" {$sort_selected[\'value\']}>{$lang->positivity_sort_value}</option>
		</select>
		<input type="submit" class="button" value="{$lang->positivity_do}">
	</form>
	</td>
</tr>
</table>

{$multipage}
{$footer}
</body>
</html>';

	$templates['positivity_history_vote'] =
'<tr>
	<td class="trow1 {$status_class}" id="rid{$rid}">
		{$target_line}
		<br>
		{$vote_line}
	</td>
</tr>';

	$templates['positivity_history_no_votes'] =
'<tr>
	<td class="trow1" align="center"><span class="smalltext">{$lang->positivity_no_votes}</span></td>
</tr>';

	foreach($templates as $title => $tpl)
	{
		$escTitle = $db->escape_string($title);
		$query = $db->simple_select('templates', 'tid', "title='{$escTitle}' AND sid='-1'", array('limit' => 1));
		$tid = (int)$db->fetch_field($query, 'tid');

		$data = array(
			'title'    => $escTitle,
			'template' => $db->escape_string($tpl),
			'sid'      => -1,
			'version'  => '1800',
			'dateline' => TIME_NOW
		);

		if($tid)
		{
			$db->update_query('templates', $data, "tid='{$tid}'");
		}
		else
		{
			$db->insert_query('templates', $data);
		}
	}
}

function positivity_global_start()
{
	global $lang;
	if(!isset($lang->positivity_label))
	{
		$lang->load('positivity');
	}
	if(!isset($lang->positivity_no_votes) || $lang->positivity_no_votes === '')
	{
		$lang->positivity_no_votes = 'Голосов пока нет.';
	}
}

function positivity_should_run(): bool
{
	global $mybb;
	return !empty($mybb->settings['positivity_enabled']);
}

function positivity_effect_value(int $rep): int
{
	global $mybb;

	$mode = $mybb->settings['positivity_count_mode'] ?? 'signed';
	if($mode === 'positive_only')
	{
		return ($rep > 0) ? $rep : 0;
	}
	return $rep;
}

function positivity_apply_delta(int $giver_uid, int $delta): void
{
	global $db;

	if($giver_uid <= 0 || $delta === 0) {
		return;
	}

	$delta = (int)$delta;
	$db->update_query('users', array(
		'positiv' => "positiv + ({$delta})"
	), "uid='{$giver_uid}'", 1, true);
}

function positivity_fetch_existing_rep(int $giver_uid, int $target_uid, int $pid): ?array
{
	global $db, $mybb;

	if($giver_uid <= 0 || $target_uid <= 0) {
		return null;
	}

	$multirep = (int)($mybb->settings['multirep'] ?? 0);

	if($multirep === 1 && $pid === 0) {
		return null;
	}

	$query = $db->simple_select(
		'reputation',
		'rid, adduid, uid, pid, reputation',
		"adduid='{$giver_uid}' AND uid='{$target_uid}' AND pid='{$pid}'",
		array('limit' => 1)
	);

	$row = $db->fetch_array($query);
	return $row ?: null;
}

function positivity_reputation_do_add_start()
{
	global $mybb, $uid, $existing_reputation;

	if(!positivity_should_run()) {
		return;
	}

	if($mybb->request_method !== 'post') {
		return;
	}

	$giver_uid = (int)$mybb->user['uid'];
	if($giver_uid <= 0) {
		return;
	}

	$target_uid = (int)$uid;
	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

	$GLOBALS['positivity_rep_ctx'] = array(
		'giver_uid' => $giver_uid,
		'target_uid' => $target_uid,
		'pid' => $pid,
		'old_effect' => 0,
		'skip_end' => 0
	);

	if(!empty($mybb->input['delete']))
	{
		$old = null;

		if(!empty($existing_reputation) && isset($existing_reputation['reputation'], $existing_reputation['adduid']))
		{
			$old = $existing_reputation;
		}
		else
		{
			$old = positivity_fetch_existing_rep($giver_uid, $target_uid, $pid);
		}

		if($old && isset($old['reputation']))
		{
			$delta = -positivity_effect_value((int)$old['reputation']);
			positivity_apply_delta($giver_uid, $delta);
		}

		$GLOBALS['positivity_rep_ctx']['skip_end'] = 1;
		return;
	}

	$old = null;

	if(!empty($existing_reputation) && isset($existing_reputation['reputation'], $existing_reputation['adduid']))
	{
		$old = $existing_reputation;
	}
	else
	{
		$old = positivity_fetch_existing_rep($giver_uid, $target_uid, $pid);
	}

	if($old && isset($old['reputation']))
	{
		$GLOBALS['positivity_rep_ctx']['old_effect'] = positivity_effect_value((int)$old['reputation']);
	}
}

function positivity_reputation_do_add_end()
{
	global $reputation, $existing_reputation;

	if(!positivity_should_run()) {
		return;
	}

	if(!is_array($reputation)) {
		return;
	}

	$ctx = $GLOBALS['positivity_rep_ctx'] ?? null;
	if(is_array($ctx) && !empty($ctx['skip_end'])) {
		return;
	}

	$giver_uid = (int)($reputation['adduid'] ?? 0);
	if($giver_uid <= 0) {
		return;
	}

	$new_rep = (int)($reputation['reputation'] ?? 0);
	$new_effect = positivity_effect_value($new_rep);

	$old_effect = null;

	if(is_array($ctx) && array_key_exists('old_effect', $ctx))
	{
		$old_effect = (int)$ctx['old_effect'];
	}
	elseif(!empty($existing_reputation) && isset($existing_reputation['reputation']))
	{
		$old_effect = positivity_effect_value((int)$existing_reputation['reputation']);
	}
	else
	{
		$old_effect = 0;
	}

	$delta = $new_effect - $old_effect;
	positivity_apply_delta($giver_uid, $delta);
}

function positivity_reputation_delete_end()
{
	global $existing_reputation;

	if(!positivity_should_run()) {
		return;
	}

	if(empty($existing_reputation) || !isset($existing_reputation['adduid'], $existing_reputation['reputation'])) {
		return;
	}

	$giver_uid = (int)$existing_reputation['adduid'];
	$old_rep   = (int)$existing_reputation['reputation'];

	$delta = -positivity_effect_value($old_rep);
	positivity_apply_delta($giver_uid, $delta);
}

function positivity_collect_recount_uids_from_pid(int $pid): void
{
	global $db;

	if($pid <= 0) {
		return;
	}

	$uids = array();
	$q = $db->simple_select('reputation', 'DISTINCT adduid AS uid', "pid='{$pid}'");
	while($row = $db->fetch_array($q))
	{
		$u = (int)$row['uid'];
		if($u > 0) $uids[$u] = 1;
	}

	if(!isset($GLOBALS['positivity_pending_recount'])) {
		$GLOBALS['positivity_pending_recount'] = array();
	}
	if(!isset($GLOBALS['positivity_pending_recount']['uids'])) {
		$GLOBALS['positivity_pending_recount']['uids'] = array();
	}

	foreach($uids as $u => $_)
	{
		$GLOBALS['positivity_pending_recount']['uids'][$u] = 1;
	}
}

function positivity_collect_recount_uids_from_tid(int $tid): void
{
	global $db;

	if($tid <= 0) {
		return;
	}

	$uids = array();
	$q = $db->query("
		SELECT DISTINCT r.adduid AS uid
		FROM ".TABLE_PREFIX."reputation r
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid = r.pid)
		WHERE p.tid = '{$tid}' AND r.pid > 0
	");
	while($row = $db->fetch_array($q))
	{
		$u = (int)$row['uid'];
		if($u > 0) $uids[$u] = 1;
	}

	if(!isset($GLOBALS['positivity_pending_recount'])) {
		$GLOBALS['positivity_pending_recount'] = array();
	}
	if(!isset($GLOBALS['positivity_pending_recount']['uids'])) {
		$GLOBALS['positivity_pending_recount']['uids'] = array();
	}

	foreach($uids as $u => $_)
	{
		$GLOBALS['positivity_pending_recount']['uids'][$u] = 1;
	}
}

function positivity_recount_users(array $uids): void
{
	global $db, $mybb;

	$uids = array_values(array_unique(array_map('intval', $uids)));
	$uids = array_filter($uids, function($v){ return $v > 0; });

	if(empty($uids)) {
		return;
	}

	$in = implode(',', $uids);

	$db->update_query('users', array('positiv' => 0), "uid IN ({$in})");

	$mode = $mybb->settings['positivity_count_mode'] ?? 'signed';
	$sumExpr = ($mode === 'positive_only')
		? "SUM(CASE WHEN reputation>0 THEN reputation ELSE 0 END)"
		: "SUM(reputation)";

	$q = $db->query("
		SELECT adduid, {$sumExpr} AS total
		FROM ".TABLE_PREFIX."reputation
		WHERE adduid IN ({$in})
		GROUP BY adduid
	");

	while($row = $db->fetch_array($q))
	{
		$u = (int)$row['adduid'];
		$total = (int)$row['total'];
		$db->update_query('users', array('positiv' => $total), "uid='{$u}'", 1);
	}
}

function positivity_moderation_delete_post_start($pid)
{
	if(!positivity_should_run()) {
		return;
	}

	$pid = (int)$pid;
	positivity_collect_recount_uids_from_pid($pid);
}

function positivity_moderation_delete_post_end($pid)
{
	if(!positivity_should_run()) {
		return;
	}

	$uids = array();
	if(!empty($GLOBALS['positivity_pending_recount']['uids']) && is_array($GLOBALS['positivity_pending_recount']['uids']))
	{
		$uids = array_keys($GLOBALS['positivity_pending_recount']['uids']);
	}

	if(!empty($uids))
	{
		positivity_recount_users($uids);
	}

	$GLOBALS['positivity_pending_recount'] = array();
}

function positivity_moderation_delete_thread_start($tid)
{
	if(!positivity_should_run()) {
		return;
	}

	$tid = (int)$tid;
	positivity_collect_recount_uids_from_tid($tid);
}

function positivity_moderation_delete_thread_end($tid)
{
	if(!positivity_should_run()) {
		return;
	}

	$uids = array();
	if(!empty($GLOBALS['positivity_pending_recount']['uids']) && is_array($GLOBALS['positivity_pending_recount']['uids']))
	{
		$uids = array_keys($GLOBALS['positivity_pending_recount']['uids']);
	}

	if(!empty($uids))
	{
		positivity_recount_users($uids);
	}

	$GLOBALS['positivity_pending_recount'] = array();
}

function positivity_get_user_positiv(int $uid): int
{
	global $db;

	static $cache = array();

	if($uid <= 0) {
		return 0;
	}

	if(isset($cache[$uid])) {
		return $cache[$uid];
	}

	$query = $db->simple_select('users', 'positiv', "uid='{$uid}'", array('limit' => 1));
	$cache[$uid] = (int)$db->fetch_field($query, 'positiv');

	return $cache[$uid];
}

function positivity_format_value(int $value): array
{
	global $mybb;

	$class = 'reputation_neutral';
	if($value > 0) {
		$class = 'reputation_positive';
	} elseif($value < 0) {
		$class = 'reputation_negative';
	}

	$show_plus = !empty($mybb->settings['positivity_show_plus_sign']);
	$display = my_number_format(abs($value));
	if($value < 0) {
		$display = '-'.$display;
	} elseif($value > 0 && $show_plus) {
		$display = '+'.$display;
	}

	return array($display, $class);
}

function positivity_build_block(int $uid, int $positiv): string
{
	global $templates, $lang;

	list($positivity_value, $positivity_class) = positivity_format_value($positiv);
	$positivity_url = "positivity.php?uid={$uid}";

	eval("\$out = \"".$templates->get('positivity_block', 1, 0)."\";");
	return $out ?? '';
}

function positivity_build_profile_row(int $uid, int $positiv): string
{
	global $templates, $lang;

	list($positivity_value, $positivity_class) = positivity_format_value($positiv);
	$positivity_url = "positivity.php?uid={$uid}";

	eval("\$out = \"".$templates->get('positivity_profile_row', 1, 0)."\";");
	return $out ?? '';
}

function positivity_postbit(&$post)
{
	global $mybb;

	if(!positivity_should_run()) {
		return;
	}

	if(empty($mybb->settings['positivity_show_postbit'])) {
		return;
	}

	$uid = (int)($post['uid'] ?? 0);
	if($uid <= 0) {
		return;
	}

	if(!empty($post['replink']) && strpos($post['replink'], 'positivity.php?uid=') !== false) {
		return;
	}

	$positiv = isset($post['positiv']) ? (int)$post['positiv'] : positivity_get_user_positiv($uid);
	$block = positivity_build_block($uid, $positiv);
	if($block === '') {
		return;
	}

	if(!isset($post['replink']) || $post['replink'] === '') {
		$post['replink'] = $block;
		return;
	}

	$post['replink'] .= $block;
}

function positivity_member_profile_end()
{
	global $mybb, $memprofile, $reputation;

	if(!positivity_should_run()) {
		return;
	}

	if(empty($mybb->settings['positivity_show_profile'])) {
		return;
	}

	$uid = (int)($memprofile['uid'] ?? 0);
	if($uid <= 0) {
		return;
	}

	$positiv = isset($memprofile['positiv']) ? (int)$memprofile['positiv'] : positivity_get_user_positiv($uid);
	$row = positivity_build_profile_row($uid, $positiv);
	if($row === '') {
		return;
	}

	if(!isset($reputation)) {
		$reputation = '';
	}

	if(strpos($reputation, 'positivity.php?uid=') === false) {
		$reputation .= $row;
	}
}

function positivity_memberlist_user(&$user)
{
	global $mybb;

	if(!positivity_should_run()) {
		return;
	}

	if(empty($mybb->settings['positivity_show_memberlist'])) {
		return;
	}

	$uid = (int)($user['uid'] ?? 0);
	if($uid <= 0) {
		return;
	}

	$positiv = isset($user['positiv']) ? (int)$user['positiv'] : positivity_get_user_positiv($uid);
	$block = positivity_build_block($uid, $positiv);
	if($block === '') {
		return;
	}

	if(isset($user['reputation']) && strpos($user['reputation'], 'positivity.php?uid=') === false)
	{
		$user['reputation'] .= $block;
	}
}

function positivity_usercp_end()
{
	global $mybb, $reputation;

	if(!positivity_should_run()) {
		return;
	}

	if(empty($mybb->settings['positivity_show_profile'])) {
		return;
	}

	$uid = (int)($mybb->user['uid'] ?? 0);
	if($uid <= 0) {
		return;
	}

	if(!isset($reputation)) {
		$reputation = '';
	}

	if(strpos($reputation, 'positivity.php?uid=') !== false) {
		return;
	}

	$positiv = positivity_get_user_positiv($uid);
	$block = positivity_build_block($uid, $positiv);
	if($block === '') {
		return;
	}

	$reputation .= $block;
}

function positivity_recount_all(): void
{
	global $db, $mybb;

	$mode = $mybb->settings['positivity_count_mode'] ?? 'signed';
	$sumExpr = ($mode === 'positive_only')
		? "SUM(CASE WHEN reputation>0 THEN reputation ELSE 0 END)"
		: "SUM(reputation)";

	$db->update_query('users', array('positiv' => 0));

	$query = $db->query("
		SELECT adduid, {$sumExpr} AS total
		FROM ".TABLE_PREFIX."reputation
		GROUP BY adduid
	");

	while($row = $db->fetch_array($query))
	{
		$uid = (int)$row['adduid'];
		$total = (int)$row['total'];

		if($uid > 0)
		{
			$db->update_query('users', array('positiv' => $total), "uid='{$uid}'", 1);
		}
	}
}