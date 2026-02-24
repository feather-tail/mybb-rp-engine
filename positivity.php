<?php
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'positivity.php');

$templatelist = 'positivity_history,positivity_history_vote,positivity_history_no_votes,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start';

require_once './global.php';
require_once MYBB_ROOT.'inc/class_parser.php';

$lang->load('positivity');
$lang->load('reputation');

if(empty($mybb->settings['positivity_enabled']))
{
	error($lang->positivity_disabled);
}

if($mybb->usergroup['canview'] != 1)
{
	error_no_permission();
}

if($mybb->usergroup['canviewprofiles'] == 0)
{
	error_no_permission();
}

$giver_uid = $mybb->get_input('uid', MyBB::INPUT_INT);
$user = get_user($giver_uid);
if(!$user)
{
	error($lang->positivity_no_uid);
}

$access = $mybb->settings['positivity_page_access'] ?? 'all';
$viewer_uid = (int)$mybb->user['uid'];
$is_mod = !empty($mybb->user['ismoderator']) || !empty($mybb->usergroup['issupermod']);
$is_super_admin = ($viewer_uid > 0 && is_member($mybb->settings['super_admins'], $viewer_uid));

if($access === 'registered' && $viewer_uid == 0)
{
	error_no_permission();
}
elseif($access === 'mods' && !$is_mod && !$is_super_admin)
{
	error_no_permission();
}
elseif($access === 'owner' && $viewer_uid != (int)$user['uid'] && !$is_super_admin)
{
	error_no_permission();
}

$action = $mybb->get_input('action');
if($action === 'recount')
{
	if(!$is_super_admin)
	{
		error_no_permission();
	}

	if($mybb->request_method === 'post')
	{
		verify_post_check($mybb->get_input('my_post_key'));
		if(function_exists('positivity_recount_all'))
		{
			positivity_recount_all();
		}
		redirect("positivity.php?uid={$giver_uid}", $lang->positivity_recount_done);
	}
	else
	{
		$confirm = "<form action=\"positivity.php?uid={$giver_uid}&action=recount\" method=\"post\">
		<input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\" />
		<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" class=\"tborder tfixed clear\">
			<tr><td class=\"thead\"><strong>{$lang->positivity_recount_title}</strong></td></tr>
			<tr><td class=\"trow1\">
				{$lang->positivity_recount_text}<br><br>
				<input type=\"submit\" class=\"button\" value=\"{$lang->positivity_recount_run}\" />
			</td></tr>
		</table>
		</form>";

		$user['username'] = htmlspecialchars_uni($user['username']);
		$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
		add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
		add_breadcrumb($lang->positivity_breadcrumb, "positivity.php?uid={$giver_uid}");

		output_page($header.$confirm.$footer);
		exit;
	}
}

$user['username'] = htmlspecialchars_uni($user['username']);
$lang->nav_profile = $lang->sprintf($lang->nav_profile, $user['username']);
$lang->positivity_report_for_user = $lang->sprintf($lang->positivity_report_for_user, $user['username']);

add_breadcrumb($lang->nav_profile, get_profile_link($user['uid']));
add_breadcrumb($lang->positivity_breadcrumb, "positivity.php?uid={$giver_uid}");

if(!$user['displaygroup'])
{
	$user['displaygroup'] = $user['usergroup'];
}
$display_group = usergroup_displaygroup($user['displaygroup']);

$usertitle = '';
if(trim($user['usertitle']) != '')
{
	$usertitle = $user['usertitle'];
}
elseif(!empty($display_group['usertitle']) && trim($display_group['usertitle']) != '')
{
	$usertitle = $display_group['usertitle'];
}
else
{
	$usertitles = $cache->read('usertitles');
	if(is_array($usertitles))
	{
		foreach($usertitles as $title)
		{
			if($title['posts'] <= $user['postnum'])
			{
				$usertitle = $title['title'];
				break;
			}
		}
	}
}
$usertitle = htmlspecialchars_uni($usertitle);

$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);

