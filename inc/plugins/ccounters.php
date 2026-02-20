<?php

if(!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.');
}

global $plugins;
$plugins->add_hook('postbit', 'ccounters_postbit');
$plugins->add_hook('showthread_start', 'ccounters_maybe_inject_assets_showthread');
$plugins->add_hook('newreply_start', 'ccounters_maybe_inject_assets_posting');
$plugins->add_hook('newthread_start', 'ccounters_maybe_inject_assets_posting');
$plugins->add_hook('editpost_start', 'ccounters_maybe_inject_assets_editpost');
$plugins->add_hook('usercp_options_end', 'ccounters_usercp_options_end');
$plugins->add_hook('usercp_do_options_end', 'ccounters_usercp_do_options_end');

function ccounters_info()
{
	return [
		'name' => 'Character Counters (Posts + Editor)',
		'description' => 'Counts characters in posts and in the editor (after removing MyCode and collapsing whitespace). Per-forum + per-user toggle.',
		'website' => '',
		'author' => 'ChatGPT',
		'authorsite' => '',
		'version' => '1.0.0',
		'compatibility' => '18*'
	];
}

function ccounters_is_installed()
{
	global $db;
	return $db->field_exists('ccounters_disable', 'users');
}

function ccounters_install()
{
	global $db;

	if(!$db->field_exists('ccounters_disable', 'users')) {
		$db->add_column('users', 'ccounters_disable', "tinyint(1) NOT NULL DEFAULT '0'");
	}

	$gid = (int)$db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='ccounters'"),
		'gid'
	);

	if(!$gid) {
		$gid = (int)$db->insert_query('settinggroups', [
			'name' => 'ccounters',
			'title' => 'Счётчики символов',
			'description' => 'Подсчёт символов в постах и редакторе (без MyCode/BB-кодов и лишних пробелов).',
			'disporder' => 50,
			'isdefault' => 0
		]);
	}

	$settings = [
		[
			'name' => 'ccounters_forums',
			'title' => 'Форумы (FID)',
			'description' => 'Список ID форумов через запятую. 0 = все форумы. Если указан родительский форум — включится и в его подфорумах.',
			'optionscode' => 'text',
			'value' => '0',
			'disporder' => 1,
			'gid' => $gid
		],
		[
			'name' => 'ccounters_in_posts',
			'title' => 'Показывать в постах',
			'description' => 'Добавляет строку с количеством символов под сообщением (после подписи, если подпись включена).',
			'optionscode' => 'yesno',
			'value' => '1',
			'disporder' => 2,
			'gid' => $gid
		],
		[
			'name' => 'ccounters_in_editor',
			'title' => 'Показывать в форме ответа',
			'description' => 'Живой счётчик под редактором (newthread/newreply/editpost + quick reply в теме).',
			'optionscode' => 'yesno',
			'value' => '1',
			'disporder' => 3,
			'gid' => $gid
		],
	];

	foreach($settings as $s) {
		$exists = (int)$db->fetch_field(
			$db->simple_select('settings', 'sid', "name='".$db->escape_string($s['name'])."'"),
			'sid'
		);
		if(!$exists) {
			$db->insert_query('settings', $s);
		}
	}

	rebuild_settings();
}

function ccounters_uninstall()
{
	global $db;

	ccounters_deactivate();

	$db->delete_query('settings', "name IN ('ccounters_forums','ccounters_in_posts','ccounters_in_editor')");
	$db->delete_query('settinggroups', "name='ccounters'");

	if($db->field_exists('ccounters_disable', 'users')) {
		$db->drop_column('users', 'ccounters_disable');
	}

	rebuild_settings();
}

function ccounters_activate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// 1) Postbit: after signature, else after message (only if signature token not present)
	find_replace_templatesets('postbit', '#\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\}#i', '', 0);
	find_replace_templatesets('postbit_classic', '#\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\}#i', '', 0);

	$pat_sig = '#(\{\$post\[(?:\'|")signature(?:\'|")\]\})(?!\s*\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\})#i';
	$rep_sig = '$1'."\n".'{$post[\'ccounters_post\']}';
	find_replace_templatesets('postbit', $pat_sig, $rep_sig);
	find_replace_templatesets('postbit_classic', $pat_sig, $rep_sig);

	$pat_msg_no_sig = '#(\{\$post\[(?:\'|")message(?:\'|")\]\})(?![\s\S]*\{\$post\[(?:\'|")signature(?:\'|")\]\})(?!\s*\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\})#i';
	$rep_msg_no_sig = '$1'."\n".'{$post[\'ccounters_post\']}';
	find_replace_templatesets('postbit', $pat_msg_no_sig, $rep_msg_no_sig);
	find_replace_templatesets('postbit_classic', $pat_msg_no_sig, $rep_msg_no_sig);

	// 2) UserCP: insert row inside "thread view options" table (near showsigs)
	find_replace_templatesets('usercp_options', '#\{\$ccounters_usercp_row\}#i', '', 0);
	find_replace_templatesets('usercp_options', '#\{\$ccounters_usercp\}#i', '', 0);

	$pat_showsigs_row = '#(<input[^>]*\bname=(["\'])showsigs\2[^>]*>[\s\S]*?</tr>)(?!\s*\{\$ccounters_usercp_row\})#i';
	$rep_showsigs_row = '$1'."\n".'{$ccounters_usercp_row}';
	find_replace_templatesets('usercp_options', $pat_showsigs_row, $rep_showsigs_row);
}

