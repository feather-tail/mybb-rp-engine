<?php
if (!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Direct access not allowed.');
}

global $page, $mybb, $db, $lang;

if (method_exists($lang, 'load')) {
    $lang->load('custompostcounter');
}

// Подтянем плагин для доступа к helper-функциям
$plugin_file = MYBB_ROOT.'inc/plugins/custompostcounter.php';
if (file_exists($plugin_file)) {
    require_once $plugin_file;
}

$page->add_breadcrumb_item($lang->cpc_tools_stats);
$page->output_header($lang->cpc_tools_stats);

// include классов формы (для старых сборок)
if (!class_exists('Form')) {
    require_once MYBB_ROOT.'inc/class_form.php';
}
if (!class_exists('FormContainer')) {
    require_once MYBB_ROOT.'inc/class_form.php';
}

$sub_tabs['custompostcounter_stats'] = [
    'title'       => $lang->cpc_tools_stats,
    'link'        => "index.php?module=tools-custompostcounter_stats",
    'description' => $lang->cpc_tools_stats_desc,
];

$page->output_nav_tabs($sub_tabs, 'custompostcounter_stats');

$prefix  = TABLE_PREFIX;
$now     = TIME_NOW;
$days7   = $now - 7*24*3600;
$days30  = $now - 30*24*3600;
$days365 = $now - 365*24*3600;

$fids = function_exists('custompostcounter_get_valid_forums') ? custompostcounter_get_valid_forums() : [];
if (!$fids) {
    echo '<div class="warning">'.$lang->cpc_tools_stats_no_forums.'</div>';
    $page->output_footer();
    exit;
}
$fidList = implode(',', array_map('intval', $fids));

$count_first = function_exists('custompostcounter_count_firstpost_enabled') && custompostcounter_count_firstpost_enabled();

$first_cond_expr = "1=1";
if (!$count_first) {
    // исключить первый пост темы
    $first_cond_expr = "p.pid != t.firstpost";
}

$make_sql = function($since_ts = null) use ($fidList, $prefix, $first_cond_expr) {
    $date_cond = $since_ts ? "AND p.dateline >= ".(int)$since_ts : "";
    return "
        SELECT p.uid, COUNT(*) AS c
        FROM {$prefix}posts p
        INNER JOIN {$prefix}threads t ON t.tid = p.tid
        WHERE p.visible = 1
          AND t.visible = 1
          AND p.fid IN ({$fidList})
          AND {$first_cond_expr}
          {$date_cond}
        GROUP BY p.uid
    ";
};

$result_all   = $db->query($make_sql(null));
$result_y     = $db->query($make_sql($days365));
$result_m     = $db->query($make_sql($days30));
$result_w     = $db->query($make_sql($days7));

// Собираем в словарь uid => [w,m,y,all]
$stats = [];
$uids = [];

$acc = function($res, $key) use (&$stats, &$uids) {
    global $db;
    while ($row = $db->fetch_array($res)) {
        $uid = (int)$row['uid'];
        $cnt = (int)$row['c'];
        if ($uid <= 0) continue;
        $uids[$uid] = true;
        if (!isset($stats[$uid])) $stats[$uid] = ['w'=>0,'m'=>0,'y'=>0,'a'=>0];
        $stats[$uid][$key] = $cnt;
    }
};

$acc($result_w, 'w');
$acc($result_m, 'm');
$acc($result_y, 'y');
$acc($result_all, 'a');

$uids = array_map('intval', array_keys($uids));
sort($uids);

// Сортировка по "за всё время" убыв.
usort($uids, function($u1, $u2) use ($stats) {
    return ($stats[$u2]['a'] <=> $stats[$u1]['a']) ?: ($u1 <=> $u2);
});

// Заголовок
echo '<div class="forum_settings_bit">';
echo '<p>'.$lang->cpc_tools_stats_hint.($count_first ? ' ('.$lang->cpc_tools_stats_first_on.')' : ' ('.$lang->cpc_tools_stats_first_off.')').'</p>';
echo '</div>';

echo '<div class="border_wrapper">';
echo '<div class="title">'.$lang->cpc_tools_stats_title.'</div>';
echo '<table class="general" cellspacing="0">';
echo '<thead><tr>';
echo '<th style="text-align:left;">'.$lang->cpc_tools_stats_user.'</th>';
echo '<th>'.$lang->cpc_tools_stats_week.'</th>';
echo '<th>'.$lang->cpc_tools_stats_month.'</th>';
echo '<th>'.$lang->cpc_tools_stats_year.'</th>';
echo '<th>'.$lang->cpc_tools_stats_all.'</th>';
echo '</tr></thead><tbody>';

$total = ['w'=>0,'m'=>0,'y'=>0,'a'=>0];

foreach ($uids as $uid) {
    $u = get_user($uid);
    $name = $u ? build_profile_link(htmlspecialchars_uni($u['username']), $uid, "_blank") : ('UID '.$uid);
    $w = (int)($stats[$uid]['w'] ?? 0);
    $m = (int)($stats[$uid]['m'] ?? 0);
    $y = (int)($stats[$uid]['y'] ?? 0);
    $a = (int)($stats[$uid]['a'] ?? 0);
    $total['w'] += $w; $total['m'] += $m; $total['y'] += $y; $total['a'] += $a;

    echo '<tr>';
    echo '<td style="text-align:left;">'.$name.'</td>';
    echo '<td style="text-align:center;">'.$w.'</td>';
    echo '<td style="text-align:center;">'.$m.'</td>';
    echo '<td style="text-align:center;">'.$y.'</td>';
    echo '<td style="text-align:center;"><strong>'.$a.'</strong></td>';
    echo '</tr>';
}

echo '</tbody>';
echo '<tfoot><tr>';
echo '<td style="text-align:left;"><strong>'.$lang->cpc_tools_stats_total.'</strong></td>';
echo '<td style="text-align:center;"><strong>'.$total['w'].'</strong></td>';
echo '<td style="text-align:center;"><strong>'.$total['m'].'</strong></td>';
echo '<td style="text-align:center;"><strong>'.$total['y'].'</strong></td>';
echo '<td style="text-align:center;"><strong>'.$total['a'].'</strong></td>';
echo '</tr></tfoot>';
echo '</table>';
echo '</div>';

$page->output_footer();
