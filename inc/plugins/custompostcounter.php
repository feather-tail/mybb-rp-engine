<?php if (!defined('IN_MYBB')) {
  die('Direct access not allowed.');
}

function custompostcounter_info()
{
  return [
    'name' => 'Custom Post Counter',
    'description' => 'Counts user posts in specific forums and updates the custom field.',
    'website' => 'https://github.com/feather-tail/mybbCustomPostCounter/tree/main',
    'author' => 'feather-tail',
    'authorsite' => '',
    'version' => '1.1',
    'compatibility' => '18*',
    'guid' => '',
  ];
}

function custompostcounter_install()
{
  global $db, $lang;
  if (!$db->field_exists('countCustomPost', 'users')) {
    $db->add_column('users', 'countCustomPost', 'INT(11) NOT NULL DEFAULT 0');
  }
  if (!isset($lang->cpc_settings_group_title)) {
    if (method_exists($lang, 'load')) {
      $lang->load('custompostcounter');
    }
  }
  $setting_group = [
    'name' => 'custompostcounter',
    'title' => isset($lang->cpc_settings_group_title)
      ? $lang->cpc_settings_group_title
      : 'Custom Post Counter Settings',
    'description' => isset($lang->cpc_settings_group_description)
      ? $lang->cpc_settings_group_description
      : 'Settings for the Custom Post Counter plugin',
    'disporder' => 5,
    'isdefault' => 0,
  ];
  $gid = $db->insert_query('settinggroups', $setting_group);
  $setting_array = [
    'custompostcounter_forums' => [
      'title' => isset($lang->cpc_settings_forums_title)
        ? $lang->cpc_settings_forums_title
        : 'Tracked Forums',
      'description' => isset($lang->cpc_settings_forums_description)
        ? $lang->cpc_settings_forums_description
        : 'Enter the IDs of the forums to track, separated by commas. Example: 2,5,7',
      'optionscode' => 'text',
      'value' => '1',
      'disporder' => 1,
      'gid' => $gid,
    ],
    'custompostcounter_count_firstpost' => [
      'title' => isset($lang->cpc_settings_count_firstpost_title)
        ? $lang->cpc_settings_count_firstpost_title
        : 'Count thread starter post',
      'description' => isset($lang->cpc_settings_count_firstpost_desc)
        ? $lang->cpc_settings_count_firstpost_desc
        : 'If enabled, the first post in a thread is also counted. Default: disabled.',
      'optionscode' => 'onoff',
      'value' => '0',
      'disporder' => 2,
      'gid' => $gid,
    ],
  ];
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
}

function custompostcounter_uninstall()
{
  global $db;
  if ($db->field_exists('countCustomPost', 'users')) {
    $db->drop_column('users', 'countCustomPost');
  }
  $db->delete_query(
    'settings',
    "name IN ('custompostcounter_forums','custompostcounter_count_firstpost')",
  );
  $db->delete_query('settinggroups', "name = 'custompostcounter'");
  rebuild_settings();
}

function custompostcounter_is_installed()
{
  global $db;
  return $db->field_exists('countCustomPost', 'users');
}

