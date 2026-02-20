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

if (!defined('IN_MYBB')) {
    die('We all know this is your own fault.');
}

/**
 * The Plugin info.
 *
 * @return array The plugin information in plugin list.
 */
function accountswitcher_info()
{
    global $mybb, $lang, $db, $plugins_cache, $cache;

    $lang->load('accountswitcher');

    $accountswitcher_info = array(
        'name'          => $lang->as_name,
        'description'   => $lang->as_desc,
        'website'       => 'http://doylecc.altervista.org',
        'author'        => 'doylecc',
        'authorsite'    => 'http://doylecc.altervista.org',
        'version'       => '2.1.13',
        'compatibility'     => '18*',
        'codename'      => 'eas_accountswitcher'
    );

    // Add settings link to plugin info
    if (accountswitcher_is_installed()
        && is_array($plugins_cache)
        && is_array($plugins_cache['active'])
        && isset($plugins_cache['active']['accountswitcher'])
    ) {
        // Display upgrade message
        $cached_version = $cache->read('dcc_plugins');
        if (!is_array($cached_version)
            || (is_array($cached_version)
                && (!isset($cached_version['release'])
                || $cached_version['release'] != $accountswitcher_info['version'])
            )
        ) {
            $accountswitcher_info['description'] .= '<div style="font-size:1.2em; margin-top:7px; text-align:center;';
            $accountswitcher_info['description'] .= ' color:red;">';
            $accountswitcher_info['description'] .= $lang->as_not_upgraded.'</div>';
        } else {
            $result = $db->simple_select("settinggroups", "gid", "name = 'accountswitcher'");
            $set = $db->fetch_array($result);
            if (!empty($set)) {
                $accountswitcher_info['description'] .= '<div style="float: right; padding-right: 20px;">';
                $accountswitcher_info['description'] .= '<img src="styles/default/images/icons/custom.png" alt="" ';
                $accountswitcher_info['description'] .= 'style="margin-left: 10px;" /><a href="';
                $accountswitcher_info['description'] .= 'index.php?module=config-settings&amp;action=change&amp;gid=';
                $accountswitcher_info['description'] .= (int)$set['gid'].'" style="margin: 10px;">';
                $accountswitcher_info['description'] .= $lang->as_name_settings.'</a>|';
            }
            // Add cleanup and debug links
            $accountswitcher_info['description'] .= '<img src="styles/default/images/icons/run_task.png" alt="" ';
            $accountswitcher_info['description'] .= 'style="margin-left: 10px;" /><a href="index.php?module=';
            $accountswitcher_info['description'] .= 'tools-accountswitcher&amp;action=cleanup&amp;my_post_key=';
            $accountswitcher_info['description'] .= $mybb->post_code.'" style="margin: 10px;">';
            $accountswitcher_info['description'] .= $lang->as_name_cleanup.'</a>|';
            $accountswitcher_info['description'] .= '<img src="styles/default/images/icons/find.png" alt="" style=';
            $accountswitcher_info['description'] .= '"margin-left: 10px;" /><a href="../index.php?as_debug=1" style=';
            $accountswitcher_info['description'] .= '"margin: 10px;" target="_blank">Plugin Debug Information</a>';
            $accountswitcher_info['description'] .= '<hr style="margin-bottom: 5px;" /></div>';
        }
    }
    return $accountswitcher_info;
}

// Load the install/admin functions in ACP.
if (defined('IN_ADMINCP')) {
    define('SUPERADMIN_ONLY', 1);
    require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_install.php';
    require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_admincp.php';
} else {
    // Load all the frontend functions
    global $mybb, $cache;

    require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_usercp.php';
    require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_functions.php';
    if ($mybb->settings['aj_myalerts'] == 1 && isset($mybb->settings['myalerts_avatar_size'])) {
        $plugins_cache = $cache->read('plugins');
        if (is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active']['myalerts']) {
            require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_alerts.php';
        }
    }
}



//########## Cache and online functions ################
$plugins->add_hook('global_start', 'accountswitcher_init');
$plugins->add_hook('admin_load', 'accountswitcher_init');
/**
 * Load the account switcher class.
 *
 */
function accountswitcher_init()
{
    global $mybb, $db, $cache, $templates, $eas, $eas_version;

    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
    $eas_version = $eas->easversion_cache;
}


