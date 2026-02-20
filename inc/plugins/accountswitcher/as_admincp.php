<?php
/**
 * Enhanced Account Switcher for MyBB 1.8
 * Copyright (c) 2012-2025 doylecc
 * http://doylecc.altervista.org
 *
 * based on the Plugin:
 * Account Switcher 1.0 by Harest
 * Copyright (c) 2011 Harest
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */


// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />
        Please make sure IN_MYBB is defined.');
}

// Hooks for template edits when adding/importing a new theme
$plugins->add_hook('admin_style_themes_add_commit', 'accountswitcher_new_themes');
$plugins->add_hook('admin_style_themes_import_commit', 'accountswitcher_new_themes');
/**
 * Applies the template edits to added/imported themes.
 *
 */
function accountswitcher_new_themes()
{
    global $mybb, $db, $theme_id;

    if (isset($mybb->settings['aj_tpledit']) && $mybb->settings['aj_tpledit'] == 1) {
        // Get the templateset sid of the theme
        $query = $db->simple_select("themes", "properties", "tid='".(int)$theme_id."'");
        $tplset = $db->fetch_array($query);
        $properties = unserialize($tplset['properties']);
        $theme_sid = (int)$properties['templateset'];

        // Edit only the template set of the new theme
        accountswitcher_revert_template_edits($theme_sid);
        accountswitcher_apply_template_edits($theme_sid);
    }
}


/**
 * Applies the template edits.
 *
 */
function accountswitcher_apply_template_edits($sid = false)
{
    require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
    // Apply the template edits
    find_replace_templatesets(
        'header_welcomeblock_member',
        '#'.preg_quote('{$lang->welcome_pms_usage}').'#i',
        '{$lang->welcome_pms_usage}<!-- AccountSwitcher -->{$as_header}<!-- /AccountSwitcher -->',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header_welcomeblock_member',
        '#'.preg_quote('{$pmslink}').'#i',
        '{$pmslink}<!-- AccountSwitcher -->{$as_header}<!-- /AccountSwitcher -->',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newreply',
        '#'.preg_quote('<input type="submit" class="button" name="submit"').'#s',
        '{$as_post}&nbsp;<input type="submit" class="button" name="submit"',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newthread',
        '#'.preg_quote('<input type="submit" class="button" name="submit"').'#s',
        '{$as_post}&nbsp;<input type="submit" class="button" name="submit"',
        '',
        $sid,
        -1
    );
    find_replace_templatesets(
        'showthread_quickreply',
        '#'.preg_quote('<input type="submit" class="button" value="{$lang->post_reply}').'#s',
        '{$as_post}&nbsp;<input type="submit" class="button" value="{$lang->post_reply}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newreply',
        '#'.preg_quote('{$lang->reply_to}</strong>').'#s',
        '{$lang->reply_to}</strong><a name="switch" id="switch"></a>',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newthread',
        '#'.preg_quote('{$lang->post_new_thread}</strong>').'#s',
        '{$lang->post_new_thread}</strong><a name="switch" id="switch"></a>',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'showthread',
        '#'.preg_quote('{$quickreply}').'#s',
        '<a name="switch" id="switch"></a>{$quickreply}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('{$pm_notice}').'#i',
        '{$pm_notice}{$pm_switch_notice}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('{$menu_memberlist}').'#i',
        '{$menu_memberlist}{$menu_accountlist}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('<div id="container">').'#i',
        '{$as_sidebar}<div id="container">',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit',
        '#'.preg_quote('{$post[\'onlinestatus\']}').'#i',
        '{$post[\'onlinestatus\']}{$post[\'authorchange\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit_classic',
        '#'.preg_quote('{$post[\'onlinestatus\']}').'#i',
        '{$post[\'onlinestatus\']}{$post[\'authorchange\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit',
        '#'.preg_quote('{$post[\'user_details\']}').'#i',
        '{$post[\'user_details\']}{$post[\'attached_accounts\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit_classic',
        '#'.preg_quote('{$post[\'user_details\']}').'#i',
        '{$post[\'user_details\']}{$post[\'attached_accounts\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'member_profile',
        '#'.preg_quote('{$profilefields}').'#i',
        '{$profilefields}{$profile_attached}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'memberlist_user',
        '#'.preg_quote('{$user[\'profilelink\']}').'#i',
        '{$user[\'profilelink\']}{$user[\'attached_accounts\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'showteam_usergroup_user',
        '#'.preg_quote('{$user[\'username\']}</strong></a>').'#i',
        '{$user[\'username\']}</strong></a>{$user[\'attached_accounts\']}',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'headerinclude',
        '#'.preg_quote('var modal_zindex = 9999').'#i',
        'var modal_zindex = 9995',
        1,
        $sid,
        -1
    );
}

/**
 * Revert the template edits.
 *
 */