function custompostcounter_activate()
{
  global $db;
  $tpl_title = 'custompostcounter_bit';
  $exists_q = $db->simple_select(
    'templates',
    'tid',
    "title='" . $db->escape_string($tpl_title) . "' AND sid='-1'",
  );
  $exists = $db->fetch_array($exists_q);
  if (!$exists) {
    $tpl = [
      'title' => $tpl_title,
      'template' => $db->escape_string(
        "<br /><strong>{\$lang->cpc_label}</strong> {\$post['countCustomPost']}",
      ),
      'sid' => -1,
      'version' => 1,
      'dateline' => TIME_NOW,
    ];
    $db->insert_query('templates', $tpl);
  }
  $tpl_title_profile = 'custompostcounter_profile_bit';
  $exists_q2 = $db->simple_select(
    'templates',
    'tid',
    "title='" . $db->escape_string($tpl_title_profile) . "' AND sid='-1'",
  );
  $exists2 = $db->fetch_array($exists_q2);
  if (!$exists2) {
    $tpl2 = [
      'title' => $tpl_title_profile,
      'template' => $db->escape_string(
        "<tr>\n\t<td class=\"trow1\"><strong>{\$lang->cpc_label}</strong></td>\n\t<td class=\"trow1\">{\$memprofile['countCustomPost']}</td>\n</tr>",
      ),
      'sid' => -1,
      'version' => 1,
      'dateline' => TIME_NOW,
    ];
    $db->insert_query('templates', $tpl2);
  }
  require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
  find_replace_templatesets(
    'postbit_author_user',
    '#\{\$post\\[\'custompostcounter\'\]\}#',
    "<br /><strong>{\$lang->cpc_label}</strong> {\$post['countCustomPost']}",
  );
  $needle_custom = '{$post[\'custompostcounter\']}';
  $needle_warning = '{$post[\'warninglevel\']}';
  $insert_markup = "<br /><strong>{\$lang->cpc_label}</strong> {\$post['countCustomPost']}";
  $q = $db->simple_select('templates', 'tid, template', "title='postbit_author_user'");
  while ($row = $db->fetch_array($q)) {
    $tpl = (string) $row['template'];
    $tid = (int) $row['tid'];
    if (strpos($tpl, $insert_markup) !== false) {
      continue;
    }
    if (strpos($tpl, $needle_custom) !== false) {
      continue;
    }
    if (strpos($tpl, $needle_warning) !== false) {
      $new = str_replace($needle_warning, $needle_warning . $insert_markup, $tpl);
      if ($new !== $tpl) {
        $db->update_query('templates', ['template' => $db->escape_string($new)], "tid={$tid}");
        continue;
      }
    }
    $new = $tpl . $insert_markup;
    if ($new !== $tpl) {
      $db->update_query('templates', ['template' => $db->escape_string($new)], "tid={$tid}");
    }
  }
  $pattern =
    '#(<td\s+colspan="2"\s+class="thead"><strong>\{\$lang->users_forum_info\}</strong></td>.*?)(</table>)#s';
  $replacement = '$1{$memprofile[\'custompostcounter\']}$2';
  $fallback_pat = '#</table>#';
  $fallback_rep = '{$memprofile[\'custompostcounter\']}</table>';
  $needle_profile = '{$memprofile[\'custompostcounter\']}';
  $q2 = $db->simple_select('templates', 'tid, template', "title='member_profile'");
  while ($row = $db->fetch_array($q2)) {
    $tpl = (string) $row['template'];
    $tid = (int) $row['tid'];
    if (strpos($tpl, $needle_profile) !== false) {
      continue;
    }
    $count1 = 0;
    $new = preg_replace($pattern, $replacement, $tpl, 1, $count1);
    if (!$count1) {
      $count2 = 0;
      $new = preg_replace($fallback_pat, $fallback_rep, $tpl, 1, $count2);
      if (!$count2) {
        continue;
      }
    }
    if ($new !== $tpl) {
      $db->update_query('templates', ['template' => $db->escape_string($new)], "tid={$tid}");
    }
  }
}

function custompostcounter_deactivate()
{
  require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
  find_replace_templatesets(
    'postbit_author_user',
    '#<br\s*/?>\s*<strong>\{\$lang->cpc_label\}</strong>\s*\{\$post\\[\'countCustomPost\'\]\}#',
    '',
  );
  find_replace_templatesets('postbit_author_user', '#\{\$post\\[\'custompostcounter\'\]\}#', '');
  find_replace_templatesets('member_profile', '#\{\$memprofile\\[\'custompostcounter\'\]\}#', '');
}

function custompostcounter_load_language()
{
  global $lang;
  if (method_exists($lang, 'load')) {
    $lang->load('custompostcounter');
  }
}

function custompostcounter_get_valid_forums()
{
  static $cached = null;
  if ($cached !== null) {
    return $cached;
  }
  global $mybb;
  $raw = isset($mybb->settings['custompostcounter_forums'])
    ? (string) $mybb->settings['custompostcounter_forums']
    : '';
  $parts = explode(',', $raw);
  $ids = [];
  foreach ($parts as $part) {
    $id = (int) trim($part);
    if ($id > 0) {
      $ids[$id] = true;
    }
  }
  $cached = array_map('intval', array_keys($ids));
  return $cached;
}

function custompostcounter_count_firstpost_enabled(): bool
{
  global $mybb;
  return !empty($mybb->settings['custompostcounter_count_firstpost']);
}

function custompostcounter_schedule_deferred_rebuild_for_merge()
{
  $GLOBALS['cpc_rebuild_scheduled_for_merge'] = true;
  if (empty($GLOBALS['cpc_rebuild_scheduled'])) {
    $GLOBALS['cpc_rebuild_scheduled'] = true;
    register_shutdown_function('custompostcounter_run_deferred_rebuild');
  }
}

function custompostcounter_update_post_count($uid, $delta)
{
  global $db;
  $uid = (int) $uid;
  $delta = (int) $delta;
  if ($uid <= 0 || $delta === 0) {
    return;
  }
  $db->update_query(
    'users',
    ['countCustomPost' => 'countCustomPost+' . ($delta > 0 ? $delta : $delta)],
    'uid=' . $uid,
    1,
    true,
  );
}

