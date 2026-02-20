<?php
if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('datahandler_post_validate_post', 'dice_rolls_validate');
$plugins->add_hook('datahandler_post_validate_thread', 'dice_rolls_validate');
$plugins->add_hook('datahandler_post_insert_post_end', 'dice_rolls_after_insert_post');
$plugins->add_hook('datahandler_post_insert_thread_post', 'dice_rolls_after_insert_thread_post');
$plugins->add_hook('datahandler_post_update_end', 'dice_rolls_after_update');
$plugins->add_hook('class_moderation_delete_post', 'dice_rolls_on_delete_post');
$plugins->add_hook('class_moderation_delete_thread_start', 'dice_rolls_on_delete_thread');
$plugins->add_hook('parse_message_end', 'dice_rolls_parse_message_end');

function dice_rolls_info()
{
    return array(
        'name' => 'Dice Rolls',
        'description' => 'Adds fixed dice rolls via [dice=1d20]. Rolls are generated on save and rendered on display.',
        'website' => '',
        'author' => 'Feathertail',
        'authorsite' => '',
        'version' => '1.0.1',
        'compatibility' => '18*'
    );
}

function dice_rolls_is_installed()
{
    global $db;
    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='dice_rolls'", array('limit' => 1)), 'gid');
    return $gid > 0;
}

function dice_rolls_install()
{
    global $db;

    if(!$db->table_exists('dice_rolls')) {
        $collation = $db->build_create_table_collation();
        $db->write_query(
            "CREATE TABLE ".$db->table_prefix."dice_rolls (\n"
            ."  id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            ."  token VARCHAR(40) NOT NULL,\n"
            ."  pid INT UNSIGNED NOT NULL,\n"
            ."  tid INT UNSIGNED NOT NULL,\n"
            ."  dice SMALLINT UNSIGNED NOT NULL,\n"
            ."  sides INT UNSIGNED NOT NULL,\n"
            ."  modifier INT NOT NULL DEFAULT 0,\n"
            ."  expr VARCHAR(32) NOT NULL,\n"
            ."  rolls TEXT NOT NULL,\n"
            ."  total INT NOT NULL,\n"
            ."  PRIMARY KEY (id),\n"
            ."  UNIQUE KEY token (token),\n"
            ."  KEY pid (pid),\n"
            ."  KEY tid (tid)\n"
            .") ENGINE=MyISAM{$collation};"
        );
    }

    $group = array(
        'name' => 'dice_rolls',
        'title' => 'Dice Rolls',
        'description' => 'Settings for Dice Rolls plugin',
        'disporder' => 70,
        'isdefault' => 0
    );

    $gid = (int)$db->insert_query('settinggroups', $group);

    $settings = array(
        array(
            'name' => 'dice_rolls_enabled',
            'title' => 'Enable Dice Rolls',
            'description' => 'Enable dice rolls parsing and generation.',
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 1
        ),
        array(
            'name' => 'dice_rolls_allowed_groups',
            'title' => 'Groups allowed to roll',
            'description' => 'Comma-separated usergroup IDs allowed to create new rolls using expressions (e.g. 1,2,4). Leave empty to allow all registered users.',
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 2
        ),
        array(
            'name' => 'dice_rolls_max_tags',
            'title' => 'Max rolls per post',
            'description' => 'Maximum number of dice expressions per single post save.',
            'optionscode' => 'numeric',
            'value' => '20',
            'disporder' => 3
        ),
        array(
            'name' => 'dice_rolls_max_dice',
            'title' => 'Max dice count (N)',
            'description' => 'Maximum N in NdS.',
            'optionscode' => 'numeric',
            'value' => '50',
            'disporder' => 4
        ),
        array(
            'name' => 'dice_rolls_max_sides',
            'title' => 'Max sides (S)',
            'description' => 'Maximum S in NdS.',
            'optionscode' => 'numeric',
            'value' => '1000',
            'disporder' => 5
        ),
        array(
            'name' => 'dice_rolls_allow_modifier',
            'title' => 'Allow modifiers (+/-M)',
            'description' => 'Allow expressions like 1d20+5 or 2d6-1.',
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 6
        ),
        array(
            'name' => 'dice_rolls_max_modifier',
            'title' => 'Max modifier (|M|)',
            'description' => 'Maximum absolute value of modifier M.',
            'optionscode' => 'numeric',
            'value' => '100000',
            'disporder' => 7
        ),
        array(
            'name' => 'dice_rolls_show_detail',
            'title' => 'Show roll details',
            'description' => 'Show each die value in output.',
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 8
        ),
        array(
            'name' => 'dice_rolls_edit_policy',
            'title' => 'Edit policy',
            'description' => 'How to treat edits of posts that already contain rolls created in that post.',
            'optionscode' => "select\nlock=Lock (cannot remove/replace existing rolls; cannot add new rolls)\nadd=Allow adding new rolls (existing rolls fixed)\nreroll=Allow removing/replacing rolls",
            'value' => 'lock',
            'disporder' => 9
        ),
        array(
            'name' => 'dice_rolls_max_render_tags',
            'title' => 'Max rendered dice tags per message',
            'description' => 'Limit how many [dice=r...] tokens will be resolved on display (protection against abuse).',
            'optionscode' => 'numeric',
            'value' => '200',
            'disporder' => 10
        ),
    );

    foreach($settings as $s) {
        $s['gid'] = $gid;
        $db->insert_query('settings', $s);
    }

    if(function_exists('rebuild_settings')) {
        rebuild_settings();
    }
}