$sumExpr = 'SUM(reputation)';
if(($mybb->settings['positivity_count_mode'] ?? 'signed') === 'positive_only')
{
	$sumExpr = 'SUM(CASE WHEN reputation>0 THEN reputation ELSE 0 END)';
}

$query = $db->query("
	SELECT {$sumExpr} AS total, COUNT(rid) AS total_votes
	FROM ".TABLE_PREFIX."reputation
	WHERE adduid = '{$giver_uid}'
");
$sync = $db->fetch_array($query);

$sync_total = (int)$sync['total'];
$total_votes = (int)$sync['total_votes'];

if((int)$user['positiv'] !== $sync_total)
{
	$db->update_query('users', array('positiv' => $sync_total), "uid='{$giver_uid}'");
	$user['positiv'] = $sync_total;
}

$total_positiv = (int)$user['positiv'];

if($total_positiv < 0) $total_class = '_minus';
elseif($total_positiv > 0) $total_class = '_plus';
else $total_class = '_neutral';

$rep_total = my_number_format($total_positiv);

$query = $db->simple_select('reputation', 'COUNT(rid) AS rep_posts', "adduid='{$giver_uid}' AND pid>0");
$rep_post_count = (int)$db->fetch_field($query, 'rep_posts');
$rep_posts = my_number_format($rep_post_count);
$rep_members = my_number_format($total_votes - $rep_post_count);

$positive_count = $negative_count = $neutral_count = 0;
$positive_week = $negative_week = $neutral_week = 0;
$positive_month = $negative_month = $neutral_month = 0;
$positive_6months = $negative_6months = $neutral_6months = 0;

$last_week = TIME_NOW - 604800;
$last_month = TIME_NOW - 2678400;
$last_6months = TIME_NOW - 16070400;

$query = $db->simple_select('reputation', 'reputation, dateline', "adduid='{$giver_uid}'");
while($v = $db->fetch_array($query))
{
	$repv = (int)$v['reputation'];
	$dt = (int)$v['dateline'];

	if($repv > 0)
	{
		$positive_count++;
		if($dt >= $last_week) $positive_week++;
		if($dt >= $last_month) $positive_month++;
		if($dt >= $last_6months) $positive_6months++;
	}
	elseif($repv < 0)
	{
		$negative_count++;
		if($dt >= $last_week) $negative_week++;
		if($dt >= $last_month) $negative_month++;
		if($dt >= $last_6months) $negative_6months++;
	}
	else
	{
		$neutral_count++;
		if($dt >= $last_week) $neutral_week++;
		if($dt >= $last_month) $neutral_month++;
		if($dt >= $last_6months) $neutral_6months++;
	}
}

$f_positive_count = my_number_format($positive_count);
$f_negative_count = my_number_format($negative_count);
$f_neutral_count = my_number_format($neutral_count);

$f_positive_week = my_number_format($positive_week);
$f_negative_week = my_number_format($negative_week);
$f_neutral_week = my_number_format($neutral_week);

$f_positive_month = my_number_format($positive_month);
$f_negative_month = my_number_format($negative_month);
$f_neutral_month = my_number_format($neutral_month);

$f_positive_6months = my_number_format($positive_6months);
$f_negative_6months = my_number_format($negative_6months);
$f_neutral_6months = my_number_format($neutral_6months);

$show_selected = array('all' => '', 'positive' => '', 'neutral' => '', 'negative' => '');
$conditions = '';
switch($mybb->get_input('show'))
{
	case 'positive':
		$conditions = 'AND r.reputation>0';
		$show_selected['positive'] = 'selected="selected"';
		break;
	case 'neutral':
		$conditions = 'AND r.reputation=0';
		$show_selected['neutral'] = 'selected="selected"';
		break;
	case 'negative':
		$conditions = 'AND r.reputation<0';
		$show_selected['negative'] = 'selected="selected"';
		break;
	default:
		$conditions = '';
		$show_selected['all'] = 'selected="selected"';
		break;
}

$sort_selected = array('dateline' => '', 'username' => '', 'value' => '');
$sort = $mybb->get_input('sort');

$default_sort = $mybb->settings['positivity_history_default_sort'] ?? 'dateline_desc';
$order = 'r.dateline DESC';

if($sort === 'username')
{
	$order = 'u.username ASC, r.dateline DESC';
	$sort_selected['username'] = 'selected="selected"';
}
elseif($sort === 'value')
{
	$order = 'r.reputation DESC, r.dateline DESC';
	$sort_selected['value'] = 'selected="selected"';
}
else
{
	if($default_sort === 'dateline_asc') $order = 'r.dateline ASC';
	elseif($default_sort === 'value_asc') $order = 'r.reputation ASC, r.dateline DESC';
	elseif($default_sort === 'value_desc') $order = 'r.reputation DESC, r.dateline DESC';
	elseif($default_sort === 'username_asc') $order = 'u.username ASC, r.dateline DESC';
	else $order = 'r.dateline DESC';

	$sort_selected['dateline'] = 'selected="selected"';
}

$query = $db->simple_select("reputation r", "COUNT(r.rid) AS cnt", "r.adduid='{$giver_uid}' {$conditions}");
$reputation_count = (int)$db->fetch_field($query, 'cnt');

$perpage = (int)($mybb->settings['positivity_history_perpage'] ?? 15);
if($perpage < 1) $perpage = 15;

$page = $mybb->get_input('page', MyBB::INPUT_INT);
if($page < 1) $page = 1;

$start = ($page - 1) * $perpage;
if($start < 0) $start = 0;

$multipage = '';
if($reputation_count > 0)
{
	$multipage = multipage($reputation_count, $perpage, $page, "positivity.php?uid={$giver_uid}&show=".$mybb->get_input('show')."&sort=".$mybb->get_input('sort'));
}

$query = $db->query("
	SELECT r.*, r.uid AS rated_uid,
	       u.uid, u.username, u.reputation AS user_reputation, u.usergroup AS user_usergroup, u.displaygroup AS user_displaygroup
	FROM ".TABLE_PREFIX."reputation r
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = r.uid)
	WHERE r.adduid='{$giver_uid}' {$conditions}
	ORDER BY {$order}
	LIMIT {$start}, {$perpage}
");

$reputation_cache = $post_cache = array();
while($row = $db->fetch_array($query))
{
	$reputation_cache[] = $row;
	if(!empty($row['pid']) && !isset($post_cache[(int)$row['pid']]))
	{
		$post_cache[(int)$row['pid']] = (int)$row['pid'];
	}
}

$post_reputation = array();
if(!empty($post_cache) && !empty($mybb->settings['positivity_history_show_postlink']))
{
	$pids = implode(',', $post_cache);
	$sql = array("p.pid IN ({$pids})");

	$unviewable = get_unviewable_forums(true);
	if($unviewable) $sql[] = "p.fid NOT IN ({$unviewable})";

	$inactive = get_inactive_forums();
	if($inactive) $sql[] = "p.fid NOT IN ({$inactive})";

	if(!$mybb->user['ismoderator'])
	{
		$sql[] = "p.visible='1'";
		$sql[] = "t.visible='1'";
	}

	$sql = implode(' AND ', $sql);

	$q2 = $db->query("
		SELECT p.pid, p.uid, p.fid, p.visible, t.tid, t.subject, t.visible AS thread_visible
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = p.tid)
		WHERE {$sql}
	");

	$forumpermissions = array();
	while($post = $db->fetch_array($q2))
	{
		if(($post['visible'] == 0 || $post['thread_visible'] == 0) && !is_moderator($post['fid'], 'canviewunapprove'))
		{
			continue;
		}
		if(($post['visible'] == -1 || $post['thread_visible'] == -1) && !is_moderator($post['fid'], 'canviewdeleted'))
		{
			continue;
		}
		if(!isset($forumpermissions[$post['fid']]))
		{
			$forumpermissions[$post['fid']] = forum_permissions($post['fid']);
		}
		if(isset($forumpermissions[$post['fid']]['canonlyviewownthreads']) && $forumpermissions[$post['fid']]['canonlyviewownthreads'] == 1 && (int)$post['uid'] != (int)$mybb->user['uid'])
		{
			continue;
		}
		$post_reputation[(int)$post['pid']] = $post;
	}
}

$parser = new postParser;
$reputation_parser = array(
	"allow_html" => 0,
	"allow_mycode" => 0,
	"allow_smilies" => 1,
	"allow_imgcode" => 0,
	"filter_badwords" => 1
);

$reputation_votes = '';

foreach($reputation_cache as $reputation_vote)
{
	$rid = (int)$reputation_vote['rid'];

	$target_rep_link = '';
	$target_plain = $lang->na;

	if(!empty($reputation_vote['username']))
	{
		$target_plain = htmlspecialchars_uni($reputation_vote['username']);

		$target_name = format_name($target_plain, (int)$reputation_vote['user_usergroup'], (int)$reputation_vote['user_displaygroup']);
		$target_name = build_profile_link($target_name, (int)$reputation_vote['uid']);

		$rep_num = (int)$reputation_vote['user_reputation'];
		$rep_fmt = get_reputation($rep_num, (int)$reputation_vote['uid']);
		$target_rep_link = "<a href=\"reputation.php?uid=".(int)$reputation_vote['uid']."\">{$rep_fmt}</a>";

		$target_line = "{$target_name} <span class=\"smalltext\">({$target_rep_link})";
	}
	else
	{
		$target_line = "{$lang->na} <span class=\"smalltext\">";
	}

	$vote_reputation = (int)$reputation_vote['reputation'];

	if($vote_reputation < 0)
	{
		$status_class = "trow_reputation_negative";
		$vote_type_class = "reputation_negative";
		$vote_type = $lang->negative;
		$vote_value = (string)$vote_reputation;
	}
	elseif($vote_reputation == 0)
	{
		$status_class = "trow_reputation_neutral";
		$vote_type_class = "reputation_neutral";
		$vote_type = $lang->neutral;
		$vote_value = "0";
	}
	else
	{
		$status_class = "trow_reputation_positive";
		$vote_type_class = "reputation_positive";
		$vote_type = $lang->positive;
		$vote_value = "+".$vote_reputation;
	}

	$vote_value = "({$vote_value})";

	$last_updated_date = my_date('relative', (int)$reputation_vote['dateline']);
	$last_updated = $lang->sprintf($lang->last_updated, $last_updated_date);

	$postrep_given = $lang->sprintf($lang->postrep_given_nolink, $target_plain);

	if(!empty($reputation_vote['pid']) && !empty($mybb->settings['positivity_history_show_postlink']))
	{
		if(isset($post_reputation[(int)$reputation_vote['pid']]))
		{
			$thread_link = get_thread_link((int)$post_reputation[(int)$reputation_vote['pid']]['tid']);
			$subject = htmlspecialchars_uni($parser->parse_badwords($post_reputation[(int)$reputation_vote['pid']]['subject']));
			$thread_link = $lang->sprintf($lang->postrep_given_thread, $thread_link, $subject);

			$link = get_post_link((int)$reputation_vote['pid'])."#pid".(int)$reputation_vote['pid'];
			$postrep_given = $lang->sprintf($lang->postrep_given, $link, $target_plain, $thread_link);
		}
	}

	$target_line .= " - {$last_updated}<br>{$postrep_given}<br></span>";

	$comment = $lang->no_comment;
	if(!empty($mybb->settings['positivity_history_show_comments']))
	{
		$comment = $parser->parse_message($reputation_vote['comments'], $reputation_parser);
		if(trim($comment) === '')
		{
			$comment = $lang->no_comment;
		}
	}

	$vote_line = "<strong class=\"{$vote_type_class}\">{$vote_type} {$vote_value}:</strong> {$comment}";

	eval("\$reputation_votes .= \"".$templates->get('positivity_history_vote', 1, 0)."\";");
}

if(!$reputation_votes)
{
	eval("\$reputation_votes = \"".$templates->get('positivity_history_no_votes', 1, 0)."\";");
}

$user_uid = (int)$user['uid'];

eval("\$page = \"".$templates->get('positivity_history', 1, 0)."\";");
output_page($page);