function custompostcounter_postbit(&$post)
{
  global $templates, $lang;
  if (!isset($lang->cpc_label) && method_exists($lang, 'load')) {
    $lang->load('custompostcounter');
  }
  if (!isset($post['countCustomPost'])) {
    if (!empty($post['uid'])) {
      $user = get_user((int) $post['uid']);
      $post['countCustomPost'] = isset($user['countCustomPost'])
        ? (int) $user['countCustomPost']
        : 0;
    } else {
      $post['countCustomPost'] = 0;
    }
  } else {
    $post['countCustomPost'] = (int) $post['countCustomPost'];
  }
  if (isset($templates) && method_exists($templates, 'get')) {
    $tpl = $templates->get('custompostcounter_bit');
    if ($tpl !== '') {
      eval("\$post['custompostcounter'] = \"{$tpl}\";");
      return;
    }
  }
  $label = isset($lang->cpc_label) ? htmlspecialchars_uni($lang->cpc_label) : '';
  $value = (int) $post['countCustomPost'];
  $post['custompostcounter'] = "<br /><strong>{$label}</strong> {$value}";
}

function custompostcounter_member_profile_end()
{
  global $memprofile, $templates, $lang;
  if (!isset($lang->cpc_label) && method_exists($lang, 'load')) {
    $lang->load('custompostcounter');
  }
  if (!isset($memprofile['countCustomPost'])) {
    $u = get_user((int) $memprofile['uid']);
    $memprofile['countCustomPost'] = isset($u['countCustomPost']) ? (int) $u['countCustomPost'] : 0;
  } else {
    $memprofile['countCustomPost'] = (int) $memprofile['countCustomPost'];
  }
  if (isset($templates) && method_exists($templates, 'get')) {
    $tpl = $templates->get('custompostcounter_profile_bit');
    if ($tpl !== '') {
      eval("\$memprofile['custompostcounter'] = \"{$tpl}\";");
      return;
    }
  }
  $label = isset($lang->cpc_label) ? htmlspecialchars_uni($lang->cpc_label) : '';
  $val = (int) $memprofile['countCustomPost'];
  $memprofile[
    'custompostcounter'
  ] = "<tr><td class=\"trow1\"><strong>{$label}</strong></td><td class=\"trow1\">{$val}</td></tr>";
}

function custompostcounter_increment($post)
{
  $fid = (int) $post->data['fid'];
  if (!in_array($fid, custompostcounter_get_valid_forums(), true)) {
    return;
  }
  if (isset($post->post_insert_data['visible'])) {
    $visible = (int) $post->post_insert_data['visible'];
  } elseif (isset($post->data['visible'])) {
    $visible = (int) $post->data['visible'];
  } else {
    $visible = 1;
  }
  if ($visible !== 1) {
    return;
  }
  if (!custompostcounter_count_firstpost_enabled()) {
    $thread = get_thread((int) $post->data['tid']);
    if ($thread && (int) $post->data['pid'] === (int) $thread['firstpost']) {
      return;
    }
  }
  custompostcounter_update_post_count((int) $post->data['uid'], 1);
}
function custompostcounter_decrement($pid)
{
  $post = get_post((int) $pid);
  if (!$post) {
    return;
  }
  $thread = get_thread((int) $post['tid']);
  if (!$thread) {
    return;
  }
  if ((int) $post['visible'] !== 1) {
    return;
  }
  if (
    !custompostcounter_count_firstpost_enabled() &&
    (int) $post['pid'] === (int) $thread['firstpost']
  ) {
    return;
  }
  if (in_array((int) $post['fid'], custompostcounter_get_valid_forums(), true)) {
    custompostcounter_update_post_count((int) $post['uid'], -1);
  }
}

function custompostcounter_decrement_soft($pids)
{
  if (!is_array($pids)) {
    $pids = [$pids];
  }
  foreach ($pids as $pid) {
    $post = get_post((int) $pid);
    if (!$post) {
      continue;
    }
    $fid = (int) $post['fid'];
    if (!in_array($fid, custompostcounter_get_valid_forums(), true)) {
      continue;
    }
    $thread = get_thread((int) $post['tid']);
    if (!$thread) {
      continue;
    }
    if (
      !custompostcounter_count_firstpost_enabled() &&
      (int) $post['pid'] === (int) $thread['firstpost']
    ) {
      continue;
    }
    if ((int) $post['visible'] == 1) {
      custompostcounter_update_post_count((int) $post['uid'], -1);
    }
  }
}