// Add the hooks for updating the cache
$plugins->add_hook('private_do_send_end', 'accountswitcher_pm');
$plugins->add_hook('private_read_end', 'accountswitcher_pm');
$plugins->add_hook('newreply_do_newreply_end', 'accountswitcher_pm');
$plugins->add_hook('newthread_do_newthread_end', 'accountswitcher_pm');
$plugins->add_hook('usercp_do_avatar_end', 'accountswitcher_pm');
$plugins->add_hook('usercp_do_options_end', 'accountswitcher_pm');
$plugins->add_hook('usercp_do_changename_end', 'accountswitcher_pm');
$plugins->add_hook('usercp_usergroups_start', 'accountswitcher_pm');
$plugins->add_hook('usercp_editlists_start', 'accountswitcher_pm');
$plugins->add_hook('admin_user_users_edit_commit', 'accountswitcher_pm');
$plugins->add_hook('modcp_do_editprofile_end', 'accountswitcher_pm');

/**
 * Updates the cache.
 *
 */
function accountswitcher_pm()
{
    global $eas;
    $eas->update_accountswitcher_cache();
}

// Add the hooks for updating profile field cache
$plugins->add_hook('admin_config_settings_change_commit', 'accountswitcher_ufields');
$plugins->add_hook('admin_user_users_edit_commit', 'accountswitcher_ufields');
$plugins->add_hook('usercp_do_profile_end', 'accountswitcher_ufields');
$plugins->add_hook('modcp_do_editprofile_end', 'accountswitcher_ufields');
/**
 * Updates the cache when changing plugin settings or user profile settings.
 *
 */
function accountswitcher_ufields()
{
    global $eas;
    $eas->update_userfields_cache();
}


// Add hook for online location
$plugins->add_hook('build_friendly_wol_location_end', 'accountswitcher_online');
/**
 * Show the location for accountlist in online.php.
 *
 */
function accountswitcher_online(&$plugin_array)
{
    global $lang;

    if (!isset($lang->aj_online)) {
        $lang->load('accountswitcher');
    }

    if (my_strpos($plugin_array['user_activity']['location'], 'accountlist.php')) {
        $plugin_array['location_name'] = $lang->aj_online;
    }
}


// Hook for new user registration
$plugins->add_hook('datahandler_user_insert', 'accountswitcher_new_user');
/**
 * Set a default value for column as_secreason.
 *
 */
function accountswitcher_new_user(&$user)
{
    $user->user_insert_data['as_secreason'] = '';
}

// Add hook for debug page
$plugins->add_hook('index_end', 'accountswitcher_debug');
/**
 * Show account switcher debug information
 *
 */