function dice_rolls_uninstall()
{
    global $db;

    if($db->table_exists('dice_rolls')) {
        $db->drop_table('dice_rolls');
    }

    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='dice_rolls'", array('limit' => 1)), 'gid');
    if($gid) {
        $db->delete_query('settings', "gid='{$gid}'");
        $db->delete_query('settinggroups', "gid='{$gid}'");
    }

    if(function_exists('rebuild_settings')) {
        rebuild_settings();
    }
}

function dice_rolls_activate()
{
    global $db;

    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='dice_rolls'", array('limit' => 1)), 'gid');
    if(!$gid) {
        return;
    }

    $exists = (int)$db->fetch_field($db->simple_select('settings', 'sid', "name='dice_rolls_max_render_tags'", array('limit' => 1)), 'sid');
    if(!$exists) {
        $db->insert_query('settings', array(
            'name' => 'dice_rolls_max_render_tags',
            'title' => 'Max rendered dice tags per message',
            'description' => 'Limit how many [dice=r...] tokens will be resolved on display (protection against abuse).',
            'optionscode' => 'numeric',
            'value' => '200',
            'disporder' => 10,
            'gid' => $gid
        ));

        if(function_exists('rebuild_settings')) {
            rebuild_settings();
        }
    }
}

function dice_rolls_deactivate() {}

function dice_rolls_boot_lang()
{
    global $lang;

    if(!isset($lang->dice_rolls_err_invalid_expr)) {
        $lang->dice_rolls_err_invalid_expr = 'Invalid dice expression. Use NdS or NdS+M / NdS-M (example: 1d20, 2d6+3).';
    }
    if(!isset($lang->dice_rolls_err_limit_tags)) {
        $lang->dice_rolls_err_limit_tags = 'Too many dice rolls in one message.';
    }
    if(!isset($lang->dice_rolls_err_limit_values)) {
        $lang->dice_rolls_err_limit_values = 'Dice expression exceeds configured limits.';
    }
    if(!isset($lang->dice_rolls_err_modifiers_disabled)) {
        $lang->dice_rolls_err_modifiers_disabled = 'Dice modifiers are disabled.';
    }
    if(!isset($lang->dice_rolls_err_permission)) {
        $lang->dice_rolls_err_permission = 'You are not allowed to create dice rolls.';
    }
    if(!isset($lang->dice_rolls_err_edit_locked)) {
        $lang->dice_rolls_err_edit_locked = 'This post contains dice rolls. Editing rolls is not allowed by current settings.';
    }
    if(!isset($lang->dice_rolls_err_edit_remove_forbidden)) {
        $lang->dice_rolls_err_edit_remove_forbidden = 'This post contains dice rolls. Removing or replacing existing rolls is not allowed.';
    }
}

