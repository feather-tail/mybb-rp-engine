<?php
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('pre_output_page', 'mentionprofilebutton_pre_output_page');
$plugins->add_hook('postbit', 'mentionprofilebutton_postbit');
$plugins->add_hook('postbit_prev', 'mentionprofilebutton_postbit');
$plugins->add_hook('postbit_pm', 'mentionprofilebutton_postbit');
$plugins->add_hook('postbit_announcement', 'mentionprofilebutton_postbit');

function mentionprofilebutton_info()
{
    return array(
        'name'          => 'Mention Profile Button',
        'description'   => 'Turns author-name clicks into mention insertion and adds a dedicated Profile button in postbit templates.',
        'website'       => '',
        'author'        => 'Codex',
        'authorsite'    => '',
        'version'       => '1.0.4',
        'compatibility' => '18*',
    );
}

function mentionprofilebutton_is_installed()
{
    global $db;
    $sid = $db->fetch_field(
        $db->simple_select('settings', 'sid', "name='mentionprofilebutton_insert_mode'", array('limit' => 1)),
        'sid'
    );
    return (bool)$sid;
}

function mentionprofilebutton_install()
{
    mentionprofilebutton_install_settings();
}

function mentionprofilebutton_uninstall()
{
    mentionprofilebutton_remove_settings();
}

function mentionprofilebutton_activate()
{
    mentionprofilebutton_install_settings();
    mentionprofilebutton_apply_template_edits();
}

function mentionprofilebutton_deactivate()
{
    mentionprofilebutton_revert_template_edits();
}

function mentionprofilebutton_install_settings()
{
    global $db;

    $gid = (int)$db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='mentionprofilebutton'", array('limit' => 1)),
        'gid'
    );

    if(!$gid)
    {
        $disporder = (int)$db->fetch_field(
            $db->simple_select('settinggroups', 'MAX(disporder) AS maxdisp'),
            'maxdisp'
        );
        $disporder = $disporder ? $disporder + 1 : 1;

        $group = array(
            'name'        => 'mentionprofilebutton',
            'title'       => 'Mention Profile Button',
            'description' => 'Settings for Mention Profile Button.',
            'disporder'   => $disporder,
            'isdefault'   => 0
        );

        $gid = (int)$db->insert_query('settinggroups', $group);
    }

    $sid = (int)$db->fetch_field(
        $db->simple_select('settings', 'sid', "name='mentionprofilebutton_insert_mode'", array('limit' => 1)),
        'sid'
    );

    if(!$sid)
    {
        $setting = array(
            'name'        => 'mentionprofilebutton_insert_mode',
            'title'       => 'Insert format',
            'description' => 'What to insert when clicking an author name.',
            'optionscode' => "select\nmention=@\"username\"#uid,\nbold=[b]username[/b],",
            'value'       => 'mention',
            'disporder'   => 1,
            'gid'         => $gid
        );

        $db->insert_query('settings', $setting);
    }
    else
    {
        $db->update_query('settings', array(
            'optionscode' => "select\nmention=@\"username\"#uid,\nbold=[b]username[/b],"
        ), "sid='{$sid}'");
    }

    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
    }
}

function mentionprofilebutton_remove_settings()
{
    global $db;

    $db->delete_query('settings', "name='mentionprofilebutton_insert_mode'");

    $gid = (int)$db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='mentionprofilebutton'", array('limit' => 1)),
        'gid'
    );

    if($gid)
    {
        $db->delete_query('settinggroups', "gid='{$gid}'");
    }

    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
    }
}

function mentionprofilebutton_apply_template_edits()
{
    if(!defined('MYBB_ADMIN_DIR'))
    {
        define('MYBB_ADMIN_DIR', MYBB_ROOT.'admin/');
    }

    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $templates = array('postbit', 'postbit_classic');

    foreach($templates as $template)
    {
        find_replace_templatesets(
            $template,
            '#\s*<!--mentionprofilebutton:profilelink-->.*?<!--/mentionprofilebutton:profilelink-->\s*#is',
            ''
        );

        find_replace_templatesets(
            $template,
            '#\{\$post\[(?:\'|")button_profile(?:\'|")\]\}#i',
            ''
        );

        find_replace_templatesets(
            $template,
            '#\{\$post\[(?:\'|")profilelink(?:\'|")\]\}#i',
            '<!--mentionprofilebutton:profilelink--><span class="mentionprofilebutton-link" data-mention-profile="1" data-uid="{$post[\'uid\']}" data-username="{$post[\'mentionprofilebutton_username_attr\']}">{$post[\'mentionprofilebutton_profilelink\']}</span><!--/mentionprofilebutton:profilelink-->'
        );

        find_replace_templatesets(
            $template,
            '#\{\$post\[(?:\'|")button_email(?:\'|")\]\}#i',
            '{$post[\'button_profile\']}{$post[\'button_email\']}'
        );
    }
}

function mentionprofilebutton_revert_template_edits()
{
    if(!defined('MYBB_ADMIN_DIR'))
    {
        define('MYBB_ADMIN_DIR', MYBB_ROOT.'admin/');
    }

    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $templates = array('postbit', 'postbit_classic');

    foreach($templates as $template)
    {
        find_replace_templatesets(
            $template,
            '#\s*<!--mentionprofilebutton:profilelink-->.*?<!--/mentionprofilebutton:profilelink-->\s*#is',
            '{$post[\'profilelink\']}'
        );

        find_replace_templatesets(
            $template,
            '#\{\$post\[(?:\'|")button_profile(?:\'|")\]\}#i',
            ''
        );
    }
}

