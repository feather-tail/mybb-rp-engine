<?php
if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('showthread_end', 'guestquickreplyname_showthread_end');

function guestquickreplyname_info()
{
    return array(
        'name'          => 'Guest Quick Reply Username',
        'description'   => 'Adds a guest "Username" field to the Quick Reply form so guests can set the displayed name like on the full reply page.',
        'website'       => '',
        'author'        => 'Feathertail',
        'authorsite'    => '',
        'version'       => '1.0.0',
        'compatibility' => '18*'
    );
}

function guestquickreplyname_activate() {}
function guestquickreplyname_deactivate() {}

function guestquickreplyname_showthread_end()
{
    global $mybb, $quickreply, $lang;

    if(!empty($mybb->user['uid'])) {
        return;
    }

    if(empty($quickreply) || !is_string($quickreply)) {
        return;
    }

    if(
        stripos($quickreply, 'guestquickreply_username') !== false ||
        stripos($quickreply, 'name="username"') !== false ||
        stripos($quickreply, "name='username'") !== false
    ) {
        return;
    }

    $maxlength = isset($mybb->settings['maxnamelength']) ? (int)$mybb->settings['maxnamelength'] : 30;
    if($maxlength < 1) {
        $maxlength = 30;
    }

    $value = '';
    if(isset($mybb->input['username'])) {
        $value = htmlspecialchars_uni($mybb->get_input('username'));
    }

    $label = isset($lang->username) ? $lang->username : 'Username';
    $label = htmlspecialchars_uni($label);

    $insert =
        '<div class="guestquickreply_username">'.
            '<label for="guestquickreply_username"><strong>'.$label.'</strong></label><br />'.
            '<input type="text" class="textbox" name="username" id="guestquickreply_username" value="'.$value.'" maxlength="'.$maxlength.'" />'.
        '</div><br />';

    $quickreply = preg_replace(
        '#(<textarea[^>]*\bname=(\"|\')message\\2[^>]*>)#i',
        $insert.'$1',
        $quickreply,
        1
    );
}