function dice_rolls_enabled()
{
    global $mybb;
    return !empty($mybb->settings['dice_rolls_enabled']);
}

function dice_rolls_user_can_roll()
{
    global $mybb;

    if((int)$mybb->user['uid'] <= 0) {
        return false;
    }

    $allowed = trim((string)($mybb->settings['dice_rolls_allowed_groups'] ?? ''));
    if($allowed === '') {
        return true;
    }

    $allowed_ids = array();
    foreach(explode(',', $allowed) as $g) {
        $g = (int)trim($g);
        if($g > 0) {
            $allowed_ids[$g] = true;
        }
    }

    if(!$allowed_ids) {
        return true;
    }

    $groups = array((int)$mybb->user['usergroup']);
    if(!empty($mybb->user['additionalgroups'])) {
        foreach(explode(',', (string)$mybb->user['additionalgroups']) as $g) {
            $g = (int)trim($g);
            if($g > 0) {
                $groups[] = $g;
            }
        }
    }

    foreach($groups as $g) {
        if(isset($allowed_ids[$g])) {
            return true;
        }
    }

    return false;
}

function dice_rolls_protect_html_codeblocks($message, &$blocks)
{
    $blocks = array();
    $i = 0;

    $patterns = array(
        '~<code\b[^>]*>.*?</code>~is',
        '~<pre\b[^>]*>.*?</pre>~is',
        '~<textarea\b[^>]*>.*?</textarea>~is',
    );

    foreach($patterns as $pattern) {
        $message = preg_replace_callback($pattern, function($m) use (&$blocks, &$i) {
            $key = "@@DICEHTMLBLOCK{$i}@@";
            $blocks[$key] = $m[0];
            $i++;
            return $key;
        }, $message);
    }

    return $message;
}

function dice_rolls_protect_blocks($message, &$blocks, $tags = 'code|php|noparse|quote')
{
    $blocks = array();
    $i = 0;
    $tags = (string)$tags;
    $pattern = '~\[(' . $tags . ')(?:=[^\]]+)?\](.*?)\[/\1\]~is';
    $guard = 0;

    while($guard < 50 && preg_match($pattern, $message)) {
        $message = preg_replace_callback($pattern, function($m) use (&$blocks, &$i) {
            $key = "@@DICEBLOCK{$i}@@";
            $blocks[$key] = $m[0];
            $i++;
            return $key;
        }, $message);
        $guard++;
    }

    return $message;
}

function dice_rolls_restore_blocks($message, $blocks)
{
    if(!$blocks) {
        return $message;
    }

    foreach($blocks as $key => $value) {
        $message = str_replace($key, $value, $message);
    }

    return $message;
}

function dice_rolls_is_token($attr)
{
    $attr = trim((string)$attr);
    return (bool)preg_match('~^r[a-f0-9]{16,39}$~i', $attr);
}

function dice_rolls_parse_expr($raw)
{
    $raw = strtolower(trim((string)$raw));
    $raw = preg_replace('~\s+~', '', $raw);

    if($raw === '') {
        return null;
    }

    if($raw[0] === 'd') {
        $raw = '1'.$raw;
    }

    if(!preg_match('~^(\d{1,4})d(\d{1,6})([+-]\d{1,9})?$~', $raw, $m)) {
        return null;
    }

    $n = (int)$m[1];
    $s = (int)$m[2];
    $mod = 0;
    if(isset($m[3]) && $m[3] !== '') {
        $mod = (int)$m[3];
    }

    $expr = $n.'d'.$s;
    if($mod > 0) {
        $expr .= '+'.$mod;
    } elseif($mod < 0) {
        $expr .= (string)$mod;
    }

    return array('n' => $n, 's' => $s, 'mod' => $mod, 'expr' => $expr);
}