function custompostcounter_increment_soft($pids)
{
  if (!empty($GLOBALS['cpc_in_thread_restore'])) {
    return;
  }
  if (!is_array($pids)) {
    $pids = [$pids];
  }
  foreach ($pids as $pid) {
    $post = get_post((int) $pid);
    if (!$post) {
      continue;
    }
    $tid = (int) $post['tid'];
    if ($tid > 0) {
      if (empty($GLOBALS['cpc_tid_restored_by_posts'])) {
        $GLOBALS['cpc_tid_restored_by_posts'] = [];
      }
      $GLOBALS['cpc_tid_restored_by_posts'][$tid] = true;
    }
    $fid = (int) $post['fid'];
    if (!in_array($fid, custompostcounter_get_valid_forums(), true)) {
      continue;
    }
    $thread = get_thread($tid);
    if (!$thread) {
      continue;
    }
    if (
      !custompostcounter_count_firstpost_enabled() &&
      (int) $post['pid'] === (int) $thread['firstpost']
    ) {
      continue;
    }
    if ((int) $post['visible'] != 1) {
      custompostcounter_update_post_count((int) $post['uid'], 1);
    }
  }
}

function custompostcounter_increment_thread($post)
{
  $fid = (int) ($post->data['fid'] ?? 0);
  if ($fid <= 0 || !in_array($fid, custompostcounter_get_valid_forums(), true)) {
    return;
  }
  if (!custompostcounter_count_firstpost_enabled()) {
    return;
  }
  if (isset($post->post_insert_data['visible'])) {
    $visible = (int) $post->post_insert_data['visible'];
  } elseif (isset($post->data['visible'])) {
    $visible = (int) $post->data['visible'];
  } else {
    $visible = 1;
  }
  if ($visible !== 1) {
    return;
  }
  $uid = (int) ($post->data['uid'] ?? 0);
  if ($uid > 0) {
    custompostcounter_update_post_count($uid, +1);
  }
}

function custompostcounter_update_thread_post_count($tid, $delta, array $visible_in = [1])
{
  global $db;
  $tid = (int) $tid;
  $delta = (int) $delta;
  $thread = get_thread($tid);
  if (!$thread) {
    return;
  }
  if (!in_array((int) $thread['fid'], custompostcounter_get_valid_forums(), true)) {
    return;
  }
  $allowed = [];
  foreach ($visible_in as $v) {
    $v = (int) $v;
    if ($v === 1 || $v === 0 || $v === -1) {
      $allowed[$v] = true;
    }
  }
  if (!$allowed) {
    return;
  }
  $firstpost = (int) $thread['firstpost'];
  $in_list = implode(',', array_keys($allowed));
  $first_condition = custompostcounter_count_firstpost_enabled() ? '1=1' : "pid!='{$firstpost}'";
  $query = $db->simple_select(
    'posts',
    'uid, COUNT(*) as post_count',
    "tid='{$tid}' AND {$first_condition} AND visible IN ({$in_list})",
    ['group_by' => 'uid'],
  );
  while ($row = $db->fetch_array($query)) {
    custompostcounter_update_post_count((int) $row['uid'], $delta * (int) $row['post_count']);
  }
}

function custompostcounter_decrement_thread($tid)
{
  custompostcounter_update_thread_post_count((int) $tid, -1, [1]);
}

function custompostcounter_decrement_soft_thread($tids)
{
  if (!is_array($tids)) {
    $tids = [$tids];
  }
  foreach ($tids as $tid) {
    custompostcounter_update_thread_post_count($tid, -1);
  }
}

function custompostcounter_increment_soft_thread($tids)
{
  if (!is_array($tids)) {
    $tids = [$tids];
  }
  $GLOBALS['cpc_in_thread_restore'] = true;
  try {
    foreach ($tids as $tid) {
      $tid = (int) $tid;
      if ($tid <= 0) {
        continue;
      }
      if (!empty($GLOBALS['cpc_tid_restored_by_posts'][$tid])) {
        continue;
      }
      custompostcounter_update_thread_post_count($tid, 1, [1]);
    }
  } finally {
    unset($GLOBALS['cpc_in_thread_restore']);
  }
}

