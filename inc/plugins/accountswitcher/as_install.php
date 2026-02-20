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


/**
 * Installs the plugin.
 *
 */
function accountswitcher_install()
{
    global $db, $mybb, $cache, $lang;

    // Drop columns to avoid database errors
    if ($db->field_exists("as_uid", "users")) {
        $db->drop_column("users", "as_uid");
    }
    if ($db->field_exists("as_share", "users")) {
        $db->drop_column("users", "as_share");
    }
    if ($db->field_exists("as_shareuid", "users")) {
        $db->drop_column("users", "as_shareuid");
    }
    if ($db->field_exists("as_sec", "users")) {
        $db->drop_column("users", "as_sec");
    }
    if ($db->field_exists("as_secreason", "users")) {
        $db->drop_column("users", "as_secreason");
    }
    if ($db->field_exists("as_privacy", "users")) {
        $db->drop_column("users", "as_privacy");
    }
    if ($db->field_exists("as_buddyshare", "users")) {
        $db->drop_column("users", "as_buddyshare");
    }
    if ($db->field_exists("as_canswitch", "usergroups")) {
        $db->drop_column("usergroups", "as_canswitch");
    }
    if ($db->field_exists("as_limit", "usergroups")) {
        $db->drop_column("usergroups", "as_limit");
    }

    // Add database columns
    if (!$db->field_exists("as_uid", "users")) {
        $db->add_column("users", "as_uid", "INT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_share", "users")) {
        $db->add_column("users", "as_share", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_shareuid", "users")) {
        $db->add_column("users", "as_shareuid", "INT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_sec", "users")) {
        $db->add_column("users", "as_sec", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_secreason", "users")) {
        $db->add_column("users", "as_secreason", "VARCHAR(500) NOT NULL DEFAULT ''");
    }
    if (!$db->field_exists("as_privacy", "users")) {
        $db->add_column("users", "as_privacy", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_buddyshare", "users")) {
        $db->add_column("users", "as_buddyshare", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_canswitch", "usergroups")) {
        $db->add_column("usergroups", "as_canswitch", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_limit", "usergroups")) {
        $db->add_column("usergroups", "as_limit", "SMALLINT NOT NULL DEFAULT '0'");
    }
    $cache->update_usergroups();

    $lang->load('accountswitcher');

    // Add the stylesheet
    accountswitcher_css_add();

    // Add the new templates
    accountswitcher_templates_add();

    // Modify templates
    accountswitcher_revert_template_edits();
    accountswitcher_apply_template_edits();

    // Integrate MyAlerts
    $alertsetting = 0;
    if ($db->table_exists("alert_types")) {
        if (!accountswitcher_alerts_status()) {
            accountswitcher_alerts_integrate();
            $alertsetting = 1;
        }
    }

    /**
     *
     * Settings
     *
     **/

    // Avoid duplicated settings
    $query_setgr = $db->simple_select("settinggroups", "gid", "name='accountswitcher'");
    $ams = $db->fetch_array($query_setgr);
    if (isset($ams['gid'])) {
        $db->delete_query("settinggroups", "gid='".(int)$ams['gid']."'");
        $db->delete_query("settings", "gid='".(int)$ams['gid']."'");
    }
    $query_set = $db->simple_select("settinggroups", "gid", "name='Enhanced Account Switcher'");
    $am = $db->fetch_array($query_set);
    if (isset($am['gid'])) {
        $db->delete_query("settinggroups", "gid='".(int)$am['gid']."'");
        $db->delete_query("settings", "gid='".(int)$am['gid']."'");
    }

    // Add the settings
    $query = $db->simple_select("settinggroups", "COUNT(*) as easrows");
    $rows = $db->fetch_field($query, "easrows");

    // Add settinggroup for global settings
    $account_jumper_group = array(
        "name" => "accountswitcher",
        "title" => $db->escape_string($lang->setting_group_accountswitcher),
        "description" => $db->escape_string($lang->setting_group_accountswitcher_desc),
        "disporder" => $rows+1,
        "isdefault" => 0
    );
    $db->insert_query("settinggroups", $account_jumper_group);

    $query_gid = $db->simple_select("settinggroups", "gid", "name='accountswitcher'");
    $asg = $db->fetch_array($query_gid);
    $gid = $asg['gid'];

    // Add settings for the settinggroup
    $account_jumper_1 = array(
        "name" => "aj_postjump",
        "title" => $db->escape_string($lang->setting_aj_postjump),
        "description" => $db->escape_string($lang->setting_aj_postjump_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 1,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_1);

    $account_jumper_2 = array(
        "name" => "aj_changeauthor",
        "title" => $db->escape_string($lang->setting_aj_changeauthor),
        "description" => $db->escape_string($lang->setting_aj_changeauthor_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 2,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_2);

    $account_jumper_3 = array(
        "name" => "aj_pmnotice",
        "title" => $db->escape_string($lang->setting_aj_pmnotice),
        "description" => $db->escape_string($lang->setting_aj_pmnotice_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 3,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_3);

    $account_jumper_4 = array(
        "name" => "aj_profile",
        "title" => $db->escape_string($lang->setting_aj_profile),
        "description" => $db->escape_string($lang->setting_aj_profile_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 4,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_4);

    $account_jumper_5 = array(
        "name" => "aj_away",
        "title" => $db->escape_string($lang->setting_aj_away),
        "description" => $db->escape_string($lang->setting_aj_away_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 5,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_5);

    $account_jumper_6 = array(
        "name" => "aj_reload",
        "title" => $db->escape_string($lang->setting_aj_reload),
        "description" => $db->escape_string($lang->setting_aj_reload_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 6,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_6);

    $account_jumper_7 = array(
        "name" => "aj_list",
        "title" => $db->escape_string($lang->setting_aj_list),
        "description" => $db->escape_string($lang->setting_aj_list_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 7,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_7);

    $account_jumper_8 = array(
        "name" => "aj_postuser",
        "title" => $db->escape_string($lang->setting_aj_postuser),
        "description" => $db->escape_string($lang->setting_aj_postuser_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 8,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_8);

    $account_jumper_9 = array(
        "name" => "aj_shareuser",
        "title" => $db->escape_string($lang->setting_aj_shareuser),
        "description" => $db->escape_string($lang->setting_aj_shareuser_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 9,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_9);

    $account_jumper_10 = array(
        "name" => "aj_sharestyle",
        "title" => $db->escape_string($lang->setting_aj_sharestyle),
        "description" => $db->escape_string($lang->setting_aj_sharestyle_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 10,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_10);

    $account_jumper_11 = array(
        "name" => "aj_sortuser",
        "title" => $db->escape_string($lang->setting_aj_sortuser),
        "description" => $db->escape_string($lang->setting_aj_sortuser_desc),
        "optionscode" => "select\nuid=User-ID\nuname=Username",
        "value" => "uid",
        "disporder" => 11,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_11);

    $account_jumper_12 = array(
        "name" => "aj_headerdropdown",
        "title" => $db->escape_string($lang->setting_aj_headerdropdown),
        "description" => $db->escape_string($lang->setting_aj_headerdropdown_desc),
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => 12,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_12);

    $account_jumper_13 = array(
        "name" => "aj_admin_changeauthor",
        "title" => $db->escape_string($lang->setting_aj_admin_changeauthor),
        "description" => $db->escape_string($lang->setting_aj_admin_changeauthor_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 13,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_13);

    $account_jumper_14 = array(
        "name" => "aj_admin_changegroup",
        "title" => $db->escape_string($lang->setting_aj_admin_changegroup),
        "description" => $db->escape_string($lang->setting_aj_admin_changegroup_desc),
        "optionscode" => "radio
admin=".$db->escape_string($lang->setting_aj_admin_changegroup_admin)."
supermods=".$db->escape_string($lang->setting_aj_admin_changegroup_supermods)."
mods=".$db->escape_string($lang->setting_aj_admin_changegroup_mods)."",
        "value" => "admin",
        "disporder" => 14,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_14);

    $account_jumper_15 = array(
        "name" => "aj_authorpm",
        "title" => $db->escape_string($lang->setting_aj_authorpm),
        "description" => $db->escape_string($lang->setting_aj_authorpm_desc),
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => 15,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_15);

    $account_jumper_16 = array(
        "name" => "aj_memberlist",
        "title" => $db->escape_string($lang->setting_aj_memberlist),
        "description" => $db->escape_string($lang->setting_aj_memberlist_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 16,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_16);

    $account_jumper_17 = array(
        "name" => "aj_sidebar",
        "title" => $db->escape_string($lang->setting_aj_sidebar),
        "description" => $db->escape_string($lang->setting_aj_sidebar_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 17,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_17);

    $account_jumper_18 = array(
        "name" => "aj_secstyle",
        "title" => $db->escape_string($lang->setting_aj_secstyle),
        "description" => $db->escape_string($lang->setting_aj_secstyle_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 18,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_18);

    $account_jumper_19 = array(
        "name" => "aj_profilefield",
        "title" => $db->escape_string($lang->setting_aj_profilefield),
        "description" => $db->escape_string($lang->setting_aj_profilefield_desc),
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => 19,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_19);

    $account_jumper_20 = array(
        "name" => "aj_profilefield_id",
        "title" => $db->escape_string($lang->setting_aj_profilefield_id),
        "description" => $db->escape_string($lang->setting_aj_profilefield_id_desc),
        "optionscode" => "numeric",
        "value" => "0",
        "disporder" => 20,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_20);

    $account_jumper_21 = array(
        "name" => "aj_sortgroup",
        "title" => $db->escape_string($lang->setting_aj_sortgroup),
        "description" => $db->escape_string($lang->setting_aj_sortgroup_desc),
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => 21,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_21);

    $account_jumper_22 = array(
        "name" => "aj_postcount",
        "title" => $db->escape_string($lang->setting_aj_postcount),
        "description" => $db->escape_string($lang->setting_aj_postcount_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 22,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_22);

    $account_jumper_23 = array(
        "name" => "aj_myalerts",
        "title" => $db->escape_string($lang->setting_aj_myalerts),
        "description" => $db->escape_string($lang->setting_aj_myalerts_desc),
        "optionscode" => "yesno",
        "value" => $alertsetting,
        "disporder" => 23,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_23);

    $account_jumper_24 = array(
        "name" => "aj_privacy",
        "title" => $db->escape_string($lang->setting_aj_privacy),
        "description" => $db->escape_string($lang->setting_aj_privacy_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 24,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_24);

    $account_jumper_25 = array(
        "name" => "aj_emailcheck",
        "title" => $db->escape_string($lang->setting_aj_emailcheck),
        "description" => $db->escape_string($lang->setting_aj_emailcheck_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 25,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_25);

    $account_jumper_26 = array(
        "name" => "aj_tpledit",
        "title" => $db->escape_string($lang->setting_aj_tpledit),
        "description" => $db->escape_string($lang->setting_aj_tpledit_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 26,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_26);

    $account_jumper_27 = array(
        "name" => "aj_groupperm",
        "title" => $db->escape_string($lang->setting_aj_groupperm),
        "description" => $db->escape_string($lang->setting_aj_groupperm_desc),
        "optionscode" => "groupselect",
        "value" => -1,
        "disporder" => 27,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_27);

    $account_jumper_28 = array(
        "name" => "aj_regmailattach",
        "title" => $db->escape_string($lang->setting_aj_regmailattach),
        "description" => $db->escape_string($lang->setting_aj_regmailattach_desc),
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => 28,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_28);

    $account_jumper_29 = array(
        "name" => "aj_accountlist_cards",
        "title" => $db->escape_string($lang->setting_aj_accountlist_cards),
        "description" => $db->escape_string($lang->setting_aj_accountlist_cards_desc),
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => 29,
        "gid" => (int)$gid
        );
    $db->insert_query("settings", $account_jumper_29);

	$account_jumper_30 = array(
		"name" => "aj_forum_perm",
		"title" => $db->escape_string($lang->setting_aj_forum_perm),
		"description" => $db->escape_string($lang->setting_aj_forum_perm_desc),
		"optionscode" => "yesno",
		"value" => 0,
		"disporder" => 30,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_30);

    // Refresh settings.php
    rebuild_settings();
}


/**
 * Checks whether the plugin is installed.
 *
 * @return boolean True if the database fields exist, otherwise false.
 */
function accountswitcher_is_installed()
{
    global $db;

    if ($db->field_exists("as_uid", "users")
        && $db->field_exists("as_canswitch", "usergroups")
        && $db->field_exists("as_limit", "usergroups")
    ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Activates the plugin.
 *
 */
function accountswitcher_activate()
{
    global $mybb, $db, $cache, $templates, $lang, $eas;

    $lang->load('accountswitcher');

    change_admin_permission('user', 'accountswitcher', 1);

    // Add text field here to store the reason for secondary attached accounts
    if (!$db->field_exists("as_secreason", "users")) {
        $db->add_column("users", "as_secreason", "VARCHAR(500) NOT NULL DEFAULT ''");
    }

    // Do we need to upgrade?
    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
    $eas_version = $eas->easversion_cache;
    $up = 1;
    if (is_array($eas_version)
        && isset($eas_version['name'])
        && isset($eas_version['version'])
        && isset($eas_version['release'])
    ) {
        if ($eas_version['name'] == 'accountswitcher'
            && $eas_version['version'] == $eas->version
        ) {
            $up = 0;
        }
    }

    if ($up > 0) {
        // Do all required upgrades
        require_once MYBB_ROOT.'inc/plugins/accountswitcher/as_upgrade.php';
        accountswitcher_upgrade();
    } else {
        // Just activate without upgrading
        $db->delete_query("templategroups", "prefix = 'accountswitcher'");
        $db->delete_query("templates", "title LIKE 'accountswitcher_%' AND sid='-2'");
        // Add the master templates
        accountswitcher_templates_add();

        // Delete stylesheet
        $db->delete_query("themestylesheets", "name = 'accountswitcher.css' AND tid='1'");

        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

        $query = $db->simple_select("themes", "tid");
        while ($theme = $db->fetch_array($query)) {
            update_theme_stylesheet_list($theme['tid'], 0, 1);
        }

        // Check if the stylesheet already exists
        $query_css = $db->simple_select(
            "themestylesheets",
            "*",
            "name = 'accountswitcher.css' AND tid='1' AND stylesheet LIKE '%acclist_card_mast%'"
        );
        $result_css = $db->num_rows($query_css);

        if (!$result_css) {
            accountswitcher_css_add();
        }

        // Build accounts and userfield cache
        $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
        $eas->update_accountswitcher_cache();
        $eas->update_userfields_cache();
    }
}

/**
 * Deactivates the plugin.
 *
 */
function accountswitcher_deactivate()
{
    global $db, $cache, $eas_version;

    change_admin_permission('user', 'accountswitcher', -1);

    // Drop the field for upgrading
    if ($db->field_exists("as_secreason", "users")
        && isset($eas_version['version'])
        && $eas_version['version'] < 2109)
    {
        $db->drop_column("users", "as_secreason");
    }

    // Delete master templates for upgrade
    $db->delete_query("templategroups", "prefix = 'accountswitcher'");
    $db->delete_query("templates", "title LIKE 'accountswitcher_%' AND sid='-2'");

    // Delete deprecated templates
    $db->delete_query("templates", "title = 'as_usercp_nav'");
    $db->delete_query("templates", "title = 'as_usercp'");
    $db->delete_query("templates", "title = 'as_usercp_users'");
    $db->delete_query("templates", "title = 'as_usercp_userbit'");
    $db->delete_query("templates", "title = 'as_usercp_options'");
    $db->delete_query("templates", "title = 'as_header'");
    $db->delete_query("templates", "title = 'as_header_dropdown'");
    $db->delete_query("templates", "title = 'global_pm_switch_alert'");
    $db->delete_query("templates", "title = 'as_acclist_link'");

    // Clear cache
    $cache->update('accountswitcher', false);
    $cache->update('accountswitcher_fields', false);
}

/**
 * Uninstalls the plugin.
 *
 */
function accountswitcher_uninstall()
{
    global $db, $cache;

    // Undo template edits
    accountswitcher_revert_template_edits();

    // Delete the templates and templategroup
    $db->delete_query("templategroups", "prefix = 'accountswitcher'");
    $db->delete_query("templates", "title LIKE 'accountswitcher_%'");

    // Delete stylesheet
    $db->delete_query("themestylesheets", "name = 'accountswitcher.css'");

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    $query = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid'], 0, 1);
    }

    // Delete table columns
    if ($db->field_exists("as_uid", "users")) {
        $db->drop_column("users", "as_uid");
    }
    if ($db->field_exists("as_share", "users")) {
        $db->drop_column("users", "as_share");
    }
    if ($db->field_exists("as_shareuid", "users")) {
        $db->drop_column("users", "as_shareuid");
    }
    if ($db->field_exists("as_sec", "users")) {
        $db->drop_column("users", "as_sec");
    }
    if ($db->field_exists("as_privacy", "users")) {
        $db->drop_column("users", "as_privacy");
    }
    if ($db->field_exists("as_buddyshare", "users")) {
        $db->drop_column("users", "as_buddyshare");
    }
    if ($db->field_exists("as_canswitch", "usergroups")) {
        $db->drop_column("usergroups", "as_canswitch");
    }
    if ($db->field_exists("as_limit", "usergroups")) {
        $db->drop_column("usergroups", "as_limit");
    }
    if ($db->field_exists("as_secreason", "users")) {
        $db->drop_column("users", "as_secreason");
    }

    // Delete settings
    $query_setgr = $db->simple_select("settinggroups", "gid", "name='accountswitcher'");
    $ams = $db->fetch_array($query_setgr);
    if (isset($ams['gid'])) {
        $db->delete_query("settinggroups", "gid='".(int)$ams['gid']."'");
        $db->delete_query("settings", "gid='".(int)$ams['gid']."'");
    }

    $cache->update_usergroups();

    // Delete cache
    if (is_object($cache->handler)) {
        $cache->handler->delete('accountswitcher');
        $cache->handler->delete('accountswitcher_fields');
        $cache->handler->delete('dcc_plugins');
    }
    // Delete database cache
    $db->delete_query("datacache", "title='accountswitcher'");
    $db->delete_query("datacache", "title='accountswitcher_fields'");
    $db->delete_query("datacache", "title='dcc_plugins'");

    // Unregister Alert types
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertTypeManager->deleteByCode('accountswitcher_author');
        $alertTypeManager->deleteByCode('accountswitcher_pm');
    }
}


/**
 * Add the templates.
 *
 */
function accountswitcher_templates_add()
{
    global $mybb, $db, $cache, $templates, $lang;

    $lang->load('accountswitcher');

    // Add templategroup
    $templategrouparray = array(
        "prefix" => "accountswitcher",
        "title"  => $db->escape_string($lang->group_accountswitcher),
        "isdefault" => 1
    );
    $db->insert_query("templategroups", $templategrouparray);

/*
 * Load Templates from XML file
 *
 */
        $templatefile = MYBB_ROOT.'/inc/plugins/accountswitcher/templates.xml';

        if (file_exists($templatefile)) {
            // Load the content
            $contents = @file_get_contents($templatefile);
            if (version_compare(phpversion(), '8.0.0', '<')) {
                require_once MYBB_ROOT."inc/class_xml.php";
                $parser = new XMLParser($contents);
            } else {
                $parser = create_xml_parser($contents);
            }
            $tree = $parser->get_tree();
        } else {
            flash_message(
                "Could not find Account Switcher template file: inc/plugins/accountswitcher/templates.xml!",
                'error'
            );
            admin_redirect("index.php?module=config-plugins");
        }
        // We have XML content
        if ($tree !== false && is_array($tree) && is_array($tree['accountswitcher'])) {
            foreach ($tree['accountswitcher']['template'] as $tpl) {
                if (is_array($tpl['title'])
                && is_array($tpl['tpl'])
                ) {
                    // Fill the template
                    $as_template = array(
                    "title" => $db->escape_string($tpl['title']['value']),
                    "template" => $db->escape_string($tpl['tpl']['value']),
                    "sid"       => -2,
                    "version"   => $mybb->version_code,
                    "dateline"  => TIME_NOW
                    );
                    $db->insert_query("templates", $as_template);
                }
            }
        } else {
            flash_message("Account Switcher templates could not be installed!", 'error');
            admin_redirect("index.php?module=config-plugins");
        }
}

/**
 * MyAlerts 2.0 integration.
 *
 */
function accountswitcher_alerts_integrate()
{
    global $db, $cache, $lang;

    if (!isset($lang->aj_name)) {
        $lang->load('accountswitcher');
    }
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode("accountswitcher_author");
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);
        $alertTypeManager->add($alertType);

        $alertType->setCode("accountswitcher_pm");
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);
        $alertTypeManager->add($alertType);
    }
}

/**
 * Checks for MyAlerts plugin.
 *
 */
function accountswitcher_alerts_status()
{
    global $db;

    if ($db->table_exists("alert_types")) {
        $query = $db->simple_select("alert_types", "*", "code='accountswitcher_author'");
        if ($db->num_rows($query) == 1) {
            return true;
        }
    }
    return false;
}

/**
 * Add the styles.
 *
 */
function accountswitcher_css_add()
{
    global $db;

    $as_styles = array(
        "name" => "accountswitcher.css",
        "tid" => 1,
        "attachedto" => "",
        "stylesheet" => "#accountswitcher_header {
    position: relative;
    float: right;
    cursor: pointer;
}

#accountswitcher_header_popup {
   padding: 0 30px 0 15px;
   position: absolute;
   top:0;
   display: none;
   opacity: 1;
}

#accountswitcher_header_popup ul {
   position: absolute;
   left: 0;
   padding-left: 10px;
   padding-right: 20px;
   min-width: 80px;
   margin-top:17px;
   line-height: 120%;
   border-bottom-right-radius: 10px;
   border-bottom-left-radius: 15px;
   white-space: pre-line;
}

[id*=\"profile_switch_\"] > img {
    height: 32px;
    width: auto;
}

[id*=\"profile_link_\"] {
    list-style-type: none;
}

[id*=\"profile_link_\"] > a > img {
    height: 32px;
    width: auto;
}

.acclist_outer {
    text-align: center;
    float: left;
    border: 1px solid;
    border-color: #000;
    width: 100%;
}

.acclist_att {
    padding-left: 20px;
}

.acclist_att > img {
    height: 44px;
    width: auto;
}

.acclist_mast {
    padding-left: 30px;
}

.acclist_mast > img {
    height: 44px;
    width: auto;
}

.acclist_card_mast {
    float:left;
    width: 100%;
    padding-right: 0;
    border-bottom: 1px solid #000;
}

.acclist_card_mast > img {
    height: 44px;
    width: auto;
}

.acclist_card_att {
    float:left;
    width: 250px;
    text-align: center;
}

.acclist_card_hidden {
    text-align: center;
    padding-top: 30px;
    float: left;
    width: 250px;
    border: none;
}

.acclist_card_att > img {
    height: 100px;
    width: auto;
}

.profile_card_mast {
    padding: 5px;
    background: none;
    border: none;
}

.profile_card_att {
    padding: 5px;
    background: none;
    border: none;
}

.as_head_drop {
    list-style-type: none;
    white-space: nowrap;
}

.as_head_drop > img {
    height: 22px;
    width: auto;
}

.as_head_userbit {
    list-style: none;
}

.as_head_userbit > img {
    height: 22px;
    width: auto;
}

.as_header {
    border-top: 1px solid;
    margin-top: 7px!important;
    padding-top: 7px!important;
    clear: both;
    min-width: 400px;
}

.as_header > img {
    height: 22px;
    width: auto;
}

.as_header > li > img {
    height: 22px;
    width: auto;
}

.as_side_user {
    list-style-type: none;
    white-space: nowrap;
    height: 24px;
}

.as_menu-arrow {
    position: fixed;
    left: 0;
    top: 0;
    z-index: 10;
    background: rgba(255, 255, 255, 0.2);
    width: 10px;
    height: 100%;
    border: 5px solid transparent;
    -webkit-transition: opacity .4s ease .4s;
    -moz-transition: opacity .4s ease .4s;
    -ms-transition: opacity .4s ease .4s;
    -o-transition: opacity .4s ease .4s;
    transition: opacity .4s ease .4s;
}

.as_sidenav {
    height: 100%;
    width: 0;
    position: fixed;
    z-index: 10000;
    top: 0;
    left: 0;
    background-color: #111;
    overflow-x: hidden;
    transition: 0.5s;
    padding-top: 60px;
}

.as_sidenav a {
    padding: 8px 8px 8px 20px;
    text-decoration: none;
    font-size: 15px;
    color: #ccc;
    display: inline-block;
    transition: 0.3s;
}

.as_sidenav a:hover {
    color: #f1f1f1;
}

.as_sidenav .closebtn {
    position: absolute;
    top: 0;
    right: 25px;
    font-size: 36px;
    margin-left: 50px;
}

 .as_sidenav ul {
    margin-top: 40px;
}

 .as_sidenav ul li {
    display: inline-block;
    width: 100%;
    list-style: none;
    font-size: 15px;
    text-align: left;
    padding: 3px 0px;
}

 .as_sidenav ul li img {
    height: 22px;
    width: auto;
}

 .as_sidenav ul li:before {
    content: \"\";
    margin-right: 5px;
    color: rgba(255, 255, 255, 0.2);
}

@media screen and (max-height: 450px) {
  .as_sidenav {padding-top: 15px;}
  .as_sidenav a {font-size: 15px;}
}         ",
        "cachefile" => $db->escape_string(str_replace("/", "", "accountswitcher.css")),
        "lastmodified" => TIME_NOW
    );

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    $sid = $db->insert_query("themestylesheets", $as_styles);
    $db->update_query(
        "themestylesheets",
        array(
            "cachefile" => "accountswitcher.css"
        ),
        "sid = '".(int)$sid."'",
        1
    );

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
        cache_stylesheet($theme['tid'], "accountswitcher.css", $as_styles['stylesheet']);
        update_theme_stylesheet_list($theme['tid'], 0, 1);
    }
}

// Hook for removing all Enhanced Account Switcher files on uninstall
$plugins->add_hook("admin_config_plugins_deactivate_commit", "accountswitcher_remove_files");

/**
 * Try to remove all Enhanced Account Switcher files on uninstall
 *
 */
function accountswitcher_remove_files()
{
    global $mybb, $lang;

    // Only use confirm action for this plugin
    if (!isset($mybb->input['plugin']) || $mybb->input['plugin'] != 'accountswitcher') {
        return;
    }

    // Skip if the plugin gets deactivated without uninstalling
    if (!isset($mybb->input['uninstall']) || $mybb->input['uninstall'] != 1) {
        return;
    }

    // Load language strings
    if (!isset($lang->aj_delete_confirm)) {
        $lang->load('accountswitcher');
    }

    // Don't delete any files if user clicks "No"
    if (isset($mybb->input['no']) && $mybb->input['no']) {
        return;
    }
    if (!verify_post_check($mybb->input['my_post_key'])) {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect("index.php?module=config-plugins");
    } else {
        if ($mybb->request_method == 'post') {
            /*
             * Try to delete all Accountswitcher language files
             *
             */
            $langdir = MYBB_ROOT."inc/languages/";
            $inst_langs = array();
            if (is_dir($langdir)) {
                $langobjects = @scandir($langdir);
            }
            $undeleted_files = $undeleted_dirs = array();

            // Find all installed language folders
            if (is_array($langobjects)) {
                foreach ($langobjects as $langobject) {
                    if ($langobject != "." && $langobject != "..") {
                        if (filetype($langdir.$langobject) == "dir") {
                            $inst_langs[] = $langobject;
                        }
                    }
                }
                // Delete Enhanced Account Switcher files in all language folders
                foreach ($inst_langs as $inst_lang) {
                    @unlink($langdir.$inst_lang.'/accountswitcher.lang.php');
                    @unlink($langdir.$inst_lang.'/admin/accountswitcher.lang.php');
                    if (file_exists($langdir.$inst_lang.'/accountswitcher.lang.php')) {
                        $undeleted_files[] = 'inc/languages/'.$inst_lang.'/accountswitcher.lang.php';
                    }
                    if (file_exists($langdir.$inst_lang.'/admin/accountswitcher.lang.php')) {
                        $undeleted_files[] = 'inc/languages/'.$inst_lang.'/admin/accountswitcher.lang.php';
                    }
                }
            }

            /*
             * Try to delete the accountswitcher jscript folder and it's contents
             *
             */
            $js_dir = MYBB_ROOT."/jscripts/accountswitcher/";

            if (is_dir($js_dir)) {
                $js_objects = @scandir($js_dir);
                if (is_array($js_objects)) {
                    foreach ($js_objects as $js_object) {
                        if ($js_object != "." && $js_object != "..") {
                            if (filetype($js_dir.$js_object) == "dir") {
                                @accountswitcher_rrmdir($js_dir.$js_object);
                                if (is_dir($js_dir.$js_object)) {
                                    $undeleted_dirs[] = 'jscripts/accountswitcher/'.$js_object.'/';
                                }
                            } else {
                                @unlink($js_dir.$js_object);
                                if (file_exists($js_dir.$js_object)) {
                                    $undeleted_files[] = 'jscripts/accountswitcher/'.$js_object;
                                }
                            }
                        }
                    }
                }
                @rmdir($js_dir);
                if (is_dir($js_dir)) {
                    $undeleted_dirs[] = 'jscripts/accountswitcher/';
                }
            }

            // Delete the image files and folder
            $imagedir = MYBB_ROOT."/images/";
            @unlink($imagedir.'attuser.png');
            @unlink($imagedir.'eas/default_avatar.png');
            @rmdir($imagedir.'eas/');

            // Delete the plugin file
            $plugindir = MYBB_ROOT."inc/plugins/";
            @unlink($plugindir.'accountswitcher.php');
            if (file_exists($plugindir.'accountswitcher.php')) {
                $undeleted_files[] = 'inc/plugins/accountswitcher.php';
            }

            // Delete the account list file
            $rootdir = MYBB_ROOT;
            @unlink($rootdir.'accountlist.php');
            if (file_exists($rootdir.'accountlist.php')) {
                $undeleted_files[] = 'accountlist.php';
            }

            /*
             * Try to delete the accountswitcher plugin folder and its contents
             *
             */
            $eas_dir = MYBB_ROOT."inc/plugins/accountswitcher/";

            if (is_dir($eas_dir)) {
                $eas_objects = @scandir($eas_dir);
                if (is_array($eas_objects)) {
                    foreach ($eas_objects as $eas_object) {
                        if ($eas_object != "." && $eas_object != "..") {
                            if (filetype($eas_dir.$eas_object) == "dir") {
                                @accountswitcher_rrmdir($eas_dir.$eas_object);
                                if (is_dir($eas_dir.$eas_object)) {
                                    $undeleted_dirs[] = 'inc/plugins/accountswitcher/'.$eas_object.'/';
                                }
                            } else {
                                @unlink($eas_dir.$eas_object);
                                if (file_exists($eas_dir.$eas_object)) {
                                    $undeleted_files[] = 'inc/plugins/accountswitcher/'.$eas_object;
                                }
                            }
                        }
                    }
                }
                @rmdir($eas_dir);
                if (is_dir($eas_dir)) {
                    $undeleted_dirs[] = 'inc/plugins/accountswitcher/';
                }
            }

            // Check for undeleted files and folders
            if (file_exists($imagedir.'attuser.png')) {
                $undeleted_files[] = 'images/attuser.png';
            }
            if (file_exists($imagedir.'eas/default_avatar.png')) {
                $undeleted_files[] = 'images/eas/default_avatar.png';
            }
            if (is_dir($imagedir.'eas/')) {
                $undeleted_dirs[] = 'images/eas/';
            }

            $message = $und_files = $und_dirs = '';
            foreach ($undeleted_files as $undeleted_file) {
                $und_files .= '
                    <li>'.$undeleted_file.'</li>';
            }
            foreach ($undeleted_dirs as $undeleted_dir) {
                $und_dirs .= '
                    <li>'.$undeleted_dir.'</li>';
            }

            // Display a list of undeleted files and folders
            if ($und_files != '' || $und_dirs != '') {
                $message .= '<div class="success">'
                .$lang->success_plugin_uninstalled
                .'</div>
                <div class="error">'
                .$lang->aj_undeleted
                .'<ul>'
                .$und_dirs
                .$und_files.'
                </ul>
                </div>';
            }

            if ($message != '') {
                flash_message($message);
                admin_redirect("index.php?module=config-plugins");
            }
        } else {
            // Display Yes and No buttons
            $link = 'index.php?'.htmlspecialchars($_SERVER['QUERY_STRING']);
            $GLOBALS['page']->output_confirm_action($link, $lang->aj_delete_confirm);
            exit;
        }
    }
}

// Delete directory recursively
function accountswitcher_rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = @scandir($dir);
        if (is_array($objects)) {
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object)) {
                        @accountswitcher_rrmdir($dir."/".$object);
                    } else {
                        @unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
        }
        @rmdir($dir);
    }
}
