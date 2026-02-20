<?php

if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.');
}

function hideauthor_info()
{
    return array(
        'name' => 'Hide Author Panel Per Post',
        'description' => 'Adds a per-post option to hide the author profile panel on thread view.',
        'website' => '',
        'author' => 'Codex',
        'authorsite' => '',
        'version' => '1.2.2',
        'compatibility' => '18*'
    );
}

function hideauthor_install()
{
    global $db, $lang;

    hideauthor_load_language();

    if(!$db->field_exists('hideauthor', 'posts'))
    {
        $db->add_column('posts', 'hideauthor', "tinyint(1) NOT NULL default '0'");
    }

    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='hideauthor'"), 'gid');
    if($gid <= 0)
    {
        $query = $db->simple_select('settinggroups', 'COUNT(*) AS rows');
        $disporder = (int)$db->fetch_field($query, 'rows') + 1;

        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'hideauthor',
            'title' => $lang->hideauthor_setting_group_title,
            'description' => $lang->hideauthor_setting_group_description,
            'disporder' => $disporder,
            'isdefault' => 0
        ));
    }

    $sid = (int)$db->fetch_field($db->simple_select('settings', 'sid', "name='hideauthor_enabled'"), 'sid');
    if($sid <= 0)
    {
        $db->insert_query('settings', array(
            'name' => 'hideauthor_enabled',
            'title' => $lang->hideauthor_setting_enabled_title,
            'description' => $lang->hideauthor_setting_enabled_description,
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 1,
            'gid' => $gid
        ));
    }
    else
    {
        $db->update_query('settings', array('gid' => $gid), "sid='{$sid}'");
    }

    rebuild_settings();
}

function hideauthor_is_installed()
{
    global $db;

    return (bool)$db->field_exists('hideauthor', 'posts');
}

function hideauthor_uninstall()
{
    global $db;

    if($db->field_exists('hideauthor', 'posts'))
    {
        $db->drop_column('posts', 'hideauthor');
    }

    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='hideauthor'"), 'gid');
    if($gid > 0)
    {
        $db->delete_query('settings', "gid='{$gid}'");
        $db->delete_query('settinggroups', "gid='{$gid}'");
    }
    else
    {
        $db->delete_query('settings', "name='hideauthor_enabled'");
        $db->delete_query('settinggroups', "name='hideauthor'");
    }

    rebuild_settings();
}

function hideauthor_activate()
{
    hideauthor_apply_template_edits();
}

function hideauthor_deactivate()
{
    hideauthor_revert_template_edits();
}

function hideauthor_apply_template_edits()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $optBlock = "<!--hideauthor:option-->{\$hideauthor_option}<!--/hideauthor:option-->";
    $qrBlock  = "<!--hideauthor:quickreply-->{\$hideauthor_quickreply_option}<!--/hideauthor:quickreply-->";

    $rmOpt = '#\s*<!--hideauthor:option-->.*?<!--/hideauthor:option-->\s*#is';
    $rmQr  = '#\s*<!--hideauthor:quickreply-->.*?<!--/hideauthor:quickreply-->\s*#is';

    $rmLegacyOpt = '#\s*\{\$hideauthor_option\}\s*#i';
    $rmLegacyQr  = '#\s*\{\$hideauthor_quickreply_option\}\s*#i';

    $rmBadClsMarkers = '#<!--hideauthor:class-->.*?<!--/hideauthor:class-->#is';
    $rmLegacyCls = '#\s*\{\$post\[(?:\'|")hideauthor_class(?:\'|")\]\}\s*#i';

    foreach(array('newthread_postoptions', 'newreply_postoptions', 'editpost_postoptions') as $tpl)
    {
        find_replace_templatesets($tpl, $rmOpt, '', 1, false, -1);
        find_replace_templatesets($tpl, $rmLegacyOpt, '', 1, false, -1);
        find_replace_templatesets($tpl, '#</span>#i', "\n{$optBlock}</span>", 1, false, 1);
    }

    find_replace_templatesets('showthread_quickreply', $rmQr, '', 1, false, -1);
    find_replace_templatesets('showthread_quickreply', $rmLegacyQr, '', 1, false, -1);
    find_replace_templatesets('showthread_quickreply', '#\{\$closeoption\}#i', "{$qrBlock}{\$closeoption}", 1, false, 1);

    foreach(array('postbit', 'postbit_classic') as $tpl)
    {
        find_replace_templatesets($tpl, $rmBadClsMarkers, '', 1, false, -1);
        find_replace_templatesets($tpl, $rmLegacyCls, '', 1, false, -1);
        find_replace_templatesets($tpl, '#\{\$unapproved_shade\}#i', "{\$unapproved_shade}{\$post['hideauthor_class']}", 1, false, 1);
    }
}