function dice_rolls_extract_exprs_count($message)
{
    if(stripos($message, '[dice=') === false) {
        return 0;
    }

    $protected = dice_rolls_protect_blocks($message, $blocks);
    preg_match_all('~\[dice=([^\]]+)\]~i', $protected, $mm);

    $count = 0;
    if(!empty($mm[1])) {
        foreach($mm[1] as $attr) {
            $attr = trim((string)$attr);
            if(dice_rolls_is_token($attr)) {
                continue;
            }
            if(dice_rolls_parse_expr($attr) !== null) {
                $count++;
            }
        }
    }

    return $count;
}

function dice_rolls_get_owned_tokens($pid)
{
    global $db;

    $pid = (int)$pid;
    if($pid <= 0 || !$db->table_exists('dice_rolls')) {
        return array();
    }

    $tokens = array();
    $q = $db->simple_select('dice_rolls', 'token', "pid='{$pid}'");
    while($row = $db->fetch_array($q)) {
        if(!empty($row['token'])) {
            $tokens[] = $row['token'];
        }
    }

    return $tokens;
}

function dice_rolls_message_contains_all($message, $tokens)
{
    if(!$tokens) {
        return true;
    }

    $safe = dice_rolls_protect_blocks((string)$message, $blocks, 'code|php|noparse');

    foreach($tokens as $t) {
        if(stripos($safe, '[dice='.$t.']') === false) {
            return false;
        }
    }

    return true;
}

function dice_rolls_validate(&$dh)
{
    global $mybb, $lang;

    if(!dice_rolls_enabled()) {
        return;
    }

    $msg = (string)($dh->data['message'] ?? '');
    if(stripos($msg, '[dice=') === false) {
        return;
    }

    dice_rolls_boot_lang();

    $protected = dice_rolls_protect_blocks($msg, $blocks);
    preg_match_all('~\[dice=([^\]]+)\]~i', $protected, $mm);
    if(empty($mm[1])) {
        return;
    }

    $max_tags = max(0, (int)($mybb->settings['dice_rolls_max_tags'] ?? 0));
    $max_dice = max(1, (int)($mybb->settings['dice_rolls_max_dice'] ?? 1));
    $max_sides = max(2, (int)($mybb->settings['dice_rolls_max_sides'] ?? 2));
    $allow_mod = !empty($mybb->settings['dice_rolls_allow_modifier']);
    $max_mod = max(0, (int)($mybb->settings['dice_rolls_max_modifier'] ?? 0));

    $expr_count = 0;

    foreach($mm[1] as $attr) {
        $attr = trim((string)$attr);

        if(dice_rolls_is_token($attr)) {
            continue;
        }

        $parsed = dice_rolls_parse_expr($attr);
        if($parsed === null) {
            $dh->set_error('dice_rolls_err_invalid_expr');
            return;
        }

        if(!$allow_mod && $parsed['mod'] != 0) {
            $dh->set_error('dice_rolls_err_modifiers_disabled');
            return;
        }

        if($parsed['n'] < 1 || $parsed['s'] < 2) {
            $dh->set_error('dice_rolls_err_invalid_expr');
            return;
        }

        if($parsed['n'] > $max_dice || $parsed['s'] > $max_sides || abs($parsed['mod']) > $max_mod) {
            $dh->set_error('dice_rolls_err_limit_values');
            return;
        }

        $expr_count++;
        if($max_tags > 0 && $expr_count > $max_tags) {
            $dh->set_error('dice_rolls_err_limit_tags');
            return;
        }
    }

    if($expr_count > 0 && !dice_rolls_user_can_roll()) {
        $dh->set_error('dice_rolls_err_permission');
        return;
    }

    $pid = dice_rolls_get_pid_from_dh($dh);
    if($pid > 0) {
        $policy = (string)($mybb->settings['dice_rolls_edit_policy'] ?? 'lock');
        $owned = dice_rolls_get_owned_tokens($pid);

        if(($policy === 'lock' || $policy === 'add') && !dice_rolls_message_contains_all($msg, $owned)) {
            $dh->set_error('dice_rolls_err_edit_remove_forbidden');
            return;
        }

        if($policy === 'lock' && $expr_count > 0) {
            $dh->set_error('dice_rolls_err_edit_locked');
            return;
        }
    }
}