function custompostcounter_rebuild_counts(array $fids = null)
{
  global $db;
  if ($fids === null) {
    $fids = custompostcounter_get_valid_forums();
  } else {
    $tmp = [];
    foreach ($fids as $v) {
      $v = (int) $v;
      if ($v > 0) {
        $tmp[$v] = true;
      }
    }
    $fids = array_map('intval', array_keys($tmp));
  }
  $db->update_query('users', ['countCustomPost' => 0], '1=1');
  if (empty($fids)) {
    return;
  }
  $fidList = implode(',', $fids);
  $prefix = TABLE_PREFIX;
  $first_join_cond = custompostcounter_count_firstpost_enabled() ? '1=1' : 'p.pid != t.firstpost';
  $q = $db->query(
    " SELECT p.uid, COUNT(*) AS c FROM {$prefix}posts p INNER JOIN {$prefix}threads t ON t.tid = p.tid WHERE p.visible = 1 AND t.visible = 1 AND p.fid IN ({$fidList}) AND {$first_join_cond} ",
  );
  while ($row = $db->fetch_array($q)) {
    break;
  }
  $q2 = $db->query(
    " SELECT p.uid, COUNT(*) AS c FROM {$prefix}posts p INNER JOIN {$prefix}threads t ON t.tid = p.tid WHERE p.visible = 1 AND t.visible = 1 AND p.fid IN ({$fidList}) AND {$first_join_cond} GROUP BY p.uid ",
  );
  while ($row = $db->fetch_array($q2)) {
    $uid = (int) $row['uid'];
    $cnt = (int) $row['c'];
    if ($uid > 0 && $cnt > 0) {
      custompostcounter_update_post_count($uid, $cnt);
    }
  }
}

function custompostcounter_admin_tools_menu(&$sub_menu)
{
  global $lang;
  $lang->load('custompostcounter');
  $sub_menu[] = [
    'id' => 'custompostcounter_rebuild',
    'title' => $lang->cpc_tools_rebuild,
    'link' => 'index.php?module=tools-custompostcounter_rebuild',
  ];
  $sub_menu[] = [
    'id' => 'custompostcounter_stats',
    'title' => $lang->cpc_tools_stats,
    'link' => 'index.php?module=tools-custompostcounter_stats',
  ];
}

function custompostcounter_admin_tools_action_handler(&$actions)
{
  $actions['custompostcounter_rebuild'] = [
    'active' => 'custompostcounter_rebuild',
    'file' => 'custompostcounter_rebuild.php',
  ];
  $actions['custompostcounter_stats'] = [
    'active' => 'custompostcounter_stats',
    'file' => 'custompostcounter_stats.php',
  ];
}

function custompostcounter_admin_tools_permissions(&$admin_permissions)
{
  global $lang;
  $lang->load('custompostcounter');
  $admin_permissions['custompostcounter'] = $lang->cpc_tools_rebuild;
}

function custompostcounter_schedule_deferred_rebuild_for_copy()
{
  $GLOBALS['cpc_rebuild_scheduled_for_copy'] = true;
  if (empty($GLOBALS['cpc_rebuild_scheduled'])) {
    $GLOBALS['cpc_rebuild_scheduled'] = true;
    register_shutdown_function('custompostcounter_run_deferred_rebuild');
  }
}

function custompostcounter_schedule_deferred_rebuild()
{
  if (!empty($GLOBALS['cpc_rebuild_scheduled'])) {
    return;
  }
  $GLOBALS['cpc_rebuild_scheduled'] = true;
  register_shutdown_function('custompostcounter_run_deferred_rebuild');
}

function custompostcounter_run_deferred_rebuild()
{
  if (!empty($GLOBALS['cpc_merge_queue'])) {
    foreach ($GLOBALS['cpc_merge_queue'] as $job) {
      if (
        !custompostcounter_count_firstpost_enabled() &&
        $job['dst_tracked'] &&
        (int) $job['src_visible'] === 1 &&
        (int) $job['src_uid'] > 0
      ) {
        $dst_thread = get_thread((int) $job['dst_tid']);
        if ($dst_thread) {
          $final_firstpid = (int) $dst_thread['firstpost'];
          if ($final_firstpid > 0 && $final_firstpid !== (int) $job['src_firstpid']) {
            custompostcounter_update_post_count((int) $job['src_uid'], +1);
          }
        }
      }
    }
  }

  if (
    !empty($GLOBALS['cpc_rebuild_scheduled_for_copy']) ||
    !empty($GLOBALS['cpc_rebuild_scheduled_for_merge'])
  ) {
    custompostcounter_rebuild_counts(null);
  }

  unset(
    $GLOBALS['cpc_merge_queue'],
    $GLOBALS['cpc_rebuild_scheduled'],
    $GLOBALS['cpc_rebuild_scheduled_for_copy'],
    $GLOBALS['cpc_rebuild_scheduled_for_merge'],
  );
}