function hideauthor_revert_template_edits()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $rmOpt = '#\s*<!--hideauthor:option-->.*?<!--/hideauthor:option-->\s*#is';
    $rmQr  = '#\s*<!--hideauthor:quickreply-->.*?<!--/hideauthor:quickreply-->\s*#is';

    $rmLegacyOpt = '#\s*\{\$hideauthor_option\}\s*#i';
    $rmLegacyQr  = '#\s*\{\$hideauthor_quickreply_option\}\s*#i';

    $rmBadClsMarkers = '#<!--hideauthor:class-->.*?<!--/hideauthor:class-->#is';
    $rmLegacyCls = '#\s*\{\$post\[(?:\'|")hideauthor_class(?:\'|")\]\}\s*#i';

    foreach(array('newthread_postoptions', 'newreply_postoptions', 'editpost_postoptions') as $tpl)
    {
        find_replace_templatesets($tpl, $rmOpt, '', 0, false, -1);
        find_replace_templatesets($tpl, $rmLegacyOpt, '', 0, false, -1);
    }

    find_replace_templatesets('showthread_quickreply', $rmQr, '', 0, false, -1);
    find_replace_templatesets('showthread_quickreply', $rmLegacyQr, '', 0, false, -1);

    foreach(array('postbit', 'postbit_classic') as $tpl)
    {
        find_replace_templatesets($tpl, $rmBadClsMarkers, '', 0, false, -1);
        find_replace_templatesets($tpl, $rmLegacyCls, '', 0, false, -1);
    }
}

function hideauthor_is_enabled()
{
    global $mybb;

    return isset($mybb->settings['hideauthor_enabled']) && (int)$mybb->settings['hideauthor_enabled'] === 1;
}

function hideauthor_load_language()
{
    global $lang;

    if(!isset($lang->hideauthor_option_label))
    {
        $lang->load('hideauthor');
    }
}

function hideauthor_build_option($checked, $name = 'hideauthor')
{
    global $lang;

    hideauthor_load_language();

    $checked_attribute = $checked ? ' checked="checked"' : '';

    return '<label><input type="checkbox" class="checkbox" name="'.$name.'" value="1"'.$checked_attribute.' /> '.htmlspecialchars_uni($lang->hideauthor_option_label).'</label><br />';
}

function hideauthor_get_input_value($default = 0)
{
    global $mybb;

    if(isset($mybb->input['hideauthor']))
    {
        return (int)$mybb->get_input('hideauthor', MyBB::INPUT_INT) === 1 ? 1 : 0;
    }

    return (int)$default === 1 ? 1 : 0;
}

function hideauthor_newthread_start()
{
    global $hideauthor_option;

    if(!hideauthor_is_enabled())
    {
        $hideauthor_option = '';
        return;
    }

    $hideauthor_option = hideauthor_build_option(hideauthor_get_input_value(0));
}

function hideauthor_newreply_start()
{
    global $hideauthor_option;

    if(!hideauthor_is_enabled())
    {
        $hideauthor_option = '';
        return;
    }

    $hideauthor_option = hideauthor_build_option(hideauthor_get_input_value(0));
}