function mentionprofilebutton_postbit(&$post)
{
    global $lang;

    $post['button_profile'] = '';

    $post['mentionprofilebutton_username_attr'] = '';
    if(isset($post['username']))
    {
        $post['mentionprofilebutton_username_attr'] = htmlspecialchars_uni($post['username']);
    }

    $post['mentionprofilebutton_profilelink'] = isset($post['profilelink']) ? $post['profilelink'] : '';

    if(!empty($post['uid']) && !empty($post['mentionprofilebutton_profilelink']))
    {
        $post['mentionprofilebutton_profilelink'] = preg_replace(
            '#<a\b([^>]*?)\bhref\s*=\s*(["\'])(.*?)\2([^>]*)>#i',
            '<a$1href="#"$4 onclick="return false;" data-mpb-orig-href=$2$3$2>',
            $post['mentionprofilebutton_profilelink'],
            1
        );
    }

    if(empty($post['uid']))
    {
        return;
    }

    $uid = (int)$post['uid'];

    if(function_exists('get_profile_link'))
    {
        $profile_link = get_profile_link($uid);
    }
    else
    {
        $profile_link = 'member.php?action=profile&amp;uid='.$uid;
    }

    $label = 'Profile';
    if(isset($lang->nav_profile) && $lang->nav_profile)
    {
        $label = $lang->nav_profile;
    }
    elseif(isset($lang->profile) && $lang->profile)
    {
        $label = $lang->profile;
    }

    $title = htmlspecialchars_uni($label);
    $label = htmlspecialchars_uni($label);

    $post['button_profile'] = '<a href="'.$profile_link.'" title="'.$title.'" class="postbit_button postbit_profile"><span>'.$label.'</span></a>';
}

function mentionprofilebutton_pre_output_page(&$page)
{
    global $mybb;

    $mode = 'mention';
    if(isset($mybb->settings['mentionprofilebutton_insert_mode']))
    {
        $mode = (string)$mybb->settings['mentionprofilebutton_insert_mode'];
    }
    if($mode !== 'bold')
    {
        $mode = 'mention';
    }

    $js = '<script>
(function() {
  if (window.MentionProfileButton && window.MentionProfileButton.__inited) return;
  window.MentionProfileButton = window.MentionProfileButton || {};
  window.MentionProfileButton.__inited = true;

  var insertMode = '.json_encode($mode).';

  function findTextarea() {
    var a = document.activeElement;
    if (a && a.tagName === "TEXTAREA" && (a.id === "message" || a.name === "message")) return a;

    var el = document.querySelector("#message");
    if (el) return el;

    el = document.querySelector("textarea[name=\\"message\\"]");
    if (el) return el;

    return null;
  }

  function getSceditor(textarea) {
    if (!textarea) return null;
    if (window.sceditor && typeof window.sceditor.instance === "function") {
      try { return window.sceditor.instance(textarea); } catch (e) { return null; }
    }
    return null;
  }

  function insertText(textarea, text) {
    var inst = getSceditor(textarea);
    if (inst && typeof inst.insertText === "function") {
      inst.insertText(text);
      if (typeof inst.focus === "function") inst.focus();
      return true;
    }

    if (!textarea) return false;
    textarea.focus();

    if (typeof textarea.selectionStart === "number" && typeof textarea.selectionEnd === "number") {
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var value = textarea.value || "";
      textarea.value = value.slice(0, start) + text + value.slice(end);
      var pos = start + text.length;
      if (typeof textarea.setSelectionRange === "function") textarea.setSelectionRange(pos, pos);
      return true;
    }

    textarea.value = (textarea.value || "") + text;
    return true;
  }

  function cleanUsername(u) {
    u = (u || "").replace(/\\s+/g, " ").trim();
    u = u.replace(/"/g, "");
    return u;
  }

  function buildInsert(username, uid) {
    if (insertMode === "bold") {
      return "[b]" + username + "[/b],  ";
    }
    if (uid && uid > 0) {
      return "@\\"" + username + "\\"#" + uid + ",  ";
    }
    return "[b]" + username + "[/b],  ";
  }

  document.addEventListener("click", function(ev) {
    var wrap = ev.target && ev.target.closest ? ev.target.closest(".mentionprofilebutton-link[data-mention-profile=\\"1\\"]") : null;
    if (!wrap) return;

    var uidRaw = wrap.getAttribute("data-uid") || "";
    var username = wrap.getAttribute("data-username") || "";

    var uid = parseInt(uidRaw, 10);
    if (isNaN(uid)) uid = 0;

    username = cleanUsername(username);
    if (!username) return;

    var textarea = findTextarea();
    if (!textarea) return;

    ev.preventDefault();
    if (typeof ev.stopImmediatePropagation === "function") ev.stopImmediatePropagation();
    if (typeof ev.stopPropagation === "function") ev.stopPropagation();

    insertText(textarea, buildInsert(username, uid));
  }, true);
})();
</script>';

    if(stripos($page, 'window.MentionProfileButton') !== false)
    {
        return;
    }

    if(stripos($page, '</body>') !== false)
    {
        $page = str_ireplace('</body>', $js.'</body>', $page);
        return;
    }

    $page .= $js;
}