function custompostcounter_on_move_thread($args)
{
  global $db;
  $tid = isset($args['tid']) ? (int) $args['tid'] : 0;
  $new_fid = 0;
  foreach (['moveto', 'new_fid', 'destination_fid', 'fid'] as $k) {
    if (isset($args[$k])) {
      $new_fid = (int) $args[$k];
      break;
    }
  }
  if ($tid <= 0 || $new_fid <= 0) {
    return;
  }
  $thread = get_thread($tid);
  if (!$thread) {
    return;
  }
  $old_fid = (int) $thread['fid'];
  if ($old_fid === $new_fid) {
    return;
  }
  $valid = custompostcounter_get_valid_forums();
  $oldTracked = in_array($old_fid, $valid, true);
  $newTracked = in_array($new_fid, $valid, true);
  if ($oldTracked === $newTracked) {
    return;
  }
  $firstpid = (int) $thread['firstpost'];
  $first_condition = custompostcounter_count_firstpost_enabled() ? '1=1' : "pid!='{$firstpid}'";
  $q = $db->simple_select(
    'posts',
    'uid, COUNT(*) AS c',
    "tid='{$tid}' AND {$first_condition} AND visible='1'",
    ['group_by' => 'uid'],
  );
  while ($row = $db->fetch_array($q)) {
    $uid = (int) $row['uid'];
    $cnt = (int) $row['c'];
    if ($uid <= 0 || $cnt <= 0) {
      continue;
    }
    $delta = $newTracked ? +$cnt : -$cnt;
    custompostcounter_update_post_count($uid, $delta);
  }
}

function custompostcounter_on_move_threads($args)
{
  $tids = isset($args['tids']) ? (array) $args['tids'] : [];
  $moveto = isset($args['moveto']) ? (int) $args['moveto'] : 0;
  if ($moveto <= 0 || empty($tids)) {
    return;
  }
  foreach ($tids as $tid) {
    $tid = (int) $tid;
    if ($tid > 0) {
      custompostcounter_on_move_thread(['tid' => $tid, 'moveto' => $moveto]);
    }
  }
}

function custompostcounter_on_approve_posts($pids)
{
  if (!is_array($pids)) {
    $pids = [$pids];
  }

  foreach ($pids as $pid) {
    $post = get_post((int) $pid);
    if (!$post) {
      continue;
    }

    $tid = (int) $post['tid'];
    $thread = get_thread($tid);
    if (!$thread) {
      continue;
    }

    $fid = (int) $post['fid'];
    if (!in_array($fid, custompostcounter_get_valid_forums(), true)) {
      continue;
    }

    if ((int) $thread['visible'] != 1) {
      continue;
    }

    if (!empty($GLOBALS['cpc_tid_approved'][$tid])) {
      continue;
    }

    $is_first = (int) $post['pid'] === (int) $thread['firstpost'];
    if ($is_first && !custompostcounter_count_firstpost_enabled()) {
      continue;
    }

    if ((int) $post['visible'] == 1 && (int) $post['uid'] > 0) {
      custompostcounter_update_post_count((int) $post['uid'], +1);
    }
  }
}

function custompostcounter_on_unapprove_posts($pids)
{
  if (!is_array($pids)) {
    $pids = [$pids];
  }
  foreach ($pids as $pid) {
    $post = get_post((int) $pid);
    if (!$post) {
      continue;
    }
    $thread = get_thread((int) $post['tid']);
    if (!$thread) {
      continue;
    }
    $fid = (int) $post['fid'];
    if (!in_array($fid, custompostcounter_get_valid_forums(), true)) {
      continue;
    }
    $is_first = (int) $post['pid'] === (int) $thread['firstpost'];
    if ($is_first && !custompostcounter_count_firstpost_enabled()) {
      continue;
    }
    custompostcounter_update_post_count((int) $post['uid'], -1);
  }
}

function custompostcounter_on_approve_threads($tids)
{
  if (!is_array($tids)) {
    $tids = [$tids];
  }

  if (empty($GLOBALS['cpc_tid_approved'])) {
    $GLOBALS['cpc_tid_approved'] = [];
  }
  foreach ($tids as $tid) {
    $tid = (int) $tid;
    if ($tid > 0) {
      $GLOBALS['cpc_tid_approved'][$tid] = true;
    }
  }

  foreach ($tids as $tid) {
    $tid = (int) $tid;
    if ($tid <= 0) {
      continue;
    }

    $thread = get_thread($tid);
    if (!$thread) {
      continue;
    }
    if (!in_array((int) $thread['fid'], custompostcounter_get_valid_forums(), true)) {
      continue;
    }

    custompostcounter_update_thread_post_count($tid, +1, [1]);
  }
}