function hideauthor_editpost_start()
{
    global $db, $hideauthor_option, $mybb;

    if(!hideauthor_is_enabled())
    {
        $hideauthor_option = '';
        return;
    }

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $saved_value = 0;

    if($pid > 0)
    {
        $saved_value = (int)$db->fetch_field($db->simple_select('posts', 'hideauthor', "pid='{$pid}'"), 'hideauthor') === 1 ? 1 : 0;
    }

    $hideauthor_option = hideauthor_build_option(hideauthor_get_input_value($saved_value));
}

function hideauthor_showthread_start()
{
    global $hideauthor_quickreply_option;

    if(!hideauthor_is_enabled())
    {
        $hideauthor_quickreply_option = '';
        return;
    }

    $hideauthor_quickreply_option = hideauthor_build_option(hideauthor_get_input_value(0));
}

function hideauthor_capture_value(&$datahandler)
{
    if(!hideauthor_is_enabled())
    {
        return;
    }

    $hideauthor = hideauthor_get_input_value(0);

    if(isset($datahandler->post_insert_data) && is_array($datahandler->post_insert_data))
    {
        $datahandler->post_insert_data['hideauthor'] = $hideauthor;
    }

    if(isset($datahandler->post_update_data) && is_array($datahandler->post_update_data))
    {
        $datahandler->post_update_data['hideauthor'] = $hideauthor;
    }
}

function hideauthor_postbit(&$post)
{
    if(!hideauthor_is_enabled())
    {
        $post['hideauthor_class'] = '';
        return;
    }

    $post['hideauthor_class'] = ((int)$post['hideauthor'] === 1) ? ' post--hideauthor' : '';
}