function accountswitcher_revert_template_edits($sid = false)
{
    require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
    // Undo template edits
    find_replace_templatesets(
        'header_welcomeblock_member',
        '#'.preg_quote('<!-- AccountSwitcher -->{$as_header}<!-- /AccountSwitcher -->').'#is',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newreply',
        '#'.preg_quote('{$as_post}&nbsp;').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newthread',
        '#'.preg_quote('{$as_post}&nbsp;').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'showthread_quickreply',
        '#'.preg_quote('{$as_post}&nbsp;').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newreply',
        '#'.preg_quote('<a name="switch" id="switch"></a>').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'newthread',
        '#'.preg_quote('<a name="switch" id="switch"></a>').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'showthread',
        '#'.preg_quote('<a name="switch" id="switch"></a>').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('{$pm_switch_notice}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('{$menu_accountlist}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'header',
        '#'.preg_quote('{$as_sidebar}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit',
        '#'.preg_quote('{$post[\'authorchange\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit_classic',
        '#'.preg_quote('{$post[\'authorchange\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit',
        '#'.preg_quote('{$post[\'attached_accounts\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'postbit_classic',
        '#'.preg_quote('{$post[\'attached_accounts\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'member_profile',
        '#'.preg_quote('{$profile_attached}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'memberlist_user',
        '#'.preg_quote('{$user[\'attached_accounts\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'showteam_usergroup_user',
        '#'.preg_quote('{$user[\'attached_accounts\']}').'#s',
        '',
        1,
        $sid,
        -1
    );
    find_replace_templatesets(
        'headerinclude',
        '#'.preg_quote('var modal_zindex = 9995').'#i',
        'var modal_zindex = 9999',
        '',
        1,
        $sid,
        -1
    );
}

// Add the hooks for editing usergroups and users
$plugins->add_hook('admin_user_groups_edit', 'accountswitcher_admingroups_edit');
$plugins->add_hook('admin_user_groups_edit_commit', 'accountswitcher_admingroups_commit');
$plugins->add_hook('admin_user_users_add_commit', 'accountswitcher_pm');
$plugins->add_hook('admin_user_users_edit_commit', 'accountswitcher_pm');
$plugins->add_hook('admin_user_users_edit_commit', 'accountswitcher_useredit_away');
$plugins->add_hook('admin_formcontainer_output_row', 'accountswitcher_user_editform');

// ##### Admin CP functions #####
/**
 * Adds a hook for the form in ACP.
 *
 */
function accountswitcher_admingroups_edit()
{
    global $plugins;

    // Add new hook
    $plugins->add_hook('admin_formcontainer_end', 'accountswitcher_admingroups_editform');
}

/**
 * Adds a setting in group options in ACP.
 *
 */
function accountswitcher_admingroups_editform()
{
    global $mybb, $lang, $form, $form_container;

    $lang->load('accountswitcher');

    // Create the input fields
    if ($form_container->_title == $lang->misc) {
        $as_group_can = array(
            $form->generate_check_box(
                'as_canswitch',
                1,
                $lang->as_admin_canswitch,
                array(
                    'checked' => $mybb->input['as_canswitch']
                )
            )
        );
        $as_group_limit = '<div class="group_settings_bit">'.$lang->as_admin_limit.'<br />'.
            $form->generate_text_box('as_limit', $mybb->input['as_limit'], array('class' => 'field50')).'</div>';
        $form_container->output_row(
            $lang->as_name,
            '',
            '<div class="group_settings_bit">'.implode('</div><div class="group_settings_bit">', $as_group_can)
            .'</div>'.$as_group_limit
        );
    }
}

/**
 * Adds a setting in user edit options in ACP.
 *
 */
function accountswitcher_user_editform($profile)
{
    global $mybb, $lang, $form, $eas, $user, $db, $cache, $templates;

    $lang->load('accountswitcher');

    if (!isset($lang->return_date)) {
        $lang->load('user_users');
    }

    // Get the number of users attached to this account
    $count = 0;
    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
    if (isset($user['uid'])) {
        $count = $eas->get_attached($user['uid']);
    }

    if ($profile['title'] == $lang->return_date
        && $lang->return_date
        && $mybb->settings['aj_away'] == 1
        && $count > 0
    ) {
        $profile['content'] .= '
                    </div>
                </td>
            </tr>
                <tr class="last">
                    <td class="first"><label>'.$lang->as_admin_profile_away.'</label>';
        $profile['content'] .= '
                        <div class="form_row"><div class="user_settings_bit">'.
                        $form->generate_check_box(
                            'awayall',
                            'enable',
                            $lang->as_admin_profile_away,
                            array('checked' => 1)
                        )
                        .'</div>';
    }
    return $profile;
}

/**
 * Sets the group options values in ACP.
 *
 */
function accountswitcher_useredit_away()
{
    global $mybb, $db, $eas, $returndate, $user, $templates, $cache;

    if (!isset($mybb->input['awayall'])) {
        return;
    }

    if ($mybb->settings['aj_away'] == 1 && $mybb->settings['allowaway'] != 0) {
        // Get the user permissions
        $user_perms = user_permissions($user['uid']);
        // Get the number of users attached to this account
        $count = 0;
        require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
        $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

        // If there are users attached and the current user can use the Enhanced Account Switcher...
        if ($user_perms['as_canswitch'] == 1) {
            $accounts = $eas->accountswitcher_cache;
            if (is_array($accounts)) {
                foreach ($accounts as $key => $account) {
                    $userUid = (int)$account['uid'];
                    if ($account['as_uid'] == $user['uid']) {
                        ++$count;
                        if ($count > 0) {
                            $awaydate = 0;
                            $return = '';
                            if ($mybb->get_input('away', MyBB::INPUT_INT) == 1) {
                                $awaydate = TIME_NOW;
                                $return = $db->escape_string($returndate);
                            }
                            $updated_record = array(
                                "away" => $mybb->get_input('away', MyBB::INPUT_INT),
                                "awaydate" => $awaydate,
                                "returndate" => $return,
                                "awayreason" => $db->escape_string($mybb->get_input('awayreason'))
                            );
                            $db->update_query("users", $updated_record, "uid='".$userUid."'");
                        }
                    }
                }
            }
        }
    }
}

/**
 * Sets the group options values in ACP.
 *
 */
function accountswitcher_admingroups_commit()
{
    global $mybb, $updated_group;

    $updated_group['as_canswitch'] = $mybb->get_input('as_canswitch', MyBB::INPUT_INT);
    $updated_group['as_limit'] = $mybb->get_input('as_limit', MyBB::INPUT_INT);
}

// Hook for the remove attached users function
$plugins->add_hook('admin_user_users_delete_commit_end', 'accountswitcher_del_user');
/**
 * Removes the attached user entries when the master user is deleted.
 *
 */
function accountswitcher_del_user()
{
    global $db, $user, $eas, $templates, $cache, $mybb;

    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

    $updated_as_uid = array(
        "as_uid" => 0,
    );
    $db->update_query("users", $updated_as_uid, "as_uid='".(int)$user['uid']."'");

    $updated_as_shareuid = array(
        "as_shareuid" => 0,
    );
    $db->update_query("users", $updated_as_shareuid, "as_shareuid='".(int)$user['uid']."'");

    $eas->update_accountswitcher_cache();
    $eas->update_userfields_cache();
}

// Hook for the action handler
$plugins->add_hook('admin_tools_action_handler', 'accountswitcher_admin_tools_action_handler');
/**
 * Set action handler for attached accounts cleanup
 *
 */
function accountswitcher_admin_tools_action_handler(&$actions)
{
    $actions['accountswitcher'] = array('active' => 'accountswitcher', 'file' => 'accountswitcher');
}

// Hook for the "remove attached users" function (set priority to 50 to get access to the eas object)
$plugins->add_hook('admin_load', 'accountswitcher_cleanup', 50);
/**
 * Removes the attached user entries if the master user doesn't exist
 * Or the user group has no permission to use the account switcher.
 *
 */
function accountswitcher_cleanup()
{
    global $mybb, $db, $lang, $page, $run_module, $action_file, $eas, $templates, $cache;

    if ($page->active_action != 'accountswitcher') {
        return false;
    }

    if ($run_module == 'tools' && $action_file == 'accountswitcher') {
        if ($mybb->get_input('action') == 'cleanup') {
            if (!verify_post_check($mybb->get_input('my_post_key'))) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-plugins");
            } else {
                require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
                $eas->update_accountswitcher_cache();

                $accounts = $eas->accountswitcher_cache;
                $masters = $attached_na = $master_na = array();

                if (is_array($accounts)) {
                    foreach ($accounts as $key => $account) {
                        // Get master accounts
                        $masters[] = (int)$account['as_uid'];
                        // Get attached accounts without EAS permissions
                        $atPermission = user_permissions($account['uid']);
                        if ($atPermission['as_canswitch'] != 1) {
                            $attached_na[] = (int)$account['uid'];
                        }
                    }
                    // Remove duplicates
                    $masters = array_unique($masters);
                    $masters = array_values($masters);

                    foreach ($masters as $master_check) {
                        if (!user_exists($master_check) && $master_check != 0) {
                            $master_na[] = (int)$master_check;
                        } else //Check if master user group is allowed to use the account switcher
                        {
                            $mPermission = user_permissions($master_check);
                            if ($mPermission['as_canswitch'] != 1) {
                                $master_na[] = (int)$master_check;
                            }
                        }
                    }
                    // Remove attached from non existing master accounts
                    $updated_record_m = array(
                        "as_uid" => 0
                    );
                    if (is_array($master_na) && !empty($master_na)) {
                        $m_na = implode($master_na, ",");
                        $db->update_query("users", $updated_record_m, "as_uid IN($m_na)");
                    }
                    // Remove acccounts with no permission to use the acount switcher
                    $updated_record_at = array(
                        "as_uid" => 0,
                        "as_share" => 0,
                        "as_shareuid" => 0
                    );
                    if (is_array($attached_na) && !empty($attached_na)) {
                        $at_na = implode($attached_na, ",");
                        $db->update_query("users", $updated_record_at, "uid IN($at_na)");
                    }
                }
                // Update the cache
                $eas->update_accountswitcher_cache();
                $eas->update_userfields_cache();
            }
            if ($mybb->get_input('manage') == 1) {
                admin_redirect("index.php?module=user-accountswitcher");
            } else {
                admin_redirect("index.php?module=config-plugins");
            }
        } elseif ($mybb->get_input('action') == 'upgrade') {
            if (!verify_post_check($mybb->get_input('my_post_key'))) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-plugins");
            } else {
                require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_upgrade.php';
                accountswitcher_upgrade();
            }
            admin_redirect("index.php?module=config-plugins");
        }
        exit;
    }
}

// Hook to change settings for peeker
$plugins->add_hook('admin_config_settings_change', 'accountswitcher_settings_change');
/**
 * Set peeker in ACP
 *
 */
function accountswitcher_settings_change()
{
    global $db, $mybb, $accountswitcher_settings_peeker;

    $result = $db->simple_select("settinggroups", "gid", "name='Enhanced Account Switcher'", array("limit" => 1));
    $group = $db->fetch_array($result);
    if (isset($group['gid'])) {
        $accountswitcher_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
    }
}

// Hook for the peeker
$plugins->add_hook('admin_settings_print_peekers', 'accountswitcher_settings_peek');
/**
 * Add peeker in ACP
 *
 */
function accountswitcher_settings_peek(&$peekers)
{
    global $mybb, $accountswitcher_settings_peeker;

    if ($accountswitcher_settings_peeker) {
        // Peeker for author moderation settings
        $peekers[] = 'new Peeker($(".setting_aj_admin_changeauthor"), $("#row_setting_aj_admin_changegroup"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_aj_admin_changeauthor"), $("#row_setting_aj_authorpm"),/1/,true)';
        // Peeker for shared accounts style settings
        $peekers[] = 'new Peeker($(".setting_aj_shareuser"), $("#row_setting_aj_sharestyle"),/1/,true)';
        // Peeker for profile field on accountlist page settings
        $peekers[] = 'new Peeker($(".setting_aj_profilefield"), $("#row_setting_aj_profilefield_id"),/1/,true)';
    }
}

// Hook for menu entry for accounts management
$plugins->add_hook('admin_user_menu', 'accountswitcher_admin_user_menu');
/**
 * ACP menu entry for attached accounts management
 *
 */
function accountswitcher_admin_user_menu(&$sub_menu)
{
    global $lang;

    if (!isset($lang->as_name)) {
        $lang->load('accountswitcher');
    }

    $sub_menu[] = array(
        'id' => 'accountswitcher',
        'title' => $lang->as_name,
        'link' => 'index.php?module=user-accountswitcher'
    );
}


// Hook for action handler for accounts management
$plugins->add_hook('admin_user_action_handler', 'accountswitcher_admin_user_action_handler');
/**
 * Set action handler for attached accounts management
 *
 */
function accountswitcher_admin_user_action_handler(&$actions)
{
    $actions['accountswitcher'] = array('active' => 'accountswitcher', 'file' => 'accountswitcher');
}


// Hook for admin permissions
$plugins->add_hook('admin_user_permissions', 'accountswitcher_admin_user_permissions');
/**
 * Set admin permissions for attached accounts management
 *
 */
function accountswitcher_admin_user_permissions(&$admin_permissions)
{
    global $lang;
    if (!isset($lang->as_can_manage_accountswitcher)) {
        $lang->load('accountswitcher');
    }
    $admin_permissions['accountswitcher'] = $lang->as_can_manage_accountswitcher;
}

// Hook for managing account attachments
$plugins->add_hook('admin_load', 'accountswitcher_admin');
/**
 * Attached accounts management
 *
 */
function accountswitcher_admin()
{
    global $db, $lang, $mybb, $page, $cache, $run_module, $action_file, $eas, $templates, $errors;

    if (!isset($lang->as_manage)) {
        $lang->load('accountswitcher');
    }

    if ($page->active_action != 'accountswitcher') {
        return false;
    }

    $masters = $shared_uids = $attached_users = array();

    if ($run_module == 'user' && $action_file == 'accountswitcher') {
        $page->add_breadcrumb_item($lang->as_name, 'index.php?module=user-accountswitcher');

        // Set value for older MyBB version
        if ($mybb->version_code < 1809) {
            $mybb->settings['allowremoteavatars'] = 1;
        }

        // Show tabs
        if ($mybb->get_input('action') == "" || !isset($mybb->input['action'])) {
            $page->add_breadcrumb_item($lang->as_manage);
            $page->output_header($lang->as_manage.' - '.$lang->as_manage);

            $sub_tabs['accountswitcher'] = array(
                'title'             => $lang->as_manage,
                'link'          => 'index.php?module=user-accountswitcher',
                'description'   => $lang->as_manage_description1
            );
            $sub_tabs['as_shared'] = array(
                'title'=>$lang->as_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_shared',
                'description'=>$lang->as_shared_description1
            );
            $sub_tabs['as_add_master'] = array(
                'title'=>$lang->as_add_master,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_master',
                'description'=>$lang->as_add_master_description1
            );
            $sub_tabs['as_add_shared'] = array(
                'title'=>$lang->as_add_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_shared',
                'description'=>$lang->as_add_shared_description1
            );
            $sub_tabs['as_clean'] = array(
                'title'=>$lang->as_name_cleanup,
                'link'=>'index.php?module=tools-accountswitcher&amp;action=cleanup&amp;manage=1&amp;my_post_key='
                    .$mybb->post_code.'',
                'description'=>$lang->as_name_cleanup
            );
            $page->output_nav_tabs($sub_tabs, 'accountswitcher');
            // New Form
            $form = new Form("index.php?module=user-accountswitcher", "post");

            $form_container = new FormContainer($lang->as_manage);
            $form_container->output_row_header('#');
            $form_container->output_row_header($lang->as_master_accounts);
            $form_container->output_row_header($lang->as_attached_accounts);
            $form_container->output_row_header(
                '<div style="text-align: center;">'.$lang->as_attached_options.':</div>'
            );

            $count = 0;
            $i = 1;

            // Incoming results per page?
            $mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
            if ($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 50) {
                $per_page = $mybb->input['perpage'];
            } else {
                $per_page = $mybb->input['perpage'] = 5;
            }

            // Page
            $pageview = $mybb->get_input('page', MyBB::INPUT_INT);
            if ($pageview && $pageview > 0) {
                $start = ($pageview - 1) * $per_page;
            } else {
                $start = 0;
                $pageview = 1;
            }

            // Get the accounts
            require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
            $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
            $accounts = $eas->accountswitcher_cache;

            if (is_array($accounts)) {
                // Find all master accounts
                foreach ($accounts as $key => $account) {
                    if ($account['as_uid'] == 0) {
                        continue;
                    }
                    $masters[] = $account['as_uid'];
                }

                $masters = array_unique($masters);
                $masters = array_values($masters);
                // Count all master accounts
                $num_masters = count($masters);

                // Show only number of master acounts per page
                $masters = array_slice($masters, $start, $per_page);
                if (is_array($masters)) {
                    foreach ($masters as $master_acc) {
                        $master = get_user($master_acc);
                        $attached_userlist = '<ul>';
                        if (!empty($master['uid'])) {
                            $form_container->output_cell($i);
                            // Display master account
                            $attachedPostUser = htmlspecialchars_uni($master['username']);
                            if (!$master['avatar']
                                || (my_strpos($master['avatar'], '://') !== false
                                    && !$mybb->settings['allowremoteavatars']
                                )
                            ) {
                                if (my_validate_url($mybb->settings['useravatar'])) {
                                    $master['avatar'] = str_replace(
                                        '{theme}',
                                        'images',
                                        $mybb->settings['useravatar']
                                    );
                                } else {
                                    $master['avatar'] = '../'.str_replace(
                                        '{theme}',
                                        'images',
                                        $mybb->settings['useravatar']
                                    );
                                }
                            } elseif (!empty($master['avatar'])) {
                                $master['avatar'] = str_replace(
                                    './',
                                    '../',
                                    $master['avatar']
                                );
                            }
                            // If no default avatar is set and user has no avatar
                            if ($master['avatar'] == '../') {
                                $master['avatar'] = '../images/eas/default_avatar.png';
                            }
                            $masterAvatar = '<img src="'.
                                htmlspecialchars_uni($master['avatar'])
                                .'" alt="" width="80" height="80" />';
                            $form_container->output_cell(
                                '<div style="float:left; margin-right: 10px;">'.
                                $masterAvatar
                                .'</div>&nbsp;&nbsp;'
                                .'<div style="font-weight: bold; font-size: 1.4em; margin: 15px;" '
                                .'title="Master Account"><a href="'
                                .'index.php?module=user-users&amp;action=edit&amp;uid='.(int)$master['uid'].'">'
                                .format_name($attachedPostUser, (int)$master['usergroup'], (int)$master['displaygroup'])
                                .'</a></div>'
                            );
                        }

                        // Sort accounts by first, secondary, shared accounts and by uid or username
                        $accounts = $eas->sort_attached();

                        // Get all attached accounts
                        foreach ($accounts as $key => $account) {
                            if ($account['as_uid'] == $master_acc) {
                                ++$count;
                                if ($count > 0) {
                                    if ($account['as_share'] != 0) {
                                        continue;
                                    }
                                    // Display attached account
                                    $attachedbit = format_name(
                                        htmlspecialchars_uni($account['username']),
                                        (int)$account['usergroup'],
                                        (int)$account['displaygroup']
                                    );
                                    if (!$account['avatar']
                                        || (my_strpos($account['avatar'], '://') !== false
                                            && !$mybb->settings['allowremoteavatars']
                                        )
                                    ) {
                                        if (my_validate_url($mybb->settings['useravatar'])) {
                                            $account['avatar'] = str_replace(
                                                '{theme}',
                                                'images',
                                                $mybb->settings['useravatar']
                                            );
                                        } else {
                                            $account['avatar'] = '../'.str_replace(
                                                '{theme}',
                                                'images',
                                                $mybb->settings['useravatar']
                                            );
                                        }
                                    } else {
                                        $account['avatar'] = str_replace(
                                            './',
                                            '../',
                                            $account['avatar']
                                        );
                                    }
                                    // If no default avatar is set and user has no avatar
                                    if ($account['avatar'] == '../') {
                                        $account['avatar'] = '../images/eas/default_avatar.png';
                                    }
                                    $attachedAvatar = '<img src="'.htmlspecialchars_uni($account['avatar'])
                                        .'" alt="" width="37" height="37" />';
                                    $attached_userlist .= '
                                        <li style="list-style: none;"><div style="float:left; margin-right: 10px;">'
                                        .$attachedAvatar.'</div>&nbsp;&nbsp;<div style="font-weight: bold; font-size:'
                                        .' 1.0em; margin: 10px;"><a href="index.php?module=user-users&amp;action=edit'
                                        .'&amp;uid='.(int)$account['uid'].'">'
                                        .format_name(
                                            $attachedbit,
                                            (int)$account['usergroup'],
                                            (int)$account['displaygroup']
                                        )
                                        .'</a></div></li>';
                                }
                            }
                        }
                        if (!empty($master['uid'])) {
                            $attached_userlist .= '
                                </ul>';
                            $form_container->output_cell($attached_userlist);
                            $popup = new PopupMenu("eas_".(int)$master['uid']."", $lang->as_attached_options);
                            $popup->add_item(
                                $lang->as_attached_options_edit,
                                "index.php?module=user-accountswitcher&amp;action=edit&amp;uid="
                                .(int)$master['uid'].""
                            );
                            $popup->add_item(
                                $lang->as_attached_options_delete,
                                "index.php?module=user-accountswitcher&amp;action=delete&amp;uid="
                                .(int)$master['uid']."&amp;my_post_key={$mybb->post_code}"
                            );
                            $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                            $form_container->construct_row();
                            ++$i;
                        }
                    }
                }
            }
            $form_container->end();
            $form->end();
            // Multipage
            $search_url = htmlspecialchars_uni(
                "index.php?module=user-accountswitcher&perpage={$mybb->input['perpage']}"
            );
            $multipage = multipage($num_masters, $per_page, $pageview, $search_url);
            echo $multipage;
            $page->output_footer();
            exit;
        }

        // Show shared accounts
        if ($mybb->get_input('action') == 'as_shared') {
            $page->add_breadcrumb_item($lang->as_shared);
            $page->output_header($lang->as_name.' - '.$lang->as_shared);

            $sub_tabs['accountswitcher'] = array(
                'title'             => $lang->as_manage,
                'link'          => 'index.php?module=user-accountswitcher',
                'description'   => $lang->as_manage_description1
            );
            $sub_tabs['as_shared'] = array(
                'title'=>$lang->as_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_shared',
                'description'=>$lang->as_shared_description1
            );
            $sub_tabs['as_add_master'] = array(
                'title'=>$lang->as_add_master,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_master',
                'description'=>$lang->as_add_master_description1
            );
            $sub_tabs['as_add_shared'] = array(
                'title'=>$lang->as_add_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_shared',
                'description'=>$lang->as_add_shared_description1
            );
            $page->output_nav_tabs($sub_tabs, 'as_shared');

            $form = new Form("index.php?module=user-accountswitcher&amp;action=as_share", "post");

            $form_container = new FormContainer($lang->as_shared);
            $form_container->output_row_header('#');
            $form_container->output_row_header($lang->as_shared);
            $form_container->output_row_header(
                '<div style="text-align: center;">'.$lang->as_attached_options.':</div>'
            );

            // Incoming results per page?
            $mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
            if ($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 50) {
                $per_page = $mybb->input['perpage'];
            } else {
                $per_page = $mybb->input['perpage'] = 10;
            }

            // Page
            $pageview = $mybb->get_input('page', MyBB::INPUT_INT);
            if ($pageview && $pageview > 0) {
                $start = ($pageview - 1) * $per_page;
            } else {
                $start = 0;
                $pageview = 1;
            }

            // Get the accounts
            require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
            $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
            $accounts = $eas->accountswitcher_cache;
            $num_shared = 0;
            $i = 1;

            if (is_array($accounts)) {
                // Count all shared accounts
                foreach ($accounts as $key => $account) {
                    if ($account['as_share'] != 0) {
                        $shared_uids[] = (int)$account['uid'];
                        ++$num_shared;
                    }
                }
                $shared_uids = array_unique($shared_uids);
                $shared_uids = array_values($shared_uids);
                // Show only number of shared accounts per page
                $shared_accounts = array_slice($shared_uids, $start, $per_page);

                // Display all shared accounts
                foreach ($shared_accounts as $key => $account_sh) {
                    $account = get_user($account_sh);
                    if ($account['as_share'] != 0) {
                        $form_container->output_cell($i);
                        $sharedUser = format_name(
                            htmlspecialchars_uni($account['username']),
                            (int)$account['usergroup'],
                            (int)$account['displaygroup']
                        );
                        if (!$account['avatar']
                            || (my_strpos($account['avatar'], '://') !== false
                                && !$mybb->settings['allowremoteavatars']
                            )
                        ) {
                            if (my_validate_url($mybb->settings['useravatar'])) {
                                $account['avatar'] = str_replace(
                                    '{theme}',
                                    'images',
                                    $mybb->settings['useravatar']
                                );
                            } else {
                                $account['avatar'] = '../'.str_replace(
                                    '{theme}',
                                    'images',
                                    $mybb->settings['useravatar']
                                );
                            }
                        } else {
                            $account['avatar'] = str_replace(
                                './',
                                '../',
                                $account['avatar']
                            );
                        }
                        // If no default avatar is set and user has no avatar
                        if ($account['avatar'] == '../') {
                            $account['avatar'] = '../images/eas/default_avatar.png';
                        }
                        $sharedAvatar = '<img src="'.htmlspecialchars_uni($account['avatar'])
                            .'" alt="" width="80" height="80" />';
                        $form_container->output_cell(
                            '<div style="float:left; margin-right: 10px;">'.$sharedAvatar
                            .'</div>&nbsp;&nbsp;<div style="font-weight: bold; font-size: 1.4em; margin: 15px;">'
                            .'<a href="index.php?module=user-users&amp;action=edit&amp;uid='.(int)$account['uid'].'">'
                            .$sharedUser.'</a></div>'
                        );

                        $popup = new PopupMenu("eas_".(int)$account['uid']."", $lang->as_attached_options);
                        $popup->add_item(
                            $lang->as_shared_options_delete,
                            "index.php?module=user-accountswitcher&amp;action=unshare&amp;uid="
                            .(int)$account['uid']."&amp;my_post_key={$mybb->post_code}"
                        );
                        $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                        $form_container->construct_row();
                        ++$i;
                    }
                }
            }
            $form_container->end();
            $form->end();
            // Multipage
            $search_url = htmlspecialchars_uni(
                "index.php?module=user-accountswitcher&action=as_shared&perpage={$mybb->input['perpage']}"
            );
            $multipage = multipage($num_shared, $per_page, $pageview, $search_url);
            echo $multipage;
            $page->output_footer();
            exit;
        }

        // Add new master account
        if ($mybb->get_input('action') == 'as_add_master') {
            $buttonatt = $master_account = $attached_account = array();

            if ($mybb->request_method == 'post') {
                if (empty($mybb->input['musername'])) {
                    $errors[] = $lang->as_missing_master;
                }
                if (empty($mybb->input['ausername'])) {
                    $errors[] = $lang->as_missing_attached;
                }

                $master_account = get_user_by_username($mybb->input['musername'], array('fields' => '*'));
                $master_perms = user_permissions((int)$master_account['uid']);

                require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
                $master_count = $eas->get_attached((int)$master_account['uid']);

                if (!$master_account['uid'] && !empty($mybb->input['musername'])) {
                    $errors[] = $lang->as_no_user;
                } elseif ($master_account['as_uid'] != 0) {
                    $errors[] = $lang->as_user_already_attached;
                } elseif ($master_account['as_share'] != 0) {
                    $errors[] = $lang->as_user_already_shared;
                } elseif ($master_count > 0) {
                    $errors[] = $lang->as_user_already_master;
                } elseif (defined('SUPERADMIN_ONLY')
                    && SUPERADMIN_ONLY == 1
                    && !is_super_admin($mybb->user['uid'])
                    && $master_account['uid'] != $mybb->user['uid']
                    && $master_perms['cancp'] == 1
                ) {
                    $errors[] = $lang->as_no_perm_admin_master.'<b>'
                        .htmlspecialchars_uni($master_account['username']).'</b>';
                } elseif ($master_perms['as_canswitch'] != 1 || $master_perms['isbannedgroup'] != 0) {
                    $errors[] = $lang->as_master_no_switch_perm;
                }

                // Get the new attached accounts
                $attached_accountnames = '';
                $attached_names = explode(',', $mybb->input['ausername']);
                foreach ($attached_names as $attached_name) {
                    $attached_account = get_user_by_username($attached_name, array('fields' => '*'));
                    $attached_perms = user_permissions((int)$attached_account['uid']);
                    $attached_count = $eas->get_attached((int)$attached_account['uid']);
                    // Error handling
                    if (!$attached_account['uid'] && !empty($mybb->input['ausername'])) {
                        $errors[] = $lang->as_no_attached_user.'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_account['as_uid'] != 0) {
                        $errors[] = $lang->as_att_user_already_attached.'<b>'
                            .htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_account['as_share'] != 0) {
                        $errors[] = $lang->as_att_user_already_shared.'<b>'
                            .htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_count > 0) {
                        $errors[] = $lang->as_att_user_already_master.'<b>'
                            .htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_perms['as_canswitch'] != 1
                        || $attached_perms['isbannedgroup'] != 0
                    ) {
                        $errors[] = $lang->as_attached_no_switch_perm.'<b>'
                            .htmlspecialchars_uni($attached_name).'</b>';
                    } elseif (isset($mybb->settings['aj_emailcheck'])
                        && $mybb->settings['aj_emailcheck'] == 1
                        && $attached_account['email'] != $master_account['email']
                    ) {
                        $errors[] = $lang->as_attached_email_addr.'<b>'
                            .htmlspecialchars_uni($attached_name).'</b>';
                    } elseif (defined('SUPERADMIN_ONLY')
                        && SUPERADMIN_ONLY == 1
                        && !is_super_admin($mybb->user['uid'])
                        && $attached_account['uid'] != $mybb->user['uid']
                        && $attached_perms['cancp'] == 1
                    ) {
                        $errors[] = $lang->as_no_perm_admin_attach.'<b>'
                        .htmlspecialchars_uni($attached_name).'</b>';
                    } else {
                        $attached_users[] = (int)$attached_account['uid'];
                        $attached_accountnames .= ' - '.htmlspecialchars_uni($attached_name);
                    }
                }

                // No errors, let's do it
                if (empty($errors)) {
                    foreach ($attached_users as $attached_user) {
                        $updated_record = array(
                            "as_uid" => (int)$master_account['uid']
                        );
                        $db->update_query("users", $updated_record, "uid='".(int)$attached_user."'");
                    }
                    $eas->update_accountswitcher_cache();

                    $mybb->input['module'] = $lang->as_name;
                    $mybb->input['action'] = $lang->as_add_master." ";
                    log_admin_action(
                        $lang->as_master_added_success.'<b>'
                        .htmlspecialchars_uni($master_account['username']).'</b>'
                        .$attached_accountnames
                    );

                    flash_message($lang->as_master_added_success, 'success');
                    admin_redirect("index.php?module=user-accountswitcher");
                }
            }

            // Build the page
            $page->add_breadcrumb_item($lang->as_add_master);
            $page->extra_header .= <<<EOF
            <link rel="stylesheet" href="../jscripts/select2/select2.css" />
            <script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1809"></script>
            <script type="text/javascript">
            <!--
            lang.select2_match = "{$lang->select2_match}";
            lang.select2_matches = "{$lang->select2_matches}";
            lang.select2_nomatches = "{$lang->select2_nomatches}";
            lang.select2_inputtooshort_single = "{$lang->select2_inputtooshort_single}";
            lang.select2_inputtooshort_plural = "{$lang->select2_inputtooshort_plural}";
            lang.select2_inputtoolong_single = "{$lang->select2_inputtoolong_single}";
            lang.select2_inputtoolong_plural = "{$lang->select2_inputtoolong_plural}";
            lang.select2_selectiontoobig_single = "{$lang->select2_selectiontoobig_single}";
            lang.select2_selectiontoobig_plural = "{$lang->select2_selectiontoobig_plural}";
            lang.select2_loadmore = "{$lang->select2_loadmore}";
            lang.select2_searching = "{$lang->select2_searching}";
            // -->
            </script>
EOF;
            $page->output_header($lang->as_name.' - '.$lang->as_add_master);

            $sub_tabs['accountswitcher'] = array(
                'title'             => $lang->as_manage,
                'link'          => 'index.php?module=user-accountswitcher',
                'description'   => $lang->as_manage_description1
            );
            $sub_tabs['as_shared'] = array(
                'title'=>$lang->as_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_shared',
                'description'=>$lang->as_shared_description1
            );
            $sub_tabs['as_add_master'] = array(
                'title'=>$lang->as_add_master,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_master',
                'description'=>$lang->as_add_master_description1
            );
            $sub_tabs['as_add_shared'] = array(
                'title'=>$lang->as_add_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_shared',
                'description'=>$lang->as_add_shared_description1
            );
            $page->output_nav_tabs($sub_tabs, 'as_add_master');

            if (isset($errors)) {
                $page->output_inline_error($errors);
            }

            $mybb->input['musername'] = $mybb->input['ausername'] = '';

            $form = new Form("index.php?module=user-accountswitcher&amp;action=as_add_master", "post", "", 1);

            $form_container = new FormContainer($lang->as_add_master);
            $form_container->output_row(
                $lang->as_add_master_acc,
                $lang->as_add_master_acc_desc,
                $form->generate_text_box(
                    'musername',
                    $mybb->input['musername'],
                    array("id" => "musername", "style" => "width: 200px;"),
                    'musername'
                )
            );
            $form_container->output_row(
                $lang->as_add_attached_acc,
                $lang->as_add_attached_acc_desc,
                $form->generate_text_box(
                    'ausername',
                    $mybb->input['ausername'],
                    array("id" => "ausername", "style" => "width: 200px;"),
                    'ausername'
                )
            );
            $form_container->end();
            $buttonatt[] = $form->generate_submit_button($lang->as_add_master_attachbutton);
            $form->output_submit_wrapper($buttonatt);
            $form->end();
            echo '<script type="text/javascript">
                <!--
                    MyBB.select2();
                    $("#ausername").select2({
                        placeholder: "'.$lang->as_search_user.'",
                        minimumInputLength: 2,
                        multiple: true,
                        ajax: {
                        // instead of writing the function to execute the request we use Select2\'s convenient helper
                            url: "../xmlhttp.php?action=get_users",
                            dataType: "json",
                            data: function (term, page) {
                                return {
                                    query: term, // search term
                                };
                            },
                            results: function (data, page) { // parse the results into the format expected by Select2.
                            // since we are using custom formatting functions we do not need to alter remote JSON data
                                return {results: data};
                            }
                        },
                        initSelection: function(element, callback) {
                            var query = $(element).val();
                            if (query !== "") {
                                $.ajax("../xmlhttp.php?action=get_users", {
                                    data: {
                                        query: query
                                    },
                                    dataType: "json"
                                }).done(function(data) { callback(data); });
                            }
                        },
                       // Allow the user entered text to be selected as well
                       createSearchChoice:function(term, data) {
                            if ( $(data).filter( function() {
                                return this.text.localeCompare(term)===0;
                            }).length===0) {
                                return {id:term, text:term};
                            }
                        },
                    });
                // -->
            </script>
            <script type="text/javascript">
            <!--
                    MyBB.select2();
                    $("#musername").select2({
                        placeholder: "'.$lang->as_search_user.'",
                        minimumInputLength: 2,
                        multiple: false,
                        ajax: {
                        // instead of writing the function to execute the request we use Select2\'s convenient helper
                            url: "../xmlhttp.php?action=get_users",
                            dataType: "json",
                            data: function (term, page) {
                                return {
                                    query: term, // search term
                                };
                            },
                            results: function (data, page) { // parse the results into the format expected by Select2.
                            // since we are using custom formatting functions we do not need to alter remote JSON data
                                return {results: data};
                            }
                        },
                        initSelection: function(element, callback) {
                            var query = $(element).val();
                            if (query !== "") {
                                $.ajax("../xmlhttp.php?action=get_users", {
                                    data: {
                                        query: query
                                    },
                                    dataType: "json"
                                }).done(function(data) { callback(data); });
                            }
                        },
                       // Allow the user entered text to be selected as well
                       createSearchChoice:function(term, data) {
                            if ( $(data).filter( function() {
                                return this.text.localeCompare(term)===0;
                            }).length===0) {
                                return {id:term, text:term};
                            }
                        },
                    });
                // -->
            </script>';
            $page->output_footer();
            exit;
        }

        // Add new shared account
        if ($mybb->get_input('action') == 'as_add_shared') {
            $buttonatt = array();

            if ($mybb->request_method == 'post') {
                if (empty($mybb->input['susername'])) {
                    $errors[] = $lang->as_missing_shared;
                }

                require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

                // Get the new shared accounts
                $shared_account = array();
                $shared_accountnames = '';
                $shared_names = explode(',', $mybb->input['susername']);
                foreach ($shared_names as $shared_name) {
                    $shared_account = get_user_by_username($shared_name, array('fields' => '*'));
                    $shared_perms = user_permissions((int)$shared_account['uid']);
                    $shared_count = $eas->get_attached((int)$shared_account['uid']);
                    // Error handling
                    if (!$shared_account['uid'] && !empty($mybb->input['susername'])) {
                        $errors[] = $lang->as_no_attached_user.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_perms['cancp'] != 0) {
                        $errors[] = $lang->as_user_admin.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_perms['issupermod'] != 0) {
                        $errors[] = $lang->as_user_supermod.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_perms['canmodcp'] != 0) {
                        $errors[] = $lang->as_user_mod.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_account['as_uid'] != 0) {
                        $errors[] = $lang->as_att_user_already_attached.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_account['as_share'] != 0) {
                        $errors[] = $lang->as_att_user_already_shared.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_count > 0) {
                        $errors[] = $lang->as_att_user_already_master.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } elseif ($shared_perms['as_canswitch'] != 1 || $shared_perms['isbannedgroup'] != 0) {
                        $errors[] = $lang->as_attached_no_switch_perm.'<b>'.htmlspecialchars_uni($shared_name).'</b>';
                    } else {
                        $shared_users[] = (int)$shared_account['uid'];
                        $shared_accountnames .= ' - '.htmlspecialchars_uni($shared_name);
                    }
                }

                // No errors, let's do it
                if (empty($errors)) {
                    foreach ($shared_users as $shared_user) {
                        $updated_record = array(
                            "as_share" => 1
                        );
                        $db->update_query("users", $updated_record, "uid='".(int)$shared_user."'");
                    }
                    $eas->update_accountswitcher_cache();

                    $mybb->input['module'] = $lang->as_name;
                    $mybb->input['action'] = $lang->as_add_shared." ";
                    log_admin_action($lang->as_shared_added_success.$shared_accountnames);

                    flash_message($lang->as_shared_added_success, 'success');
                    admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
                }
            }

            // Build the page
            $page->add_breadcrumb_item($lang->as_add_shared);
            $page->extra_header .= <<<EOF
            <link rel="stylesheet" href="../jscripts/select2/select2.css" />
            <script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1809"></script>
            <script type="text/javascript">
            <!--
            lang.select2_match = "{$lang->select2_match}";
            lang.select2_matches = "{$lang->select2_matches}";
            lang.select2_nomatches = "{$lang->select2_nomatches}";
            lang.select2_inputtooshort_single = "{$lang->select2_inputtooshort_single}";
            lang.select2_inputtooshort_plural = "{$lang->select2_inputtooshort_plural}";
            lang.select2_inputtoolong_single = "{$lang->select2_inputtoolong_single}";
            lang.select2_inputtoolong_plural = "{$lang->select2_inputtoolong_plural}";
            lang.select2_selectiontoobig_single = "{$lang->select2_selectiontoobig_single}";
            lang.select2_selectiontoobig_plural = "{$lang->select2_selectiontoobig_plural}";
            lang.select2_loadmore = "{$lang->select2_loadmore}";
            lang.select2_searching = "{$lang->select2_searching}";
            // -->
            </script>
EOF;
            $page->output_header($lang->as_name.' - '.$lang->as_add_shared);

            $sub_tabs['accountswitcher'] = array(
                'title'             => $lang->as_manage,
                'link'          => 'index.php?module=user-accountswitcher',
                'description'   => $lang->as_manage_description1
            );
            $sub_tabs['as_shared'] = array(
                'title'=>$lang->as_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_shared',
                'description'=>$lang->as_shared_description1
            );
            $sub_tabs['as_add_master'] = array(
                'title'=>$lang->as_add_master,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_master',
                'description'=>$lang->as_add_master_description1
            );
            $sub_tabs['as_add_shared'] = array(
                'title'=>$lang->as_add_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_shared',
                'description'=>$lang->as_add_shared_description1
            );
            $page->output_nav_tabs($sub_tabs, 'as_add_shared');

            if (isset($errors)) {
                $page->output_inline_error($errors);
            }

            $mybb->input['susername'] = '';

            $form = new Form("index.php?module=user-accountswitcher&amp;action=as_add_shared", "post", "", 1);

            $form_container = new FormContainer($lang->as_add_shared);
            $form_container->output_row(
                $lang->as_add_shared_acc,
                $lang->as_add_shared_acc_desc,
                $form->generate_text_box(
                    'susername',
                    $mybb->input['susername'],
                    array("id" => "susername", "style" => "width: 200px;"),
                    'susername'
                )
            );
            $form_container->end();
            $buttonatt[] = $form->generate_submit_button($lang->as_add_master_attachbutton);
            $form->output_submit_wrapper($buttonatt);
            $form->end();
            echo '<script type="text/javascript">
                <!--
                    MyBB.select2();
                    $("#susername").select2({
                        placeholder: "'.$lang->as_search_user.'",
                        minimumInputLength: 2,
                        multiple: true,
                        ajax: {
                        // instead of writing the function to execute the request we use Select2\'s convenient helper
                            url: "../xmlhttp.php?action=get_users",
                            dataType: "json",
                            data: function (term, page) {
                                return {
                                    query: term, // search term
                                };
                            },
                            results: function (data, page) { // parse the results into the format expected by Select2.
                            // since we are using custom formatting functions we do not need to alter remote JSON data
                                return {results: data};
                            }
                        },
                        initSelection: function(element, callback) {
                            var query = $(element).val();
                            if (query !== "") {
                                $.ajax("../xmlhttp.php?action=get_users", {
                                    data: {
                                        query: query
                                    },
                                    dataType: "json"
                                }).done(function(data) { callback(data); });
                            }
                        },
                       // Allow the user entered text to be selected as well
                       createSearchChoice:function(term, data) {
                            if ( $(data).filter( function() {
                                return this.text.localeCompare(term)===0;
                            }).length===0) {
                                return {id:term, text:term};
                            }
                        },
                    });
                // -->
            </script>';
            $page->output_footer();
            exit;
        }

        // Delete all attached accounts of a master account
        if ($mybb->get_input('action') == 'delete') {
            $master_account = array();

            if (empty($mybb->input['uid'])) {
                flash_message($lang->as_invalid_master, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=user-accountswitcher");
            }
            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            } else {
                if ($mybb->request_method == 'post') {
                    $master_uid = $mybb->get_input('uid', MyBB::INPUT_INT);
                    $master_account = get_user((int)$master_uid);
                    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

                    // Does the master account exist?
                    if (!empty($master_account)) {
                        $master_count = $eas->get_attached((int)$master_account['uid']);
                    }
                    // Are there accounts attached?
                    if ($master_count == 0) {
                        flash_message($lang->as_invalid_master, 'error');
                        admin_redirect("index.php?module=user-accountswitcher");
                    } else {
                        $updated_record = array(
                            "as_uid" => 0
                        );
                        $db->update_query("users", $updated_record, "as_uid='".(int)$master_account['uid']."'");

                        $eas->update_accountswitcher_cache();

                        $mybb->input['module'] = $lang->as_name;
                        $mybb->input['action'] = $lang->as_delete_master." ";
                        log_admin_action(
                            $lang->as_master_deleted_success.': <b>'
                            .htmlspecialchars_uni($master_account['username']).'</b>'
                        );

                        flash_message($lang->as_master_deleted_success, 'success');
                        admin_redirect("index.php?module=user-accountswitcher");
                    }
                } else {
                    $page->output_confirm_action(
                        "index.php?module=user-accountswitcher&amp;action=delete&amp;uid=".(int)$mybb->input['uid']."",
                        $lang->as_master_deleted_ok
                    );
                }
            }
            exit;
        }

        // Unshare an account
        if ($mybb->get_input('action') == 'unshare') {
            $shared_account = array();

            if (empty($mybb->input['uid'])) {
                flash_message($lang->as_invalid_master, 'error');
                admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
            }
            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
            } else {
                if ($mybb->request_method == 'post') {
                    $shared_uid = $mybb->get_input('uid', MyBB::INPUT_INT);
                    $shared_account = get_user((int)$shared_uid);
                    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

                    if (empty($shared_account)) {
                        flash_message($lang->as_invalid_master, 'error');
                        admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
                    } else {
                        $updated_record = array(
                            "as_share" => 0,
                            "as_shareuid" => 0
                        );
                        $db->update_query("users", $updated_record, "uid='".(int)$shared_account['uid']."'");

                        $eas->update_accountswitcher_cache();

                        $mybb->input['module'] = $lang->as_name;
                        $mybb->input['action'] = $lang->as_delete_shared." ";
                        log_admin_action(
                            $lang->as_shared_deleted_success.': <b>'
                            .htmlspecialchars_uni($shared_account['username']).'</b>'
                        );

                        flash_message($lang->as_shared_deleted_success, 'success');
                        admin_redirect("index.php?module=user-accountswitcher&amp;action=as_shared");
                    }
                } else {
                    $page->output_confirm_action(
                        "index.php?module=user-accountswitcher&amp;action=unshare&amp;uid=".(int)$mybb->input['uid']."",
                        $lang->as_shared_deleted_ok
                    );
                }
            }
            exit;
        }

        // Edit attached accounts of master account
        if ($mybb->get_input('action') == 'edit') {
            $master_account = $buttonatt = array();
            $master_count = 0;

            if (empty($mybb->input['uid'])) {
                flash_message($lang->as_invalid_master, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            }
            $master_uid = $mybb->get_input('uid', MyBB::INPUT_INT);
            $master_account = get_user((int)$master_uid);

            require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
            $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
            // Does the master account exist?
            if (!empty($master_account)) {
                $master_count = $eas->get_attached((int)$master_account['uid']);
            }
            // Are there accounts attached?
            if ($master_count == 0) {
                flash_message($lang->as_invalid_master, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            }

            // Get already attached accounts
            $account_query = $db->simple_select('users', '*', "as_uid='".(int)$master_account['uid']."'");
            $attached_accounts = '<ul>';
            while ($attached_account = $db->fetch_array($account_query)) {
                $att_account = '<img src="styles/default/images/icons/user.png" alt="" />&nbsp;&nbsp;'
                    .format_name(
                        htmlspecialchars_uni($attached_account['username']),
                        (int)$attached_account['usergroup'],
                        (int)$attached_account['displaygroup']
                    )
                    .'&nbsp;&nbsp;';
                $att_delete = '<a style="float: right;" href="index.php?module=user-accountswitcher&amp;action=detach'
                    .'&amp;uid='.(int)$attached_account['uid'].'&amp;my_post_key='.$mybb->post_code.'">'
                    .'<img src="styles/default/images/icons/cross.png" alt="'.$lang->as_edit_attached_delete
                    .'" title="'.$lang->as_edit_attached_delete.'" /></a>';
                $attached_accounts .= '<li style="list-style: none; padding: 10px; border-bottom: 1px solid; '
                    .'width: 200px;">'.$att_account.$att_delete.'</li>';
            }
            $attached_accounts .= '</ul>';

            if ($mybb->request_method == 'post') {
                // Get the new attached accounts
                $attached_accountnames = '';
                $attached_users = $attached_account = array();
                $attached_names = explode(',', $mybb->input['ausername']);
                foreach ($attached_names as $attached_name) {
                    $attached_account = get_user_by_username($attached_name, array('fields' => '*'));
                    $attached_perms = user_permissions((int)$attached_account['uid']);
                    $attached_count = $eas->get_attached((int)$attached_account['uid']);
                    // Error handling
                    if (!$attached_account['uid'] && !empty($mybb->input['ausername'])) {
                        $errors[] = $lang->as_no_attached_user.'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_account['as_uid'] != 0) {
                        $errors[] = $lang->as_att_user_already_attached
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_account['as_share'] != 0) {
                        $errors[] = $lang->as_att_user_already_shared
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_count > 0) {
                        $errors[] = $lang->as_att_user_already_master
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif ($attached_perms['as_canswitch'] != 1
                        || $attached_perms['isbannedgroup'] != 0
                    ) {
                        $errors[] = $lang->as_attached_no_switch_perm
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif (isset($mybb->settings['aj_emailcheck'])
                        && $mybb->settings['aj_emailcheck'] == 1
                        && $attached_account['email'] != $master_account['email']
                    ) {
                        $errors[] = $lang->as_attached_email_addr
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } elseif (defined('SUPERADMIN_ONLY')
                        && SUPERADMIN_ONLY == 1
                        && !is_super_admin($mybb->user['uid'])
                        && $attached_account['uid'] != $mybb->user['uid']
                        && $attached_perms['cancp'] == 1
                    ) {
                        $errors[] = $lang->as_no_perm_admin_attach
                            .'<b>'.htmlspecialchars_uni($attached_name).'</b>';
                    } else {
                        $attached_users[] = (int)$attached_account['uid'];
                        $attached_accountnames .= ' - '.htmlspecialchars_uni($attached_name);
                    }
                }

                // No errors, let's do it
                if (empty($errors)) {
                    foreach ($attached_users as $attached_user) {
                        $updated_record = array(
                            "as_uid" => (int)$master_account['uid']
                        );
                        $db->update_query("users", $updated_record, "uid='".(int)$attached_user."'");
                    }
                    $eas->update_accountswitcher_cache();

                    $mybb->input['module'] = $lang->as_name;
                    $mybb->input['action'] = $lang->as_edit_master." ";
                    log_admin_action(
                        $lang->as_master_added_success.': <b>'
                        .htmlspecialchars_uni($master_account['username']).'</b>'
                        .$attached_accountnames
                    );

                    flash_message($lang->as_master_added_success, 'success');
                    admin_redirect(
                        "index.php?module=user-accountswitcher&amp;action=edit&amp;uid="
                        .(int)$master_account['uid'].""
                    );
                }
            }

            // Build the page
            $page->add_breadcrumb_item($lang->as_edit_master);
            $page->extra_header .= <<<EOF
            <link rel="stylesheet" href="../jscripts/select2/select2.css" />
            <script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1809"></script>
            <script type="text/javascript">
            <!--
            lang.select2_match = "{$lang->select2_match}";
            lang.select2_matches = "{$lang->select2_matches}";
            lang.select2_nomatches = "{$lang->select2_nomatches}";
            lang.select2_inputtooshort_single = "{$lang->select2_inputtooshort_single}";
            lang.select2_inputtooshort_plural = "{$lang->select2_inputtooshort_plural}";
            lang.select2_inputtoolong_single = "{$lang->select2_inputtoolong_single}";
            lang.select2_inputtoolong_plural = "{$lang->select2_inputtoolong_plural}";
            lang.select2_selectiontoobig_single = "{$lang->select2_selectiontoobig_single}";
            lang.select2_selectiontoobig_plural = "{$lang->select2_selectiontoobig_plural}";
            lang.select2_loadmore = "{$lang->select2_loadmore}";
            lang.select2_searching = "{$lang->select2_searching}";
            // -->
            </script>
EOF;
            $page->output_header($lang->as_name.' - '.$lang->as_edit_master);

            $sub_tabs['accountswitcher'] = array(
                'title'             => $lang->as_manage,
                'link'          => 'index.php?module=user-accountswitcher',
                'description'   => $lang->as_manage_description1
            );
            $sub_tabs['edit'] = array(
                'title'             => $lang->as_edit_master,
                'link'          => 'index.php?module=user-accountswitcher&amp;action=edit',
                'description'   => $lang->as_edit_master_description1
            );
            $sub_tabs['as_shared'] = array(
                'title'=>$lang->as_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_shared',
                'description'=>$lang->as_shared_description1
            );
            $sub_tabs['as_add_master'] = array(
                'title'=>$lang->as_add_master,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_master',
                'description'=>$lang->as_add_master_description1
            );
            $sub_tabs['as_add_shared'] = array(
                'title'=>$lang->as_add_shared,
                'link'=>'index.php?module=user-accountswitcher&amp;action=as_add_shared',
                'description'=>$lang->as_add_shared_description1
            );
            $page->output_nav_tabs($sub_tabs, 'edit');

            if (isset($errors)) {
                $page->output_inline_error($errors);
            }

            $mybb->input['ausername'] = '';

            $form = new Form(
                "index.php?module=user-accountswitcher&amp;action=edit&amp;uid=".(int)$mybb->input['uid']."",
                "post",
                "",
                1
            );

            $form_container = new FormContainer($lang->as_edit_master);
            if (!$master_account['avatar']
                || (my_strpos($master_account['avatar'], '://') !== false
                    && !$mybb->settings['allowremoteavatars']
                )
            ) {
                if (my_validate_url($mybb->settings['useravatar'])) {
                    $master_account['avatar'] = str_replace(
                        '{theme}',
                        'images',
                        $mybb->settings['useravatar']
                    );
                } else {
                    $master_account['avatar'] = '../'.str_replace(
                        '{theme}',
                        'images',
                        $mybb->settings['useravatar']
                    );
                }
            } else {
                $master_account['avatar'] = str_replace(
                    './',
                    '../',
                    $master_account['avatar']
                );
            }
            // If no default avatar is set and user has no avatar
            if ($master_account['avatar'] == '../') {
                $master_account['avatar'] = '../images/eas/default_avatar.png';
            }
            $masterAvatar = '<img src="'.htmlspecialchars_uni($master_account['avatar']).
                '" alt="" width="37" height="37" />';
            $form_container->output_row(
                $lang->as_edit_master_acc,
                $lang->as_edit_master_acc_desc,
                '<div style="float:left; margin-right: 10px;">'.$masterAvatar
                .'</div>&nbsp;&nbsp;<span style="font-size: 1.4em; line-height: 2.5;">'
                .format_name(
                    htmlspecialchars_uni($master_account['username']),
                    (int)$master_account['usergroup'],
                    (int)$master_account['displaygroup']
                )
                .'</span>'
            );
            $form_container->output_row(
                $lang->as_edit_attached_acc,
                $lang->as_edit_attached_acc_desc,
                $attached_accounts
            );
            $form_container->output_row(
                $lang->as_edit_attached_add,
                $lang->as_edit_attached_add_desc,
                $form->generate_text_box(
                    'ausername',
                    $mybb->input['ausername'],
                    array("id" => "ausername", "style" => "width: 200px;"),
                    'ausername'
                )
            );
            $form_container->end();
            $buttonatt[] = $form->generate_submit_button($lang->as_add_master_attachbutton);
            $form->output_submit_wrapper($buttonatt);
            $form->end();
            echo '<script type="text/javascript">
                <!--
                    MyBB.select2();
                    $("#ausername").select2({
                        placeholder: "'.$lang->as_search_user.'",
                        minimumInputLength: 2,
                        multiple: true,
                        ajax: {
                        // instead of writing the function to execute the request we use Select2\'s convenient helper
                            url: "../xmlhttp.php?action=get_users",
                            dataType: "json",
                            data: function (term, page) {
                                return {
                                    query: term, // search term
                                };
                            },
                            results: function (data, page) { // parse the results into the format expected by Select2.
                            // since we are using custom formatting functions we do not need to alter remote JSON data
                                return {results: data};
                            }
                        },
                        initSelection: function(element, callback) {
                            var query = $(element).val();
                            if (query !== "") {
                                $.ajax("../xmlhttp.php?action=get_users", {
                                    data: {
                                        query: query
                                    },
                                    dataType: "json"
                                }).done(function(data) { callback(data); });
                            }
                        },
                       // Allow the user entered text to be selected as well
                       createSearchChoice:function(term, data) {
                            if ( $(data).filter( function() {
                                return this.text.localeCompare(term)===0;
                            }).length===0) {
                                return {id:term, text:term};
                            }
                        },
                    });
                // -->
            </script>';
            $page->output_footer();
            exit;
        }

        // Detach an account
        if ($mybb->get_input('action') == 'detach') {
            $detach_account = array();

            if (empty($mybb->input['uid'])) {
                flash_message($lang->as_invalid_master, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=user-accountswitcher");
            }
            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=user-accountswitcher");
            } else {
                if ($mybb->request_method == 'post') {
                    $detach_uid = $mybb->get_input('uid', MyBB::INPUT_INT);
                    $detach_account = get_user((int)$detach_uid);
                    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
                    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

                    // Does the account exist?
                    if (empty($detach_account)) {
                        flash_message($lang->as_invalid_master, 'error');
                        admin_redirect("index.php?module=user-accountswitcher");
                    } else {
                        $master_account = get_user((int)$detach_account['as_uid']);

                        $updated_record = array(
                            "as_uid" => 0,
                        );
                        $db->update_query("users", $updated_record, "uid='".(int)$detach_account['uid']."'");

                        $eas->update_accountswitcher_cache();

                        $mybb->input['module'] = $lang->as_name;
                        $mybb->input['action'] = $lang->as_edit_attached_delete." ";
                        log_admin_action(
                            $lang->as_detach_attached.': <b>'
                            .htmlspecialchars_uni($detach_account['username']).'</b> ('
                            .$lang->as_from.$lang->as_edit_master_acc.': '
                            .htmlspecialchars_uni($master_account['username']).')'
                        );

                        flash_message($lang->as_edit_detached_ok, 'success');
                        admin_redirect(
                            "index.php?module=user-accountswitcher&amp;action=edit&amp;uid="
                            .(int)$detach_account['as_uid'].""
                        );
                    }
                } else {
                    $page->output_confirm_action(
                        "index.php?module=user-accountswitcher&amp;action=detach&amp;uid=".(int)$mybb->input['uid']."",
                        $lang->as_edit_attached_ok
                    );
                }
            }
        }
        exit;
    }
}