function custompostcounter_on_unapprove_threads($tids)
{
  if (!is_array($tids)) {
    $tids = [$tids];
  }

  foreach ($tids as $tid) {
    $tid = (int) $tid;
    if ($tid <= 0) {
      continue;
    }

    $thread = get_thread($tid);
    if (!$thread) {
      continue;
    }
    if (!in_array((int) $thread['fid'], custompostcounter_get_valid_forums(), true)) {
      continue;
    }

    custompostcounter_update_thread_post_count($tid, -1, [1]);
  }
}

function custompostcounter_on_merge_threads($args)
{
  $src_tid = (int) ($args['tid'] ?? 0);

  $dst_tid = 0;
  foreach (['mergetid', 'destination_tid', 'new_tid', 'merge_tid'] as $k) {
    if (!empty($args[$k])) {
      $dst_tid = (int) $args[$k];
      break;
    }
  }

  if ($src_tid <= 0 || $dst_tid <= 0 || $src_tid === $dst_tid) {
    return;
  }

  $src_thread = get_thread($src_tid);
  $dst_thread = get_thread($dst_tid);
  if (!$dst_thread) {
    return;
  }

  $valid = custompostcounter_get_valid_forums();
  $srcTracked = $src_thread ? in_array((int) $src_thread['fid'], $valid, true) : false;
  $dstTracked = in_array((int) $dst_thread['fid'], $valid, true);

  if (!$srcTracked && !$dstTracked) {
    return;
  }

  static $shutdown_registered = false;
  if (!$shutdown_registered) {
    $shutdown_registered = true;
    register_shutdown_function(function () {
      custompostcounter_rebuild_counts(null);
    });
  }
}

function custompostcounter_on_copy_thread($args)
{
  $dstF = 0;
  if (isset($args['destination_fid'])) {
    $dstF = (int) $args['destination_fid'];
  } elseif (isset($args['new_fid'])) {
    $dstF = (int) $args['new_fid'];
  }
  if ($dstF > 0 && in_array($dstF, custompostcounter_get_valid_forums(), true)) {
    custompostcounter_schedule_deferred_rebuild_for_copy();
  }
}

function custompostcounter_on_split_posts($args)
{
  global $db;
  $pids = isset($args['pids']) ? (array) $args['pids'] : [];
  $srcTid = isset($args['tid']) ? (int) $args['tid'] : 0;
  $dstFid = isset($args['moveto']) ? (int) $args['moveto'] : 0;
  if ($srcTid <= 0 || $dstFid <= 0 || empty($pids)) {
    return;
  }
  $srcThread = get_thread($srcTid);
  if (!$srcThread) {
    return;
  }
  $srcFid = (int) $srcThread['fid'];
  $valid = custompostcounter_get_valid_forums();
  $oldTracked = in_array($srcFid, $valid, true);
  $newTracked = in_array($dstFid, $valid, true);
  $pidSet = [];
  foreach ($pids as $pid) {
    $pid = (int) $pid;
    if ($pid > 0) {
      $pidSet[$pid] = true;
    }
  }
  if (empty($pidSet)) {
    return;
  }
  $pidList = implode(',', array_keys($pidSet));
  $firstRow = $db->simple_select('posts', 'pid,uid,dateline,visible', "pid IN ({$pidList})", [
    'order_by' => 'dateline',
    'order_dir' => 'asc',
    'limit' => 1,
  ]);
  $firstNew = $db->fetch_array($firstRow);
  $firstPid = $firstNew ? (int) $firstNew['pid'] : 0;
  if ($oldTracked && !$newTracked) {
    $q = $db->simple_select('posts', 'uid, COUNT(*) AS c', "pid IN ({$pidList}) AND visible='1'", [
      'group_by' => 'uid',
    ]);
    while ($row = $db->fetch_array($q)) {
      $uid = (int) $row['uid'];
      $cnt = (int) $row['c'];
      if ($uid > 0 && $cnt > 0) {
        custompostcounter_update_post_count($uid, -$cnt);
      }
    }
    return;
  }
  if (!$oldTracked && $newTracked) {
    $first_condition =
      $firstPid > 0 && !custompostcounter_count_firstpost_enabled() ? "AND pid!='{$firstPid}'" : '';
    $q = $db->simple_select(
      'posts',
      'uid, COUNT(*) AS c',
      "pid IN ({$pidList}) AND visible='1' {$first_condition}",
      ['group_by' => 'uid'],
    );
    while ($row = $db->fetch_array($q)) {
      $uid = (int) $row['uid'];
      $cnt = (int) $row['c'];
      if ($uid > 0 && $cnt > 0) {
        custompostcounter_update_post_count($uid, +$cnt);
      }
    }
    return;
  }
  if ($oldTracked && $newTracked && $firstPid > 0 && !custompostcounter_count_firstpost_enabled()) {
    $fp = get_post($firstPid);
    if ($fp && (int) $fp['visible'] === 1) {
      $uid = (int) $fp['uid'];
      if ($uid > 0) {
        custompostcounter_update_post_count($uid, -1);
      }
    }
    return;
  }
}