function accountswitcher_debug()
{
    global $mybb, $db, $cache, $lang, $eas;

    // Admin access only
    if ($mybb->usergroup['cancp'] == 1 && isset($mybb->input['as_debug']) && $mybb->input['as_debug'] == 1) {
    // Declare variables
        $grouprows = $groupname = $groupid = $groupswitch = $grouplimit = $groupperm = $nogroups = '';
        $groupcache = $cache->read('usergroups');
        $count = 0;
        $myalertscheck = 'no';
        $permitted_groups = array();
        if (isset($mybb->settings['aj_groupperm']) && $mybb->settings['aj_groupperm'] == -1) {
            $groupperm = 1;
        }
        if ($db->table_exists('alert_types')) {
            $myalertscheck = 'yes';
        }
        $lang->load('accountswitcher');
        // User group settings
        if (is_array($groupcache)) {
            foreach ($groupcache as $usergroup) {
                $groupname = htmlspecialchars_uni($usergroup['title']);
                $groupid = (int)$usergroup['gid'];
                $groupswitch = (int)$usergroup['as_canswitch'];
                $grouplimit = (int)$usergroup['as_limit'];
                if (isset($mybb->settings['aj_groupperm'])
                    && $mybb->settings['aj_groupperm'] == ''
                    && $usergroup['cancp'] == 0
                ) {
                    $groupperm = 0;
                } elseif (isset($mybb->settings['aj_groupperm'])
                    && $mybb->settings['aj_groupperm'] == ''
                    && $usergroup['cancp'] == 1
                ) {
                    $groupperm = 1;
                } elseif (isset($mybb->settings['aj_groupperm'])
                    && $mybb->settings['aj_groupperm'] != ''
                    && $mybb->settings['aj_groupperm'] != -1
                ) {
                    $permitted_groups = explode(',', $mybb->settings['aj_groupperm']);
                    if (in_array($usergroup['gid'], $permitted_groups)
                        || $usergroup['cancp'] == 1
                    ) {
                        $groupperm = 1;
                    } else {
                        $groupperm = 0;
                    }
                }
                $grouprows .= '
                    <tr>
                    <td style="background: #fff;">'.$groupname.'</td><td style="background: #fff; text-align: center;">'
                    .$groupid.
                    '</td><td style="background: #fff; text-align: center;">'
                    .$groupswitch.
                    '</td><td style="background: #fff; text-align: center;">'.$grouplimit.'</td>
                    </td><td style="background: #fff; text-align: center;">'.$groupperm.'</td>
                    </tr>
                    ';
                if ($usergroup['as_canswitch'] == 1) {
                    ++$count;
                }
            }
        }

        if ($count == 0) {
            $nogroups = '<tr>
                            <td style="background: #fff;" colspan="4">
                                <h3 style="color: red;">'.$lang->as_installed_nogroup.'</h3>
                            </td>
                        </tr>';
        }

        // Output page
        echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"";
        echo "\"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
        echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">\n";
        echo "<head>\n";
        echo "<meta name=\"robots\" content=\"noindex\" />\n";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\n";
        echo "<title>Enhanced Account Switcher Debug Information</title>\n";
        echo "</head>\n";
        echo "<body>\n";
        echo "<table style=\"background-color: #666; font-size: 0.9em;\" width=\"91%\" cellpadding=\"4\" ";
        echo "cellspacing=\"1\" align=\"center\">\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff; text-align: center;\"><h2>Enhanced Account Switcher Debug Information";
        echo "</h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td>\n";
        echo "<table style=\"background-color: #666; font-size: 0.9em; float: left;\" width=\"49%\" cellpadding=\"4\"";
        echo " cellspacing=\"1\">\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"2\">";
        echo "<h2 style=\"text-align: center;\">Plugin Settings </h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Account switch on posting: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_postjump']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Change author of an existing post: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_changeauthor']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">PM Notice for all attached accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_pmnotice']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Attached accounts in profile: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_profile']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Away Status of the master account for all attached accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_away']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Reload after switch from dropdown list: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_reload']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Accountlist: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_list']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Accounts in Postbit: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_postuser']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Share Accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_shareuser']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Username Style of Shared Accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_sharestyle']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Sort Accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">";
        echo htmlspecialchars_uni($mybb->settings['aj_sortuser'])."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Accounts in header as dropdown: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_headerdropdown'];
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Team change post author: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_admin_changeauthor'];
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Team author change permission: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">";
        echo htmlspecialchars_uni($mybb->settings['aj_admin_changegroup'])."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">PM after Author Moderation: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_authorpm']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Attached Accounts in Memberlist and on Showteam Page: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_memberlist']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Attached Accounts in Sidebar: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_sidebar']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Mark Secondary Accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_secstyle']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Profile Field in Accountlist: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_profilefield']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">ID of the Profile Field: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_profilefield_id'];
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Sort Master Accounts by Usergroups in Accountlist: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_sortgroup']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Attached Accounts Post Count: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_postcount']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">MyAlerts Integration: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_myalerts']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Hide Attached Accounts: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_privacy']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Email Check When Attaching Accounts in ACP: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_emailcheck']."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\">Automatic Template Edits When Importing/Adding New Theme: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['aj_tpledit']."</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<table style=\"background-color: #666; float: left; font-size: 0.9em; margin-left: 27px;\" ";
        echo "width=\"49%\" cellpadding=\"4\" cellspacing=\"1\">\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"5\">";
        echo "<h2 style=\"text-align: center;\">Plugin Version </h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"4\">Version:</td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".$eas->release."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"4\">Version Number:</td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".$eas->version."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"5\">";
        echo "<h2 style=\"text-align: center;\">Ajax Setting </h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"4\">Enable XMLHttp request features: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".(int)$mybb->settings['use_xmlhttprequest'];
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"5\">";
        echo "<h2 style=\"text-align: center;\">My Alerts 2.0 Plugin </h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"4\">Installed: </td>\n";
        echo "<td style=\"background: #fff; text-align: center;\">".$myalertscheck."</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff;\" colspan=\"5\">";
        echo "<h2 style=\"text-align: center;\">User Group Settings </h2>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td style=\"background: #fff; font-weight: bold;\">Group Title</td>\n";
        echo "<td style=\"background: #fff; font-weight: bold; text-align: center;\">Group ID</td>\n";
        echo "<td style=\"background: #fff; font-weight: bold; text-align: center;\">Can Switch</td>\n";
        echo "<td style=\"background: #fff; font-weight: bold; text-align: center;\">Switch Limit</td>\n";
        echo "<td style=\"background: #fff; font-weight: bold; text-align: center;\">Can View Lists</td>\n";
        echo "</tr>\n";
        echo $grouprows;
        echo $nogroups;
        echo "</table>\n";
        echo "<br />\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</body>";
        echo "</html>";
        exit;
    }
}