function dice_rolls_roll($n, $sides)
{
    $rolls = array();
    $sum = 0;
    for($i = 0; $i < $n; $i++) {
        $v = function_exists('random_int') ? random_int(1, $sides) : mt_rand(1, $sides);
        $rolls[] = $v;
        $sum += $v;
    }
    return array($rolls, $sum);
}

function dice_rolls_new_token()
{
    if(function_exists('random_bytes')) {
        return 'r'.bin2hex(random_bytes(12));
    }
    if(function_exists('openssl_random_pseudo_bytes')) {
        return 'r'.bin2hex(openssl_random_pseudo_bytes(12));
    }
    return 'r'.bin2hex(pack('N', mt_rand()).pack('N', mt_rand()).pack('N', mt_rand()));
}

function dice_rolls_insert_roll_row($pid, $tid, $parsed, $rolls, $total, &$token_out)
{
    global $db;

    $token_out = '';

    $row = array(
        'token' => '',
        'pid' => (int)$pid,
        'tid' => (int)$tid,
        'dice' => (int)$parsed['n'],
        'sides' => (int)$parsed['s'],
        'modifier' => (int)$parsed['mod'],
        'expr' => (string)$parsed['expr'],
        'rolls' => json_encode($rolls),
        'total' => (int)$total
    );

    for($i = 0; $i < 5; $i++) {
        $token = dice_rolls_new_token();
        $row['token'] = $token;

        $ins = $db->insert_query('dice_rolls', $row);
        if((int)$ins > 0) {
            $token_out = $token;
            return true;
        }
    }

    return false;
}

function dice_rolls_convert_message_and_store($pid, $tid, $message, &$created)
{
    global $db;

    $created = 0;

    if(stripos($message, '[dice=') === false) {
        return $message;
    }

    $protected = dice_rolls_protect_blocks($message, $blocks);

    $protected = preg_replace_callback('~\[dice=([^\]]+)\]~i', function($m) use ($pid, $tid, &$created) {
        global $mybb;

        $attr = trim((string)$m[1]);

        if(dice_rolls_is_token($attr)) {
            return '[dice='.$attr.']';
        }

        $parsed = dice_rolls_parse_expr($attr);
        if($parsed === null) {
            return $m[0];
        }

        $allow_mod = !empty($mybb->settings['dice_rolls_allow_modifier']);
        if(!$allow_mod && $parsed['mod'] != 0) {
            return $m[0];
        }

        list($rolls, $sum) = dice_rolls_roll($parsed['n'], $parsed['s']);
        $total = $sum + (int)$parsed['mod'];

        $token = '';
        $ok = dice_rolls_insert_roll_row($pid, $tid, $parsed, $rolls, $total, $token);
        if(!$ok || $token === '') {
            return $m[0];
        }

        $created++;
        return '[dice='.$token.']';
    }, $protected);

    $message = dice_rolls_restore_blocks($protected, $blocks);
    return $message;
}

function dice_rolls_get_pid_from_dh($dh)
{
    if(isset($dh->pid) && (int)$dh->pid > 0) {
        return (int)$dh->pid;
    }
    if(!empty($dh->data['pid'])) {
        return (int)$dh->data['pid'];
    }
    if(!empty($dh->post_insert_data['pid'])) {
        return (int)$dh->post_insert_data['pid'];
    }
    return 0;
}

function dice_rolls_get_tid_from_dh($dh)
{
    if(isset($dh->tid) && (int)$dh->tid > 0) {
        return (int)$dh->tid;
    }
    if(!empty($dh->data['tid'])) {
        return (int)$dh->data['tid'];
    }
    if(!empty($dh->post_insert_data['tid'])) {
        return (int)$dh->post_insert_data['tid'];
    }
    if(!empty($dh->thread_insert_data['tid'])) {
        return (int)$dh->thread_insert_data['tid'];
    }
    return 0;
}