function ccounters_deactivate()
{
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// Postbit cleanup
	find_replace_templatesets('postbit', '#\s*\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\}\s*#i', '', 0);
	find_replace_templatesets('postbit_classic', '#\s*\{\$post\[(?:\'|")ccounters_post(?:\'|")\]\}\s*#i', '', 0);

	// UserCP cleanup
	find_replace_templatesets('usercp_options', '#\s*\{\$ccounters_usercp_row\}\s*#i', '', 0);
	find_replace_templatesets('usercp_options', '#\s*\{\$ccounters_usercp\}\s*#i', '', 0);
}

function ccounters_load_lang()
{
	global $lang;

	if(!isset($lang->ccounters_chars_label)) {
		if(isset($lang) && is_object($lang) && method_exists($lang, 'load')) {
			$lang->load('ccounters');
		}
	}

	if(!isset($lang->ccounters_chars_label)) {
		$lang->ccounters_chars_label = 'Символов';
		$lang->ccounters_editor_label = 'Символов:';
		$lang->ccounters_usercp_disable = 'Отключить отображение счётчиков символов';
	}
}

function ccounters_user_disabled()
{
	global $mybb;
	return (isset($mybb->user['uid']) && (int)$mybb->user['uid'] > 0 && (int)($mybb->user['ccounters_disable'] ?? 0) === 1);
}

function ccounters_parse_forums_setting()
{
	global $mybb;
	static $cache = null;

	if($cache !== null) return $cache;

	$raw = trim((string)($mybb->settings['ccounters_forums'] ?? '0'));
	if($raw === '' || $raw === '0') {
		$cache = [0];
		return $cache;
	}

	$parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
	$out = [];
	foreach($parts as $p) {
		$fid = (int)$p;
		if($fid > 0) $out[$fid] = $fid;
	}
	$cache = $out ? array_values($out) : [0];
	return $cache;
}

function ccounters_forum_enabled($fid)
{
	$allowed = ccounters_parse_forums_setting();
	if(in_array(0, $allowed, true)) return true;

	$fid = (int)$fid;
	if($fid <= 0) return false;

	$forum = get_forum($fid);
	if(empty($forum)) return false;

	$parents = array_map('intval', explode(',', (string)($forum['parentlist'] ?? (string)$fid)));
	foreach($allowed as $a) {
		$a = (int)$a;
		if($a > 0 && in_array($a, $parents, true)) return true;
	}

	return false;
}

function ccounters_collapse_ws($text)
{
	$text = str_replace(["\r\n", "\r"], "\n", (string)$text);
	$text = preg_replace('/\s+/u', ' ', $text);
	return trim((string)$text);
}

function ccounters_html_to_text($html)
{
	$html = (string)$html;
	$html = preg_replace('~<br\s*/?>~i', "\n", $html);
	$html = preg_replace('~</p\s*>~i', "\n", $html);
	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = str_replace("\xC2\xA0", ' ', $text);
	return ccounters_collapse_ws($text);
}

function ccounters_postbit($post)
{
	global $mybb, $lang;

	ccounters_load_lang();

	$post['ccounters_post'] = '';

	if((int)($mybb->settings['ccounters_in_posts'] ?? 1) !== 1) return $post;
	if(ccounters_user_disabled()) return $post;

	if(!defined('THIS_SCRIPT') || (THIS_SCRIPT !== 'showthread.php' && THIS_SCRIPT !== 'printthread.php')) return $post;

	$fid = (int)($GLOBALS['fid'] ?? 0);
	if($fid <= 0) $fid = (int)($GLOBALS['thread']['fid'] ?? 0);
	if($fid <= 0 || !ccounters_forum_enabled($fid)) return $post;

	if(empty($post['message'])) return $post;

	$plain = ccounters_html_to_text($post['message']);
	$count = my_strlen($plain);

	$post['ccounters_post'] =
		'<div class="ccounters_post"><span class="ccounters_label">'
		.htmlspecialchars_uni($lang->ccounters_chars_label)
		.':</span> <strong class="ccounters_value">'.my_number_format($count).'</strong></div>';

	return $post;
}