// Caching templates
global $templatelist;

if (isset($templatelist)) {
    $templatelist .= ',';
}
$templatelist .= 'accountswitcher_header_userbit,accountswitcher_header_dropdown_userbit,accountswitcher_header';
$templatelist .= ',accountswitcher_header_dropdown,accountswitcher_header_accountlist,accountswitcher_newpm_messagebit';
$templatelist .= ',accountswitcher_newpm_message,accountswitcher_footer,accountswitcher_post_userbit';
$templatelist .= ',accountswitcher_post,accountswitcher_sec_accountsbit,accountswitcher_shared_accountsbit';
$templatelist .= ',accountswitcher_sidebar_userbit,accountswitcher_sidebar,accountswitcher_avatar';

if (my_strpos($_SERVER['PHP_SELF'], 'usercp.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_usercp_nav,accountswitcher_usercp_options';
    $templatelist .= ',accountswitcher_usercp_attached_userbit,accountswitcher_usercp_attached_users';
    $templatelist .= ',accountswitcher_usercp,accountswitcher_usercp_unshare,accountswitcher_usercp_attached_detach';
    $templatelist .= ',accountswitcher_usercp_free_attach,accountswitcher_usercp_shareuser';
    $templatelist .= ',accountswitcher_usercp_master_attach,accountswitcher_usercp_away';
    $templatelist .= ',accountswitcher_usercp_sec_account,accountswitcher_usercp_privacy_master';
    $templatelist .= ',accountswitcher_usercp_privacy,accountswitcher_usercp_buddyshare';
    $templatelist .= ',accountswitcher_usercp_registered_attach';
}
if (my_strpos($_SERVER['PHP_SELF'], 'private.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_usercp_nav';
}
if (my_strpos($_SERVER['PHP_SELF'], 'member.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_profile_switch,accountswitcher_profile_link,accountswitcher_profile';
    $templatelist .= 'accountswitcher_attached_postcount';
}
if (my_strpos($_SERVER['PHP_SELF'], 'showthread.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_postbit_switch,accountswitcher_postbit_link,accountswitcher_postbit';
    $templatelist .= ',accountswitcher_author_button,accountswitcher_author_button_admin,';
    $templatelist .= ',accountswitcher_author_button_attached,accountswitcher_author_userbit';
    $templatelist .= ',accountswitcher_author_selfbit,accountswitcher_author_admin,accountswitcher_author_change';
    $templatelist .= ',accountswitcher_attached_postcount';
}
if (my_strpos($_SERVER['PHP_SELF'], 'editpost.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_author_userbit,accountswitcher_author_selfbit,accountswitcher_author_admin';
    $templatelist .= ',accountswitcher_author_change';
}
if (my_strpos($_SERVER['PHP_SELF'], 'memberlist.php') || my_strpos($_SERVER['PHP_SELF'], 'showteam.php')) {
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'accountswitcher_memberlist_switch,accountswitcher_memberlist_link';
    $templatelist .= ',accountswitcher_memberlist,accountswitcher_memberlist_shared,accountswitcher_attached_postcount';
}
