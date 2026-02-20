<?php
/**
 * Enhanced Account Switcher for MyBB 1.8
 * Copyright (c) 2012-2025 doylecc
 * http://doylecc.altervista.org
 *
 * Based on the Plugin:
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

define('KILL_GLOBALS', 1);
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'accountlist.php');
define('EAS_PROFILEFIELD', 1);
//define("NO_ONLINE", 1); // Remove from online list

$templatelist = 'accountswitcher_accountlist,accountswitcher_accountlist_master,accountswitcher_accountlist_attached';
$templatelist .= ',accountswitcher_accountlist_shared,accountswitcher_accountlist_endbit,accountswitcher_profilefield';
$templatelist .= ',accountswitcher_avatar,accountswitcher_profilefield_head,accountswitcher_profilefield_attached';
$templatelist .= ',accountswitcher_accountlist_card,accountswitcher_accountlist_master_card';
$templatelist .= ',accountswitcher_accountlist_attached_card,accountswitcher_accountlist_shared_card';
$templatelist .= ',accountswitcher_accountlist_endbit_card,accountswitcher_profilefield_card, multipage';
$templatelist .= ',accountswitcher_profilefield_attached_card,accountswitcher_accountlist_hidden_card';
$templatelist .= ',accountswitcher_accountlist_hidden,multipage_page_current, multipage_page, multipage_nextpage';

require_once './global.php';

// Get the permission to view the list, super admins are always allowed
if (isset($mybb->settings['aj_groupperm'])
    && $mybb->settings['aj_groupperm'] != -1
    && !is_super_admin($mybb->user['uid'])
) {
    if (!is_member($mybb->settings['aj_groupperm']) || $mybb->settings['aj_groupperm'] == '') {
        error_no_permission();
    }
}

// Redirect back if accountlist disabled
if (!isset($mybb->settings['aj_list']) || $mybb->settings['aj_list'] != 1) {
    redirect("index.php", $lang->aj_list_disabled);
}

// Load language file
$lang->load('accountswitcher');

// Add breadcrumb navigation
add_breadcrumb($lang->aj_accountlist);

// Declare variables
$masters = $masters_uid = $masters_ad = $masters_smod = $masters_mod = array();
$masters_reg = $masters_share = $masters_shared = $masters_attached = array();
$count = 0;
$accountlist = $accountlist_masterbit = $masterlink = $attachedlink = $profile_head = $colspan = '';
$profilefield_attached = $profile_field = $profile_name = $viewableby = $as_accountlist_hidden = '';
$colspan_head = 'colspan="2"';
$master_width = 'width="50%"';
$tb_row = '</tr>';

// Incoming results per page?
$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
if (isset($mybb->input['perpage']) && $mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 50) {
    $per_page = $mybb->input['perpage'];
} else {
    $per_page = $mybb->input['perpage'] = 5;
}

// Page
$page = $mybb->get_input('page', MyBB::INPUT_INT);
if (isset($page) && $page > 0) {
    $start = ($page - 1) * $per_page;
} else {
    $start = 0;
    $page = 1;
}

// If profile field enabled, change colspans and get user fields
if (isset($mybb->settings['aj_profilefield']) && $mybb->settings['aj_profilefield'] == 1
    && isset($mybb->settings['aj_profilefield_id']) && (int)$mybb->settings['aj_profilefield_id'] > 0) {
    $colspan_head = 'colspan="4"';
    $colspan = 'colspan="2"';
    $tb_row = '';
}

// Display accounts as cards
$cardbreak = '&nbsp;&nbsp;';
if (isset($mybb->settings['aj_accountlist_cards']) && $mybb->settings['aj_accountlist_cards'] == 1) {
    $cardbreak = '<br />';
}

// Load account data from cache
$accounts = $eas->accountswitcher_cache;

if (is_array($accounts)) {
    // Find all master accounts
    foreach ($accounts as $key => $account) {
        $masters_uid[] = $account['as_uid'];
    }

    $masters_uid = array_unique($masters_uid);
    $masters_uid = array_values($masters_uid);

    // Sort Master Accounts by usergroup: 1. Admins, 2. Super-Mods, 3. Mods, 4. Other
    if (isset($mybb->settings['aj_sortgroup']) && $mybb->settings['aj_sortgroup'] == 1) {
        foreach ($masters_uid as $master_uid) {
            if (is_member(4, $master_uid) && $master_uid != 0) {
                $masters_ad[] = $master_uid;
            } elseif (is_member(3, $master_uid) && $master_uid != 0) {
                $masters_smod[] = $master_uid;
            } elseif (is_member(6, $master_uid) && $master_uid != 0) {
                $masters_mod[] = $master_uid;
            } elseif ($master_uid != 0) {
                $masters_reg[] = $master_uid;
            } else {
                $masters_share[] = $master_uid;
            }
        }
    } else {
        foreach ($masters_uid as $master_uid) {
            if ($master_uid != 0) {
                $masters_attached[] = $master_uid;
            } else {
                $masters_shared[] = $master_uid;
            }
        }
    }

    // Sort master accounts by username
    if (isset($mybb->settings['aj_sortuser']) && $mybb->settings['aj_sortuser'] == 'uname') {
        $m_ad_username = $m_smod_username = $m_mod_username = $m_username = array();
        $m_ad_uid = $m_smod_uid = $m_mod_uid = $m_uid = array();
        // Sort by group and username
        if (isset($mybb->settings['aj_sortgroup']) && $mybb->settings['aj_sortgroup'] == 1) {
            if (is_array($masters_ad)) {
                foreach ($masters_ad as $key => $master_ad) {
                    $master_admin = get_user($master_ad);
                    $m_ad_uid[$key] = $master_admin['uid'];
                    $m_ad_username[$key] = strtolower($master_admin['username']);
                }
                array_multisort($m_ad_username, SORT_ASC, $m_ad_uid, SORT_ASC, $masters_ad);
            }
            if (is_array($masters_smod)) {
                foreach ($masters_smod as $key => $master_smod) {
                    $master_super = get_user($master_smod);
                    $m_smod_uid[$key] = $master_super['uid'];
                    $m_smod_username[$key] = strtolower($master_super['username']);
                }
                array_multisort($m_smod_username, SORT_ASC, $m_smod_uid, SORT_ASC, $masters_smod);
            }
            if (is_array($masters_mod)) {
                foreach ($masters_mod as $key => $master_mod) {
                    $master_md = get_user($master_mod);
                    $m_mod_uid[$key] = $master_md['uid'];
                    $m_mod_username[$key] = strtolower($master_md['username']);
                }
                array_multisort($m_mod_username, SORT_ASC, $m_mod_uid, SORT_ASC, $masters_mod);
            }
            if (is_array($masters_reg)) {
                foreach ($masters_reg as $key => $master_user) {
                    $master = get_user($master_user);
                    $m_uid[$key] = $master['uid'];
                    $m_username[$key] = strtolower($master['username']);
                }
                array_multisort($m_username, SORT_ASC, $m_uid, SORT_ASC, $masters_reg);
            }
        } else {
            // Sort all by username
            if (is_array($masters_attached)) {
                foreach ($masters_attached as $key => $master_uid) {
                    $master = get_user($master_uid);
                    $m_uid[$key] = $master['uid'];
                    $m_username[$key] = strtolower($master['username']);
                }
                array_multisort($m_username, SORT_ASC, $m_uid, SORT_ASC, $masters_attached);
            }
        }
    }

    // Sort Master Accounts by usergroup: 1. Admins, 2. Super-Mods, 3. Mods, 4. Other
    if (isset($mybb->settings['aj_sortgroup']) && $mybb->settings['aj_sortgroup'] == 1) {
        $masters = array_merge($masters_ad, $masters_smod, $masters_mod, $masters_reg, $masters_share);
    } else {
        $masters = array_merge($masters_attached, $masters_shared);
    }

    // Count all master accounts
    $num_masters = count($masters);
    // Show only number of master acounts per page
    $masters = array_slice($masters, $start, $per_page);

    if (is_array($masters)) {
        foreach ($masters as $master_acc) {
            $master = get_user($master_acc);
            if (!empty($master['uid'])) {
                $profilefield = '&nbsp;';
                $hidden = 0;
                // Hide users with privacy setting enabled
                if (($mybb->usergroup['cancp'] != 1
                    && $mybb->user['uid'] != $master['uid']
                    && $mybb->settings['aj_privacy'] == 1
                    && $master['as_privacy'] == 1)
                    && (($mybb->user['as_uid'] > 0 && $mybb->user['as_uid'] != $master['uid'])
                    || ($mybb->user['as_uid'] == 0 && $mybb->user['uid'] != $master['as_uid']))
                ) {
                    $masterAvatar = $eas->attached_avatar(
                        $mybb->settings['default_avatar'],
                        $mybb->settings['useravatardims']
                    );
                    $masterlink = $lang->aj_hidden_master;
                } else {
                    // Display master account
                    $attachedPostUser = htmlspecialchars_uni($master['username']);
                    $masterAvatar = $eas->attached_avatar($master['avatar'], $master['avatardimensions']);
                    $masterlink = '<span style="font-weight: bold;" title="Master Account">'.
                                build_profile_link(
                                    format_name(
                                        $attachedPostUser,
                                        $master['usergroup'],
                                        $master['displaygroup']
                                    ),
                                    (int)$master['uid']
                                )
                                .'</span>';
                    // Get profile field
                    if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0) {
                        $master_width = 'width="28%"';
                        $profile_field = $eas->get_profilefield($master['uid']);
                    }
                }
                if ($mybb->settings['aj_accountlist_cards'] == 1) {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_master_card'));
                } else {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_master'));
                }
            } else {
                // Display shared account
                if ($account['as_buddyshare'] != 0) {
                    $lang->as_isshared = $lang->as_isshared_buddy;
                }
                if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0) {
                    if ($mybb->settings['aj_accountlist_cards'] == 1) {
                        $profile_field = eval($templates->render('accountswitcher_profilefield_card'));
                    } else {
                        $profilefield = '&nbsp;';
                        $profile_field = eval($templates->render('accountswitcher_profilefield'));
                    }
                }
                if ($mybb->settings['aj_accountlist_cards'] == 1) {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_shared_card'));
                } else {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_shared'));
                }
            }

            // Sort accounts by first, secondary, shared accounts and by uid or username
            $accounts = $eas->sort_attached();

            // Get all attached accounts
            foreach ($accounts as $key => $account) {
                if ($account['as_uid'] == $master_acc) {
                    $profilefield = '&nbsp;';
                    // Hide users with privacy setting enabled
                    if ($mybb->usergroup['cancp'] != 1
                        && $mybb->user['uid'] != $account['uid']
                        && $mybb->settings['aj_privacy'] == 1
                        && $account['as_privacy'] == 1
                    ) {
                        if (($mybb->user['as_uid'] != 0
                            && $mybb->user['as_uid'] != $account['as_uid']
                            && $mybb->user['as_uid'] != $account['uid'])
                            || ($mybb->user['as_uid'] == 0
                            && $mybb->user['uid'] != $account['as_uid'])
                        ) {
                            ++$hidden;
                            continue;
                        }
                    }
                    ++$count;
                    if ($count > 0) {
                        // Display attached account
                        $attachedPostUser = htmlspecialchars_uni($account['username']);
                        if ($mybb->settings['aj_sharestyle'] == 1 && $account['as_share'] != 0) {
                            $attachedbit = eval($templates->render('accountswitcher_shared_accountsbit'));
                        } elseif ($mybb->settings['aj_secstyle'] == 1
                            && $account['as_sec'] != 0
                            && $account['as_share'] == 0
                        ) {
                            $user_sec_reason = htmlspecialchars_uni($account['as_secreason']);
                            $attachedbit = eval($templates->render('accountswitcher_sec_accountsbit'));
                        } else {
                            $attachedbit = format_name(
                                $attachedPostUser,
                                (int)$account['usergroup'],
                                (int)$account['displaygroup']
                            );
                        }
                        $attachedAvatar = $eas->attached_avatar($account['avatar'], $account['avatardimensions']);
                        $attachedlink = build_profile_link($attachedbit, (int)$account['uid']);
                        // Get profile field
                        if ($mybb->settings['aj_profilefield'] == 1 && (int)$mybb->settings['aj_profilefield_id'] > 0) {
                            $profile_field = $eas->get_profilefield($account['uid'], true);
                        }
                        if ($mybb->settings['aj_accountlist_cards'] == 1) {
                            $accountlist_masterbit .= eval(
                                $templates->render('accountswitcher_accountlist_attached_card')
                            );
                        } else {
                            $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_attached'));
                        }
                    }
                }
            }
            // Show number of hidden attached accounts
            if ($hidden > 0) {
                $lang->aj_hidden = $lang->sprintf($lang->aj_hidden, $hidden);
                if ($mybb->settings['aj_accountlist_cards'] == 1) {
                    $as_accountlist_hidden = eval($templates->render('accountswitcher_accountlist_hidden_card'));
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit_card'));
                } else {
                    $as_accountlist_hidden = eval($templates->render('accountswitcher_accountlist_hidden'));
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit'));
                }
            } else {
                $as_accountlist_hidden = '';
                if ($mybb->settings['aj_accountlist_cards'] == 1) {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit_card'));
                } else {
                    $accountlist_masterbit .= eval($templates->render('accountswitcher_accountlist_endbit'));
                }
            }
        }
    }

    // Multipage
    $search_url = htmlspecialchars_uni("accountlist.php?perpage={$mybb->input['perpage']}");
    $multipage = multipage($num_masters, $per_page, $page, $search_url);
}

// Output accountlist
if ($mybb->settings['aj_accountlist_cards'] == 1) {
    $accountlist .= eval($templates->render('accountswitcher_accountlist_card'));
} else {
    $accountlist .= eval($templates->render('accountswitcher_accountlist'));
}

output_page($accountlist);