function hideauthor_pre_output_page(&$contents)
{
    global $lang;

    if(!hideauthor_is_enabled())
    {
        return;
    }

    if(strpos($contents, 'id="hideauthor-post-css"') === false)
    {
        $css = '<style id="hideauthor-post-css">'
            .'.post.post--hideauthor .post_author,'
            .'.post.post--hideauthor td.post_author{display:none !important;}'
            .'.post.post--hideauthor .post_content,'
            .'.post.post--hideauthor td.post_content{width:100% !important;max-width:none !important;margin-left:0 !important;}'
            .'.post.post--hideauthor .post_controls{margin-left:0 !important;float:none !important;clear:both !important;display:block !important;width:auto !important;}'
            .'.post.post--hideauthor .post_controls:after{content:"";display:block;clear:both;}'
            .'.post.post--hideauthor .post_signature,'
            .'.post.post--hideauthor td.post_signature,'
            .'.post.post--hideauthor .signature,'
            .'.post.post--hideauthor td.signature,'
            .'.post.post--hideauthor div[id^="signature_"],'
            .'.post.post--hideauthor hr.signature_sep,'
            .'.post.post--hideauthor .signature_sep{display:none !important;}'
            .'</style>';


        $contents = str_replace('</head>', $css.'</head>', $contents);
    }

    if(THIS_SCRIPT !== 'showthread.php')
    {
        return;
    }

    if(strpos($contents, 'id="hideauthor-quickedit-js"') !== false)
    {
        return;
    }

    hideauthor_load_language();
    $label = json_encode($lang->hideauthor_option_label);

    $script = "<script id=\"hideauthor-quickedit-js\">(function($){if(typeof $==='undefined'){return;}var hideauthorLabel={$label};var haState={};function pidFromUrlOrData(options){var url=(options&&typeof options.url==='string')?options.url:'';var m=url.match(/(?:\\?|&)pid=(\\d+)/);if(m){return m[1];}if(options&&typeof options.data==='string'){var m2=options.data.match(/(?:^|&)pid=(\\d+)/);if(m2){return m2[1];}}if(options&&$.isPlainObject(options.data)&&options.data.pid){return String(options.data.pid);}return null;}function ensureControls(pid){var textarea=$('#quickedit_'+pid);if(!textarea.length||$('#quickedit_'+pid+'_hideauthor_wrap').length){return;}var checked=$('#post_'+pid).hasClass('post--hideauthor')?1:0;haState[pid]=checked;var chkAttr=checked?' checked=\\\"checked\\\"':'';var html='<div id=\"quickedit_'+pid+'_hideauthor_wrap\" class=\"smalltext\" style=\"margin-top:6px;\"><input type=\"hidden\" id=\"quickedit_'+pid+'_hideauthor_val\" name=\"hideauthor\" value=\"'+checked+'\" /><label><input type=\"checkbox\" class=\"checkbox hideauthor-qe-cb\" id=\"quickedit_'+pid+'_hideauthor\" value=\"1\"'+chkAttr+' /> '+hideauthorLabel+'</label></div>';$(html).insertAfter(textarea);}$(document).on('click','.quick_edit_button',function(){var pid=(this.id||'').replace(/[^\\d]/g,'');if(!pid){return;}window.setTimeout(function(){ensureControls(pid);},0);});$(document).on('change','.hideauthor-qe-cb',function(){var m=(this.id||'').match(/^quickedit_(\\d+)_hideauthor$/);if(!m){return;}var pid=m[1];var v=$(this).is(':checked')?1:0;haState[pid]=v;$('#quickedit_'+pid+'_hideauthor_val').val(v);});$(document).ajaxSend(function(event,xhr,options){if(!options||typeof options.url!=='string'||options.url.indexOf('xmlhttp.php?action=edit_post&do=update_post')===-1){return;}var pid=pidFromUrlOrData(options);if(!pid){return;}var v;var valEl=$('#quickedit_'+pid+'_hideauthor_val');if(valEl.length){v=parseInt(valEl.val(),10)||0;}else if(Object.prototype.hasOwnProperty.call(haState,pid)){v=haState[pid];}else{v=$('#quickedit_'+pid+'_hideauthor').is(':checked')?1:0;}if(typeof options.data==='string'){if(/(?:^|&)hideauthor=/.test(options.data)){options.data=options.data.replace(/(^|&)hideauthor=[^&]*/,'$1hideauthor='+v);}else{options.data+=(options.data.length?'&':'')+'hideauthor='+v;}}else if($.isPlainObject(options.data)){options.data.hideauthor=v;}else{options.data='hideauthor='+v;}});$(document).ajaxSuccess(function(event,xhr,options,response){if(!options||typeof options.url!=='string'||options.url.indexOf('xmlhttp.php?action=edit_post&do=update_post')===-1){return;}var pid=pidFromUrlOrData(options);if(!pid){return;}var json=response;if(typeof json==='string'){try{json=JSON.parse(json);}catch(e){json=null;}}if(json&&((json.errors&&json.errors.length)||json.moderation_post||json.moderation_thread)){return;}var v;var valEl=$('#quickedit_'+pid+'_hideauthor_val');if(valEl.length){v=parseInt(valEl.val(),10)||0;}else if(Object.prototype.hasOwnProperty.call(haState,pid)){v=haState[pid];}else{v=$('#quickedit_'+pid+'_hideauthor').is(':checked')?1:0;}$('#post_'+pid).toggleClass('post--hideauthor',!!v);});})(jQuery);</script>";

    $contents = str_replace('</body>', $script.'</body>', $contents);
}

$plugins->add_hook('newthread_start', 'hideauthor_newthread_start');
$plugins->add_hook('newreply_start', 'hideauthor_newreply_start');
$plugins->add_hook('editpost_start', 'hideauthor_editpost_start');
$plugins->add_hook('showthread_start', 'hideauthor_showthread_start');
$plugins->add_hook('datahandler_post_insert_post', 'hideauthor_capture_value');
$plugins->add_hook('datahandler_post_insert_thread_post', 'hideauthor_capture_value');
$plugins->add_hook('datahandler_post_update', 'hideauthor_capture_value');
$plugins->add_hook('postbit', 'hideauthor_postbit');
$plugins->add_hook('postbit_prev', 'hideauthor_postbit');
$plugins->add_hook('postbit_announcement', 'hideauthor_postbit');
$plugins->add_hook('pre_output_page', 'hideauthor_pre_output_page');