function dice_rolls_after_insert_post(&$dh)
{
    global $db;

    if(!dice_rolls_enabled() || !$db->table_exists('dice_rolls')) {
        return;
    }

    $pid = dice_rolls_get_pid_from_dh($dh);
    if($pid <= 0) {
        return;
    }

    $tid = dice_rolls_get_tid_from_dh($dh);

    $message = (string)($dh->data['message'] ?? '');
    if($message === '' || stripos($message, '[dice=') === false) {
        return;
    }

    $expr_count = dice_rolls_extract_exprs_count($message);
    if($expr_count <= 0) {
        return;
    }

    $existing = (int)$db->fetch_field($db->simple_select('dice_rolls', 'COUNT(*) AS c', "pid='{$pid}'", array('limit' => 1)), 'c');
    if($existing > 0) {
        return;
    }

    $created = 0;
    $new_message = dice_rolls_convert_message_and_store($pid, $tid, $message, $created);

    if($created > 0 && $new_message !== $message) {
        $db->update_query('posts', array('message' => $new_message), "pid='{$pid}'");
    }
}

function dice_rolls_after_insert_thread_post(&$dh)
{
    dice_rolls_after_insert_post($dh);
}

function dice_rolls_cleanup_orphans_for_pid($pid, $message)
{
    global $db;

    $pid = (int)$pid;
    if($pid <= 0 || !$db->table_exists('dice_rolls')) {
        return;
    }

    $tokens = dice_rolls_get_owned_tokens($pid);
    if(!$tokens) {
        return;
    }

    $keep = array();
    foreach($tokens as $t) {
        if(stripos($message, '[dice='.$t.']') !== false) {
            $keep[] = $t;
        }
    }

    if(count($keep) === count($tokens)) {
        return;
    }

    if(!$keep) {
        $db->delete_query('dice_rolls', "pid='{$pid}'");
        return;
    }

    $escaped = array();
    foreach($keep as $t) {
        $escaped[] = "'".$db->escape_string($t)."'";
    }

    $db->delete_query('dice_rolls', "pid='{$pid}' AND token NOT IN(".implode(',', $escaped).")");
}

function dice_rolls_after_update(&$dh)
{
    global $db, $mybb;

    if(!dice_rolls_enabled() || !$db->table_exists('dice_rolls')) {
        return;
    }

    $pid = dice_rolls_get_pid_from_dh($dh);
    if($pid <= 0) {
        return;
    }

    $tid = dice_rolls_get_tid_from_dh($dh);

    $message = (string)($dh->data['message'] ?? '');
    if($message === '') {
        return;
    }

    $created = 0;
    $new_message = dice_rolls_convert_message_and_store($pid, $tid, $message, $created);

    if($created > 0 && $new_message !== $message) {
        $db->update_query('posts', array('message' => $new_message), "pid='{$pid}'");
        $message = $new_message;
    }

    $policy = (string)($mybb->settings['dice_rolls_edit_policy'] ?? 'lock');
    if($policy === 'reroll') {
        dice_rolls_cleanup_orphans_for_pid($pid, $message);
    }
}

function dice_rolls_on_delete_post($pid)
{
    global $db;

    if(!$db->table_exists('dice_rolls')) {
        return;
    }

    $pid = (int)$pid;
    if($pid > 0) {
        $db->delete_query('dice_rolls', "pid='{$pid}'");
    }
}