function custompostcounter_settings_capture_prev()
{
  global $mybb;
  $GLOBALS['cpc_prev_count_firstpost'] =
    (int) ($mybb->settings['custompostcounter_count_firstpost'] ?? 0);
  $GLOBALS['cpc_prev_forums_raw'] = (string) ($mybb->settings['custompostcounter_forums'] ?? '');
}

function custompostcounter_settings_rebuild_if_changed()
{
  global $mybb;
  $new_count_first = (int) ($mybb->settings['custompostcounter_count_firstpost'] ?? 0);
  $old_count_first = isset($GLOBALS['cpc_prev_count_firstpost'])
    ? (int) $GLOBALS['cpc_prev_count_firstpost']
    : $new_count_first;
  $prev_forums_norm = isset($GLOBALS['cpc_prev_forums_raw'])
    ? custompostcounter_normalize_forum_list((string) $GLOBALS['cpc_prev_forums_raw'])
    : null;
  $curr_forums_norm = custompostcounter_normalize_forum_list(
    isset($mybb->settings['custompostcounter_forums'])
      ? (string) $mybb->settings['custompostcounter_forums']
      : '',
  );
  $forums_changed = $prev_forums_norm !== null && $prev_forums_norm !== $curr_forums_norm;
  if ($new_count_first !== $old_count_first || $forums_changed) {
    custompostcounter_rebuild_counts(null);
  }
}

function custompostcounter_normalize_forum_list(string $raw): string
{
  $ids = [];
  foreach (explode(',', $raw) as $p) {
    $v = (int) trim($p);
    if ($v > 0) {
      $ids[$v] = true;
    }
  }
  ksort($ids);
  return implode(',', array_keys($ids));
}

$plugins->add_hook('datahandler_post_insert_post', 'custompostcounter_increment');
$plugins->add_hook('class_moderation_delete_post', 'custompostcounter_decrement');
$plugins->add_hook('class_moderation_soft_delete_posts', 'custompostcounter_decrement_soft');
$plugins->add_hook('class_moderation_restore_posts', 'custompostcounter_increment_soft');
$plugins->add_hook('class_moderation_delete_thread_start', 'custompostcounter_decrement_thread');
$plugins->add_hook(
  'class_moderation_soft_delete_threads',
  'custompostcounter_decrement_soft_thread',
);
$plugins->add_hook('class_moderation_restore_threads', 'custompostcounter_increment_soft_thread');
$plugins->add_hook('postbit', 'custompostcounter_postbit');
$plugins->add_hook('member_profile_end', 'custompostcounter_member_profile_end');
$plugins->add_hook('class_moderation_move_simple', 'custompostcounter_on_move_thread');
$plugins->add_hook('class_moderation_move_thread_redirect', 'custompostcounter_on_move_thread');
$plugins->add_hook('class_moderation_move_threads', 'custompostcounter_on_move_threads');
$plugins->add_hook('class_moderation_approve_posts', 'custompostcounter_on_approve_posts');
$plugins->add_hook('class_moderation_unapprove_posts', 'custompostcounter_on_unapprove_posts');
$plugins->add_hook('class_moderation_approve_threads', 'custompostcounter_on_approve_threads');
$plugins->add_hook('class_moderation_unapprove_threads', 'custompostcounter_on_unapprove_threads');
$plugins->add_hook('class_moderation_merge_threads', 'custompostcounter_on_merge_threads');
$plugins->add_hook('class_moderation_copy_thread', 'custompostcounter_on_copy_thread');
$plugins->add_hook('class_moderation_split_posts', 'custompostcounter_on_split_posts');
$plugins->add_hook('global_start', 'custompostcounter_load_language');
$plugins->add_hook('admin_tools_menu', 'custompostcounter_admin_tools_menu');
$plugins->add_hook('admin_tools_action_handler', 'custompostcounter_admin_tools_action_handler');
$plugins->add_hook('admin_tools_permissions', 'custompostcounter_admin_tools_permissions');
$plugins->add_hook('datahandler_post_insert_thread', 'custompostcounter_increment_thread');
$plugins->add_hook('admin_config_settings_change', 'custompostcounter_settings_capture_prev');
$plugins->add_hook(
  'admin_config_settings_change_commit',
  'custompostcounter_settings_rebuild_if_changed',
); ?>
