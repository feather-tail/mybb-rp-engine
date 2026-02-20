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
 * Upgrades the settings, templates, database columns of the plugin
 *
 */
// The upgrade function
function accountswitcher_upgrade()
{
    global $mybb, $db, $cache, $templates, $lang, $eas;

    $lang->load('accountswitcher');

    // Delete stylesheet
    $db->delete_query("themestylesheets", "name = 'accountswitcher.css' AND tid='1'");

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    $query = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid'], 0, 1);
    }

    // Add stylesheet if not exists
    $query_css = $db->simple_select(
        "themestylesheets",
        "*",
        "name = 'accountswitcher.css' AND tid='1' AND stylesheet LIKE '%acclist_card_mast%'"
    );
    $result_css = $db->num_rows($query_css);

    if (!$result_css) {
        accountswitcher_css_add();
    }

    // If we are upgrading... add the new table columns
    if (!$db->field_exists("as_share", "users")) {
        $db->add_column("users", "as_share", "SMALLINT NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists("as_shareuid", "users")) {
        $db->add_column("users", "as_shareuid", "INT NOT NULL DEFAULT '0'");
    }
    // Add new columns for 2.0
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

    // Template edits - search for existing entries
    $template_edits = $db->simple_select(
        "templates",
        "template",
        "title='memberlist_user' AND template LIKE '%attached_accounts%' AND sid <> '-2'"
    );
    if ($db->num_rows($template_edits) == 0) {
        // Ad all entries anew
        accountswitcher_revert_template_edits();
        accountswitcher_apply_template_edits();
    } else {
        // Add the newest entry only
        $tpl_showteam = $db->simple_select(
            "templates",
            "template",
            "title='showteam_usergroup_user' AND template LIKE '%attached_accounts%' AND sid <> '-2'"
        );
        if ($db->num_rows($tpl_showteam) == 0) {
            require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
            find_replace_templatesets(
                'showteam_usergroup_user',
                '#'.preg_quote('{$user[\'username\']}</strong></a>').'#i',
                '{$user[\'username\']}</strong></a>{$user[\'attached_accounts\']}'
            );
        }
    }

    // Integrate MyAlerts
    $alertsetting = 0;
    if ($db->table_exists('alert_types')) {
        $alertsetting = 1;
        if (!accountswitcher_alerts_status()) {
            accountswitcher_alerts_integrate();
        }
    }

    // Update setting group
    $query = $db->simple_select("settinggroups", "gid", "name='Enhanced Account Switcher'");
    $asgid = $db->fetch_array($query);
    if (isset($asgid)) {
        $gid = $asgid['gid'];
    }
    // Rename setting group
    if (!empty($gid)) {
        $groupupdate = array(
            "name" => "accountswitcher",
            "title" => $db->escape_string($lang->setting_group_accountswitcher),
            "description" => $db->escape_string($lang->setting_group_accountswitcher_desc)
        );
        $db->update_query("settinggroups", $groupupdate, "gid='".$gid."'");
    }

    // If we are upgrading...add the new settings
    $query = $db->simple_select("settings", "*", "name='aj_postjump'");
    $result = $db->num_rows($query);

    // Update disporder
    $db->update_query("settings", array("disporder" => 11), "name='aj_sortuser'");
    $db->update_query("settings", array("disporder" => 12), "name='aj_headerdropdown'");
    $db->update_query("settings", array("disporder" => 13), "name='aj_admin_changeauthor'");
    $db->update_query("settings", array("disporder" => 14), "name='aj_admin_changegroup'");
    $db->update_query("settings", array("disporder" => 15), "name='aj_authorpm'");
    $db->update_query("settings", array("disporder" => 16), "name='aj_memberlist'");
    $db->update_query("settings", array("disporder" => 17), "name='aj_sidebar'");
    $db->update_query("settings", array("disporder" => 18), "name='aj_secstyle'");
    $db->update_query("settings", array("disporder" => 19), "name='aj_profilefield'");
    $db->update_query("settings", array("disporder" => 20), "name='aj_profilefield_id'");
    $db->update_query("settings", array("disporder" => 21), "name='aj_sortgroup'");
    $db->update_query("settings", array("disporder" => 22), "name='aj_postcount'");
    $db->update_query("settings", array("disporder" => 23), "name='aj_myalerts'");
    $db->update_query("settings", array("disporder" => 24), "name='aj_privacy'");
    $db->update_query("settings", array("disporder" => 25), "name='aj_emailcheck'");
    $db->update_query("settings", array("disporder" => 26), "name='aj_tpledit'");
    $db->update_query("settings", array("disporder" => 27), "name='aj_groupperm'");
    $db->update_query("settings", array("disporder" => 28), "name='aj_regmailattach'");
    $db->update_query("settings", array("disporder" => 29), "name='aj_accountlist_cards'");

    if (!$result) {
        $query2 = $db->simple_select("settinggroups", "COUNT(*) as easrows");
        $rows = $db->fetch_field($query2, "easrows");

        // Add settinggroup for the settings
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
    }

    // Upgrade to v1.5
    $query_gr = $db->simple_select("settinggroups", "gid", "name='accountswitcher'");
    $eacgid = $db->fetch_array($query_gr);
    if ($eacgid) {
        $gid = $eacgid['gid'];
    }
    $query_reload = $db->simple_select("settings", "*", "name='aj_reload'");
    $result_reload = $db->num_rows($query_reload);

    if (!$result_reload) {
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
    }

    $query_list = $db->simple_select("settings", "*", "name='aj_list'");
    $result_list = $db->num_rows($query_list);

    if (!$result_list) {
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
    }

    $query_share = $db->simple_select("settings", "*", "name='aj_shareuser'");
    $result_share = $db->num_rows($query_share);

    if (!$result_share) {
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
    }

    $query_sort = $db->simple_select("settings", "*", "name='aj_sortuser'");
    $result_sort = $db->num_rows($query_sort);

    if (!$result_sort) {
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
    }

    $query_dropdown = $db->simple_select("settings", "*", "name='aj_headerdropdown'");
    $result_dropdown = $db->num_rows($query_dropdown);

    // Upgrade to v1.6
    if (!$result_dropdown) {
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
    }

    $query_admin_changeauthor = $db->simple_select("settings", "*", "name='aj_admin_changeauthor'");
    $result_admin_changeauthor = $db->num_rows($query_admin_changeauthor);

    // Upgrade to v1.7
    if (!$result_admin_changeauthor) {
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
    }

    // Upgrade to v2.0
    $query_authorpm = $db->simple_select("settings", "*", "name='aj_authorpm'");
    $result_authorpm = $db->num_rows($query_authorpm);

    if (!$result_authorpm) {
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
    }

    $query_memberlist = $db->simple_select("settings", "*", "name='aj_memberlist'");
    $result_memberlist = $db->num_rows($query_memberlist);

    if (!$result_memberlist) {
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
    }

    $query_sidebar = $db->simple_select("settings", "*", "name='aj_sidebar'");
    $result_sidebar = $db->num_rows($query_sidebar);

    if (!$result_sidebar) {
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
    }

    $query_sharestyle = $db->simple_select("settings", "*", "name='aj_sharestyle'");
    $result_sharestyle = $db->num_rows($query_sharestyle);

    if (!$result_sharestyle) {
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
    }

    $query_profilefield = $db->simple_select("settings", "*", "name='aj_profilefield'");
    $result_profilefield = $db->num_rows($query_profilefield);

    if (!$result_profilefield) {
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
    }

    $query_sortgroup = $db->simple_select("settings", "*", "name='aj_sortgroup'");
    $result_sortgroup = $db->num_rows($query_sortgroup);

    if (!$result_sortgroup) {
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
    }

    $query_postcount = $db->simple_select("settings", "*", "name='aj_postcount'");
    $result_postcount = $db->num_rows($query_postcount);

    if (!$result_postcount) {
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
    }

    $query_myalerts = $db->simple_select("settings", "*", "name='aj_myalerts'");
    $result_myalerts = $db->num_rows($query_myalerts);

    if (!$result_myalerts) {
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
    }

    $query_privacy = $db->simple_select("settings", "*", "name='aj_privacy'");
    $result_privacy = $db->num_rows($query_privacy);

    if (!$result_privacy) {
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
    }

    $query_emailcheck = $db->simple_select("settings", "*", "name='aj_emailcheck'");
    $result_emailcheck = $db->num_rows($query_emailcheck);

    if (!$result_emailcheck) {
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
    }

    $query_tpledit = $db->simple_select("settings", "*", "name='aj_tpledit'");
    $result_tpledit = $db->num_rows($query_tpledit);

    if (!$result_tpledit) {
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
    } elseif ($result_tpledit > 1) {
        // If we have duplicate entries, remove them
        $db->delete_query("settings", "name='aj_tpledit'");

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
    }

    $query_groupperm = $db->simple_select("settings", "*", "name='aj_groupperm'");
    $result_groupperm = $db->num_rows($query_groupperm);

    if (!$result_groupperm) {
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
    } elseif ($result_groupperm > 1) {
        // If we have duplicate entries, remove them
        $db->delete_query("settings", "name='aj_groupperm'");

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
    }

    $query_regmail = $db->simple_select("settings", "*", "name='aj_regmailattach'");
    $result_regmail = $db->num_rows($query_regmail);

    if (!$result_regmail) {
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
    }

    $query_al_cards = $db->simple_select("settings", "*", "name='aj_accountlist_cards'");
    $result_al_cards = $db->num_rows($query_al_cards);

    if (!$result_al_cards) {
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
    }
    
    $query_f_perm = $db->simple_select("settings", "*", "name='aj_forum_perm'");
    $result_f_perm = $db->num_rows($query_f_perm);

    if (!$result_f_perm) {
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
    }

    // Refresh settings.php
    rebuild_settings();

    // Delete master templates for upgrade
    $db->delete_query("templategroups", "prefix = 'accountswitcher'");
    $db->delete_query("templates", "title LIKE 'accountswitcher_%' AND sid='-2'");
    // If we are upgrading...add the new templates
    accountswitcher_templates_add();

    // Update the template versions to match the MyBB version
    $updated_version = array(
        "version" => $db->escape_string($mybb->version_code)
    );
    $db->update_query("templates", $updated_version, "title like'accountswitcher_%' AND sid='-2'");

    // If the master stylesheet was updated remove the old stylesheet first
    /*
    $db->delete_query("themestylesheets", "name = 'automedia.css'");

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    $query = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid'], 0, 1);
    }
    */
    // If we are upgrading...check if the stylesheet already exists
    $query_css = $db->simple_select("themestylesheets", "*", "name = 'accountswitcher.css' AND tid='1'");
    $result_css = $db->num_rows($query_css);
    if (!$result_css) {
        accountswitcher_css_add();
    }

    // Build accounts and userfield cache
    require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
    $eas = new AccountSwitcher($mybb, $db, $cache, $templates);
    $eas->update_accountswitcher_cache();
    $eas->update_userfields_cache();
    // After everything is updated - cache the new plugin version
    $eas->update_easversion_cache();
}