function dice_rolls_on_delete_thread($tid)
{
    global $db;

    if(!$db->table_exists('dice_rolls')) {
        return;
    }

    $tids = array();

    if(is_array($tid)) {
        foreach($tid as $t) {
            $t = (int)$t;
            if($t > 0) {
                $tids[$t] = true;
            }
        }
    } else {
        $tid = (string)$tid;
        if(strpos($tid, ',') !== false) {
            foreach(explode(',', $tid) as $t) {
                $t = (int)trim($t);
                if($t > 0) {
                    $tids[$t] = true;
                }
            }
        } else {
            $t = (int)$tid;
            if($t > 0) {
                $tids[$t] = true;
            }
        }
    }

    if(!$tids) {
        return;
    }

    foreach(array_keys($tids) as $t) {
        $pids = array();
        $q = $db->simple_select('posts', 'pid', "tid='{$t}'");
        while($row = $db->fetch_array($q)) {
            $pids[] = (int)$row['pid'];
        }

        if(!$pids) {
            continue;
        }

        foreach(array_chunk($pids, 500) as $chunk) {
            $db->delete_query('dice_rolls', 'pid IN('.implode(',', $chunk).')');
        }
    }
}

function dice_rolls_html($s)
{
    if(function_exists('htmlspecialchars_uni')) {
        return htmlspecialchars_uni($s);
    }
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dice_rolls_parse_message_end(&$message)
{
    global $db, $mybb;

    if(!dice_rolls_enabled() || !$db->table_exists('dice_rolls')) {
        return;
    }

    if(stripos($message, '[dice=') === false) {
        return;
    }

    $protected = dice_rolls_protect_html_codeblocks($message, $blocks);

    if(stripos($protected, '[dice=') === false) {
        return;
    }

    preg_match_all('~\[dice=([^\]]+)\]~i', $protected, $mm);
    if(empty($mm[1])) {
        return;
    }

    $tokens = array();
    foreach($mm[1] as $attr) {
        $attr = trim((string)$attr);
        if(dice_rolls_is_token($attr)) {
            $tokens[$attr] = true;
        }
    }

    if(!$tokens) {
        return;
    }

    $max_render = (int)($mybb->settings['dice_rolls_max_render_tags'] ?? 200);
    if($max_render <= 0) {
        $max_render = 200;
    }

    $token_list = array_keys($tokens);
    if(count($token_list) > $max_render) {
        $token_list = array_slice($token_list, 0, $max_render);
    }

    $escaped = array();
    foreach($token_list as $t) {
        $escaped[] = "'".$db->escape_string($t)."'";
    }

    $map = array();
    $q = $db->simple_select('dice_rolls', '*', 'token IN('.implode(',', $escaped).')');
    while($row = $db->fetch_array($q)) {
        if(!empty($row['token'])) {
            $map[$row['token']] = $row;
        }
    }

    if(!$map) {
        return;
    }

    $show_detail = !empty($mybb->settings['dice_rolls_show_detail']);

    $protected = preg_replace_callback('~\[dice=([^\]]+)\]~i', function($m) use ($map, $show_detail) {
        $attr = trim((string)$m[1]);
        if(!dice_rolls_is_token($attr) || empty($map[$attr])) {
            return $m[0];
        }

        $row = $map[$attr];

        $expr = dice_rolls_html((string)$row['expr']);
        $total = (int)$row['total'];
        $mod = (int)$row['modifier'];

        $rolls = array();
        $decoded = json_decode((string)$row['rolls'], true);
        if(is_array($decoded)) {
            foreach($decoded as $v) {
                $rolls[] = (int)$v;
            }
        }

        $detail_html = '';
        if($show_detail && $rolls) {
            $parts = array();
            foreach($rolls as $v) {
                $parts[] = (string)$v;
            }
            $calc = implode(' + ', $parts);
            if($mod !== 0) {
                if($mod > 0) {
                    $calc .= ' + '.$mod;
                } else {
                    $calc .= ' - '.abs($mod);
                }
            }
            $detail_html = ' <span class="dice-detail">(' . dice_rolls_html($calc) . ')</span>';
        }

        $token = dice_rolls_html($attr);

        return '<span class="dice" data-dice="'.$token.'"><span class="dice-expr">'.$expr.'</span> → <span class="dice-total">'.$total.'</span>'.$detail_html.'</span>';
    }, $protected);

    $message = dice_rolls_restore_blocks($protected, $blocks);
}