function ccounters_inject_assets($contextFid)
{
	global $mybb, $lang, $headerinclude;

	static $done = false;
	if($done) return;

	ccounters_load_lang();

	if((int)($mybb->settings['ccounters_in_editor'] ?? 1) !== 1) return;
	if(ccounters_user_disabled()) return;

	$fid = (int)$contextFid;
	if($fid > 0 && !ccounters_forum_enabled($fid)) return;

	$payload = [
		'editorLabel' => (string)$lang->ccounters_editor_label,
	];

	$jsPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$headerinclude .= "\n".'<style>
.ccounters_post{margin-top:8px;font-size:11px;opacity:.85}
.ccounters_form{margin-top:6px;font-size:11px;opacity:.85}
</style>'."\n";

	$headerinclude .= "\n".'<script>
(function(){
  const i18n = '.$jsPayload.';
  const blockTags = ["img","video","youtube","media","attachment"];
  function clean(s){
    s = String(s || "").replace(/\\r\\n?/g, "\\n");
    for(const t of blockTags){
      const re = new RegExp("\\\\["+t+"(?:=[^\\\\]]*)?\\\\][\\\\s\\\\S]*?\\\\[\\\\/"+t+"\\\\]","gi");
      s = s.replace(re,"");
    }
    s = s.replace(/\\[attachment=\\d+\\]/gi,"");
    s = s.replace(/\\[\\/?[a-z0-9*]+(?:=[^\\]]*)?\\]/gi,"");
    s = s.replace(/\\s+/g," ").trim();
    return s;
  }
  function getValue(ta){
    try{
      if(window.sceditor && typeof sceditor.instance === "function"){
        const inst = sceditor.instance(ta);
        if(inst && typeof inst.val === "function") return inst.val();
      }
    }catch(e){}
    return ta.value || "";
  }
  function bindEditor(ta, update){
    try{
      if(window.sceditor && typeof sceditor.instance === "function"){
        const inst = sceditor.instance(ta);
        if(inst && typeof inst.bind === "function"){
          inst.bind("valuechanged keyup nodechanged", update);
          return true;
        }
      }
    }catch(e){}
    return false;
  }
  function mount(ta){
    if(!ta || ta.dataset.ccountersMounted === "1") return;
    ta.dataset.ccountersMounted = "1";

    const wrap = document.createElement("div");
    wrap.className = "ccounters_form";
    const label = document.createElement("span");
    label.textContent = (i18n.editorLabel || "Chars:") + " ";
    const val = document.createElement("strong");
    wrap.appendChild(label);
    wrap.appendChild(val);

    ta.parentNode && ta.parentNode.insertBefore(wrap, ta.nextSibling);

    const update = () => { val.textContent = String(clean(getValue(ta)).length); };

    update();
    ta.addEventListener("input", update, {passive:true});
    ta.addEventListener("keyup", update, {passive:true});

    if(!bindEditor(ta, update)){
      let tries = 0;
      const timer = setInterval(() => {
        tries++;
        if(bindEditor(ta, update) || tries >= 20) clearInterval(timer);
      }, 250);
    }
  }
  function init(){
    const ta = document.querySelector("textarea[name=message], textarea#message");
    if(ta) mount(ta);
  }
  if(document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
</script>'."\n";

	$done = true;
}

function ccounters_maybe_inject_assets_posting()
{
	$fid = (int)($GLOBALS['fid'] ?? 0);
	if($fid > 0) ccounters_inject_assets($fid);
}

function ccounters_maybe_inject_assets_showthread()
{
	$fid = (int)($GLOBALS['fid'] ?? 0);
	if($fid > 0) ccounters_inject_assets($fid);
}

function ccounters_maybe_inject_assets_editpost()
{
	global $mybb;

	$fid = 0;
	$pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
	if($pid > 0) {
		$p = get_post($pid);
		if(!empty($p['fid'])) $fid = (int)$p['fid'];
	}
	ccounters_inject_assets($fid);
}

function ccounters_usercp_options_end()
{
	global $mybb, $lang, $ccounters_usercp_row;

	ccounters_load_lang();

	$checked = ((int)($mybb->user['ccounters_disable'] ?? 0) === 1) ? ' checked="checked"' : '';

	$ccounters_usercp_row = '
<tr>
	<td valign="top" width="1">
		<input type="checkbox" class="checkbox" name="ccounters_disable" id="ccounters_disable" value="1"'.$checked.' />
	</td>
	<td>
		<span class="smalltext"><label for="ccounters_disable">'.htmlspecialchars_uni($lang->ccounters_usercp_disable).'</label></span>
	</td>
</tr>';
}

function ccounters_usercp_do_options_end()
{
	global $db, $mybb;

	$val = (int)$mybb->get_input('ccounters_disable', MyBB::INPUT_INT);
	if(!$mybb->user['uid'] || !$db->field_exists('ccounters_disable', 'users')) return;

	$db->update_query('users', ['ccounters_disable' => $val], "uid='".(int)$mybb->user['uid']."'");
	$mybb->user['ccounters_disable'] = $val;
}
