<?php

if (!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Direct access not allowed.');
}

global $mybb, $db, $page, $lang, $cache;

if (method_exists($lang, 'load')) {
    $lang->load('maskify', true);
}
$L = $lang;

$page->add_breadcrumb_item($L->maskify_nav_title, 'index.php?module=config-maskify');
$page->output_header($L->maskify_nav_title);

/* -------- helpers -------- */

function maskify_t($key, array $vars = [])
{
    global $lang;
    $s = isset($lang->$key) ? (string)$lang->$key : (string)$key;
    foreach ($vars as $k => $v) {
        $s = str_replace('{'.$k.'}', (string)$v, $s);
    }
    return $s;
}

function maskify_admin_read_setting($name, $fallback = '')
{
    global $mybb;
    return isset($mybb->settings[$name]) ? $mybb->settings[$name] : $fallback;
}

function maskify_admin_update_settings($pairs)
{
    global $db;
    foreach ($pairs as $name => $val) {
        $db->update_query('settings', ['value' => $db->escape_string($val)], "name='".$db->escape_string($name)."'");
    }
    rebuild_settings();
}

function maskify_admin_csv_to_clean_csv($csv, $only_int = false)
{
    $out = [];
    foreach (explode(',', (string)$csv) as $v) {
        $v = trim($v);
        if ($v === '') continue;
        $out[$only_int ? (int)$v : $v] = true;
    }
    return implode(',', array_keys($out));
}

function maskify_admin_get_groups()
{
    global $db;
    $ret = [];
    $q = $db->simple_select('usergroups', 'gid,title', '', ['order_by' => 'title', 'order_dir' => 'asc']);
    while ($r = $db->fetch_array($q)) {
        $ret[] = ['gid' => (int)$r['gid'], 'title' => (string)$r['title']];
    }
    return $ret;
}

function maskify_admin_get_forums()
{
    global $db;
    $ret = [];
    $q = $db->simple_select('forums', 'fid,name', '', ['order_by' => 'disporder', 'order_dir' => 'asc']);
    while ($r = $db->fetch_array($q)) {
        $ret[] = ['fid' => (int)$r['fid'], 'name' => (string)$r['name']];
    }
    return $ret;
}

function maskify_admin_get_profile_fields_from_cache()
{
    global $cache;
    $ret = [];
    if (is_object($cache)) {
        $raw = $cache->read('profilefields');
        if (is_array($raw)) {
            foreach ($raw as $p) {
                if (!isset($p['fid'])) continue;
                $ret[] = ['fid' => (int)$p['fid'], 'name' => (string)$p['name']];
            }
        }
    }
    return $ret;
}

/* -------- tabs -------- */

$sub_tabs = [
    'general' => [
        'title'       => $L->maskify_tab_general,
        'link'        => 'index.php?module=config-maskify&action=general',
        'description' => $L->maskify_tab_general_desc,
    ],
    'access' => [
        'title'       => $L->maskify_tab_access,
        'link'        => 'index.php?module=config-maskify&action=access',
        'description' => $L->maskify_tab_access_desc,
    ],
    'forums' => [
        'title'       => $L->maskify_tab_forums,
        'link'        => 'index.php?module=config-maskify&action=forums',
        'description' => $L->maskify_tab_forums_desc,
    ],
    'fields' => [
        'title'       => $L->maskify_tab_fields,
        'link'        => 'index.php?module=config-maskify&action=fields',
        'description' => $L->maskify_tab_fields_desc,
    ],
    'tags' => [
        'title'       => $L->maskify_tab_tags,
        'link'        => 'index.php?module=config-maskify&action=tags',
        'description' => $L->maskify_tab_tags_desc,
    ],
    'html' => [
        'title'       => $L->maskify_tab_html,
        'link'        => 'index.php?module=config-maskify&action=html',
        'description' => $L->maskify_tab_html_desc,
    ],
];

$action = $mybb->get_input('action');
if (!isset($sub_tabs[$action])) $action = 'general';
$page->output_nav_tabs($sub_tabs, $action);

/* -------- POST handling + validation -------- */

$errors = [];

if ($mybb->request_method === 'post') {
    if ($action === 'general') {
        $denied_policy = $mybb->get_input('maskify_denied_policy');
        $sign_mycode   = (int)$mybb->get_input('maskify_sign_allow_mycode');
        $sign_maxlen   = (int)$mybb->get_input('maskify_sign_maxlen');
        $nick_maxlen   = (int)$mybb->get_input('maskify_nick_maxlen');
        $ava_maxurl    = (int)$mybb->get_input('maskify_avatar_maxurl');
        $ava_domains   = trim($mybb->get_input('maskify_avatar_domains'));

        if (!in_array($denied_policy, ['escape','strip'], true)) {
            $errors[] = $L->err_denied_policy;
        }
        if ($sign_maxlen <= 0 || $sign_maxlen > 100000) {
            $errors[] = $L->err_sign_maxlen;
        }
        if ($nick_maxlen <= 0 || $nick_maxlen > 255) {
            $errors[] = $L->err_nick_maxlen;
        }
        if ($ava_maxurl <= 0 || $ava_maxurl > 4096) {
            $errors[] = $L->err_avatar_maxurl;
        }
        if ($ava_domains !== '') {
            foreach (explode(',', $ava_domains) as $host) {
                $host = trim($host);
                if ($host === '') continue;
                if (preg_match('#[:/\s]#', $host)) {
                    $errors[] = maskify_t('err_invalid_host', ['host' => $host]);
                    break;
                }
            }
            $ava_domains = maskify_admin_csv_to_clean_csv($ava_domains, false);
        }

        if (!$errors) {
            maskify_admin_update_settings([
                'maskify_denied_policy'     => $denied_policy,
                'maskify_sign_allow_mycode' => $sign_mycode ? '1' : '0',
                'maskify_sign_maxlen'       => (string)$sign_maxlen,
                'maskify_nick_maxlen'       => (string)$nick_maxlen,
                'maskify_avatar_maxurl'     => (string)$ava_maxurl,
                'maskify_avatar_domains'    => $ava_domains,
            ]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=general');
        }
    }

    if ($action === 'access') {
        $allowed_csv = trim($mybb->get_input('maskify_allowed_groups'));
        $allow_users = trim($mybb->get_input('maskify_user_allow'));
        $deny_users  = trim($mybb->get_input('maskify_user_deny'));

        $allowed_csv = maskify_admin_csv_to_clean_csv($allowed_csv, true);
        $allow_users = maskify_admin_csv_to_clean_csv($allow_users, true);
        $deny_users  = maskify_admin_csv_to_clean_csv($deny_users, true);

        if (!$errors) {
            maskify_admin_update_settings([
                'maskify_allowed_groups' => $allowed_csv,
                'maskify_user_allow'     => $allow_users,
                'maskify_user_deny'      => $deny_users,
            ]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=access');
        }
    }

    if ($action === 'forums') {
        $global_wl   = trim($mybb->get_input('maskify_forum_whitelist'));
        $preserve    = trim($mybb->get_input('maskify_preserve_forums'));
        $map_json    = trim($mybb->get_input('maskify_group_forums_map'));

        $global_wl = maskify_admin_csv_to_clean_csv($global_wl, true);
        $preserve  = maskify_admin_csv_to_clean_csv($preserve, true);

        if ($map_json === '') $map_json = '{}';
        $obj = @json_decode($map_json, true);
        if (!is_array($obj)) {
            $errors[] = $L->err_forum_map_json;
        } else {
            $norm = [];
            foreach ($obj as $gid => $csv) {
                $gid = (string)(int)$gid;
                $csv = maskify_admin_csv_to_clean_csv((string)$csv, true);
                if ($csv !== '') $norm[$gid] = $csv;
            }
            $map_json = json_encode($norm, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        if (!$errors) {
            maskify_admin_update_settings([
                'maskify_forum_whitelist'  => $global_wl,
                'maskify_preserve_forums'  => $preserve,
                'maskify_group_forums_map' => $map_json,
            ]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=forums');
        }
    }

    if ($action === 'tags') {
        $matrix = trim($mybb->get_input('maskify_group_tag_matrix'));
        if ($matrix === '') $matrix = '{}';
        $obj = @json_decode($matrix, true);
        if (!is_array($obj)) {
            $errors[] = $L->err_matrix_json;
        } else {
            $norm = [];
            foreach ($obj as $k => $arr) {
                if (!is_array($arr)) continue;
                $tags = [];
                foreach ($arr as $t) {
                    $t = trim((string)$t);
                    if ($t !== '') $tags[$t] = true;
                }
                if ($tags) $norm[(string)$k] = array_values(array_keys($tags));
            }
            $matrix = json_encode($norm, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        if (!$errors) {
            maskify_admin_update_settings(['maskify_group_tag_matrix' => $matrix]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=tags');
        }
    }

    if ($action === 'fields') {
        $extra = trim($mybb->get_input('maskify_extra_fields'));
        if ($extra === '') $extra = '[]';
        $arr = @json_decode($extra, true);
        if (!is_array($arr)) {
            $errors[] = $L->err_fields_json;
        } else {
            $norm = [];
            foreach ($arr as $item) {
                if (!is_array($item)) continue;
                $code = trim((string)($item['code'] ?? ''));
                if ($code === '') continue;
                $type = ($item['type'] ?? 'text') === 'html' ? 'html' : 'text';
                $maxl = (int)($item['max_length'] ?? 255);
                $prof = (string)($item['html_profile'] ?? 'default');
                $fid  = (int)($item['replace_fid'] ?? 0);
                if ($fid <= 0) {
                    $errors[] = maskify_t('err_field_replace_required', ['code' => $code]);
                    continue;
                }
                if ($maxl <= 0 || $maxl > 100000) {
                    $errors[] = maskify_t('err_field_maxlen', ['code' => $code]);
                    continue;
                }
                $norm[] = [
                    'code' => $code,
                    'type' => $type,
                    'max_length' => $maxl,
                    'html_profile' => $prof,
                    'replace_fid' => $fid,
                ];
            }
            if (!$errors) $extra = json_encode($norm, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        if (!$errors) {
            maskify_admin_update_settings(['maskify_extra_fields' => $extra]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=fields');
        }
    }

    if ($action === 'html') {
        $wh = trim($mybb->get_input('maskify_html_whitelists'));
        if ($wh === '') $wh = '{}';
        $obj = @json_decode($wh, true);
        if (!is_array($obj)) {
            $errors[] = $L->err_html_json;
        } else {
            $wh = json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        if (!$errors) {
            maskify_admin_update_settings(['maskify_html_whitelists' => $wh]);
            flash_message($L->saved_ok, 'success');
            admin_redirect('index.php?module=config-maskify&action=html');
        }
    }
}

/* -------- errors -------- */

if ($errors) {
    echo '<div class="error"><p><strong>'.$L->saved_err.'</strong></p><ul>';
    foreach ($errors as $e) echo '<li>'.htmlspecialchars_uni($e).'</li>';
    echo '</ul></div>';
}

/* -------- forms -------- */

require_once MYBB_ROOT.'/admin/inc/class_form.php';

if ($action === 'general') {
    $denied_policy = maskify_admin_read_setting('maskify_denied_policy', 'escape');
    $sign_mycode   = (int)maskify_admin_read_setting('maskify_sign_allow_mycode', 1);
    $sign_maxlen   = (int)maskify_admin_read_setting('maskify_sign_maxlen', 1000);
    $nick_maxlen   = (int)maskify_admin_read_setting('maskify_nick_maxlen', 32);
    $ava_maxurl    = (int)maskify_admin_read_setting('maskify_avatar_maxurl', 512);
    $ava_domains   = maskify_admin_read_setting('maskify_avatar_domains', '');

    $form = new Form('index.php?module=config-maskify&action=general', 'post', 'general');

    echo '<div class="float_right" style="margin:6px 0 10px;">
      <input type="search" id="maskify-gen-search" placeholder="'.htmlspecialchars_uni($L->ui_search_placeholder).'" style="padding:6px;min-width:320px">
    </div><div class="clear"></div>';

    $fc = new FormContainer($L->maskify_tab_general);
    $opts = [
        'escape' => $L->opt_escape,
        'strip'  => $L->opt_strip,
    ];
    $fc->output_row($L->lbl_denied_policy, $L->desc_denied_policy, $form->generate_select_box('maskify_denied_policy', $opts, $denied_policy, ['id'=>'mf_den']), 'mf_den');
    $fc->output_row($L->lbl_sign_allow_mycode, $L->desc_sign_allow_mycode, $form->generate_yes_no_radio('maskify_sign_allow_mycode', $sign_mycode, true), 'mf_sign_mycode');
    $fc->output_row($L->lbl_sign_maxlen, $L->desc_sign_maxlen, $form->generate_text_box('maskify_sign_maxlen', $sign_maxlen, ['id'=>'mf_sign_max','style'=>'width:120px']), 'mf_sign_max');
    $fc->output_row($L->lbl_nick_maxlen, $L->desc_nick_maxlen, $form->generate_text_box('maskify_nick_maxlen', $nick_maxlen, ['id'=>'mf_nick_max','style'=>'width:120px']), 'mf_nick_max');
    $fc->output_row($L->lbl_avatar_maxurl, $L->desc_avatar_maxurl, $form->generate_text_box('maskify_avatar_maxurl', $ava_maxurl, ['id'=>'mf_av_max','style'=>'width:120px']), 'mf_av_max');
    $fc->output_row($L->lbl_avatar_domains, $L->desc_avatar_domains, $form->generate_text_box('maskify_avatar_domains', $ava_domains, ['id'=>'mf_av_dom','style'=>'min-width:420px']), 'mf_av_dom');
    $fc->end();

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    echo <<<'JS'
<script>
(() => {
  const q = document.getElementById('maskify-gen-search');
  if (!q) return;
  q.addEventListener('input', () => {
    const needle = (q.value || '').toLowerCase();
    document.querySelectorAll('#general .form_row, #general tr').forEach(r => {
      const txt = (r.textContent || '').toLowerCase();
      r.style.display = needle === '' || txt.includes(needle) ? '' : 'none';
    });
  });
})();
</script>
JS;
}

if ($action === 'access') {
    $groups = maskify_admin_get_groups();
    $allowed_csv = maskify_admin_read_setting('maskify_allowed_groups', '');
    $allow_users = maskify_admin_read_setting('maskify_user_allow', '');
    $deny_users  = maskify_admin_read_setting('maskify_user_deny', '');

    $allowed_set = [];
    foreach (explode(',', $allowed_csv) as $g) {
        $g = (int)trim($g);
        if ($g) $allowed_set[$g] = true;
    }

    $form = new Form('index.php?module=config-maskify&action=access', 'post', 'access');

    echo '<fieldset class="tborder"><legend><strong>'.htmlspecialchars_uni($L->legend_allowed_groups).'</strong></legend>';
    echo '<div style="margin:8px 0 6px;opacity:.85">'.htmlspecialchars_uni($L->desc_allowed_groups).'</div>';
    echo '<div id="maskify-groups" style="display:grid;grid-template-columns: 320px 1fr;gap:8px">';
    foreach ($groups as $g) {
        $chk = isset($allowed_set[$g['gid']]) ? ' checked' : '';
        echo '<label><input type="checkbox" class="mf_g" value="'.(int)$g['gid'].'"'.$chk.'> '.htmlspecialchars_uni($g['title']).' (gid='.(int)$g['gid'].')</label>';
    }
    echo '</div>';
    echo $form->generate_hidden_field('maskify_allowed_groups', $allowed_csv, ['id'=>'mf_allowed_csv']);
    echo '</fieldset>';

    $fc = new FormContainer($L->group_user_lists);
    $fc->output_row($L->lbl_user_allow, $L->desc_user_allow, $form->generate_text_box('maskify_user_allow', $allow_users, ['style'=>'min-width:420px']), 'mf_allow_users');
    $fc->output_row($L->lbl_user_deny,  $L->desc_user_deny,  $form->generate_text_box('maskify_user_deny',  $deny_users,  ['style'=>'min-width:420px']), 'mf_deny_users');
    $fc->end();

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    echo <<<'JS'
<script>
(() => {
  const sync = () => {
    const ids = [];
    document.querySelectorAll('#maskify-groups .mf_g:checked').forEach(cb => {
      ids.push(String(parseInt(cb.value, 10)));
    });
    document.getElementById('mf_allowed_csv').value = ids.join(',');
  };
  document.querySelectorAll('#maskify-groups .mf_g').forEach(cb => cb.addEventListener('change', sync));
})();
</script>
JS;
}

if ($action === 'forums') {
    $global_wl = maskify_admin_read_setting('maskify_forum_whitelist', '');
    $preserve  = maskify_admin_read_setting('maskify_preserve_forums', '');
    $map_json  = maskify_admin_read_setting('maskify_group_forums_map', '{}');

    $groups = maskify_admin_get_groups();
    $forums = maskify_admin_get_forums();

    $allowed_csv = maskify_admin_read_setting('maskify_allowed_groups', '');
    $allowed = [];
    foreach (explode(',', $allowed_csv) as $g) {
        $g = (int)trim($g);
        if ($g) $allowed[$g] = true;
    }
    if ($allowed) {
        $groups = array_values(array_filter($groups, function($g) use ($allowed){ return isset($allowed[$g['gid']]); }));
    }

    $form = new Form('index.php?module=config-maskify&action=forums', 'post', 'forums');

    $fc = new FormContainer($L->legend_global_limits);
    $fc->output_row($L->lbl_forum_whitelist, $L->desc_forum_whitelist, $form->generate_text_box('maskify_forum_whitelist', $global_wl, ['style'=>'min-width:360px']), 'mf_fw');
    $fc->output_row($L->lbl_preserve_forums, $L->desc_preserve_forums, $form->generate_text_box('maskify_preserve_forums', $preserve, ['style'=>'min-width:360px']), 'mf_pres');
    $fc->end();

    echo '<fieldset class="tborder"><legend><strong>'.htmlspecialchars_uni($L->legend_group_scope).'</strong></legend>';
    echo '<div style="opacity:.85;margin:6px 0 10px">'.htmlspecialchars_uni($L->desc_group_scope).'</div>';
    echo '<div id="mf_group_scope" style="display:grid;grid-template-columns: 320px 1fr;gap:8px"></div>';
    echo $form->generate_hidden_field('maskify_group_forums_map', $map_json, ['id'=>'mf_scope_json']);
    echo '<div class="toggle-raw" style="margin:8px 0"><button type="button" class="button" id="mf_scope_toggle">'.htmlspecialchars_uni($L->btn_toggle_json).'</button></div>';
    echo '<textarea id="mf_scope_json_raw" style="display:none;min-height:220px;min-width:100%;font-family:monospace"></textarea>';

    echo '<div style="margin-top:8px;opacity:.8">'.htmlspecialchars_uni($L->hint_forums_prefix).' ';
    if ($forums) {
        $h = [];
        foreach ($forums as $f) $h[] = 'fid'.$f['fid'].' — '.htmlspecialchars_uni($f['name']);
        echo implode(', ', $h);
    } else {
        echo htmlspecialchars_uni($L->text_no_forums);
    }
    echo '</div>';
    echo '</fieldset>';

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $groups_js = json_encode($groups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ph_forum_csv = json_encode($L->ph_forum_csv, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo "<script>\nconst groups = $groups_js;\nconst PH_FORUM_CSV = $ph_forum_csv;\n";
    echo <<<'JS'
(() => {
  const ta   = document.getElementById('mf_scope_json');
  const box  = document.getElementById('mf_group_scope');
  const raw  = document.getElementById('mf_scope_json_raw');
  const btn  = document.getElementById('mf_scope_toggle');

  let data = {};
  try { data = JSON.parse(ta.value || '{}') || {}; } catch { data = {}; }

  const syncRawFromHidden = () => {
    try { raw.value = JSON.stringify(JSON.parse(ta.value || '{}') || {}, null, 2); }
    catch { raw.value = ta.value || '{}'; }
  };

  const save = () => {
    const out = {};
    box.querySelectorAll('input[data-gid]').forEach(inp => {
      const v = (inp.value || '').trim();
      if (v !== '') out[String(parseInt(inp.dataset.gid, 10))] = v;
    });
    ta.value = JSON.stringify(out);
    syncRawFromHidden();
  };

  const render = () => {
    box.innerHTML = '';
    groups.forEach(g => {
      const lbl = document.createElement('div');
      lbl.innerHTML = `<b>${g.title}</b> <span class="smalltext" style="opacity:.8">(gid=${g.gid})</span>`;
      const inp = document.createElement('input');
      inp.type = 'text';
      inp.style.minWidth = '360px';
      inp.placeholder = PH_FORUM_CSV;
      inp.value = typeof data[g.gid] === 'string' ? data[g.gid] : '';
      inp.dataset.gid = g.gid;
      inp.addEventListener('input', save);
      box.append(lbl, inp);
    });
  };

  btn.addEventListener('click', () => {
    if (raw.style.display === 'none') { syncRawFromHidden(); raw.style.display = ''; }
    else { raw.style.display = 'none'; }
  });
  raw.addEventListener('input', () => { ta.value = raw.value; });

  render();
})();
</script>
JS;
}

if ($action === 'tags') {
    $matrix      = maskify_admin_read_setting('maskify_group_tag_matrix', '{}');
    $extra       = maskify_admin_read_setting('maskify_extra_fields', '[]');
    $groups_full = maskify_admin_get_groups();

    $allowed_csv = maskify_admin_read_setting('maskify_allowed_groups', '');
    $allowed = [];
    foreach (explode(',', $allowed_csv) as $g) {
        $g = (int)trim($g);
        if ($g) $allowed[$g] = true;
    }
    $groups = $allowed ? array_values(array_filter($groups_full, function($g) use ($allowed){ return isset($allowed[$g['gid']]); })) : $groups_full;

    $form = new Form('index.php?module=config-maskify&action=tags', 'post', 'tags');

    echo '<fieldset class="tborder"><legend><strong>'.htmlspecialchars_uni($L->legend_matrix).'</strong></legend>';
    echo '<div class="smalltext" style="opacity:.85;margin:6px 0 10px">'.htmlspecialchars_uni($L->desc_matrix).'</div>';
    echo '<div id="mf_matrix_wrap" style="max-height:480px;overflow:auto;border:1px solid #ddd"></div>';
    echo $form->generate_hidden_field('maskify_group_tag_matrix', $matrix, ['id'=>'mf_matrix_json']);
    echo '<div class="toggle-raw" style="margin:8px 0"><button type="button" class="button" id="mf_matrix_toggle">'.htmlspecialchars_uni($L->btn_toggle_json).'</button></div>';
    echo '<textarea id="mf_matrix_json_raw" style="display:none;min-height:220px;min-width:100%;font-family:monospace"></textarea>';
    echo '</fieldset>';

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $groups_js = json_encode($groups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $extra_js  = json_encode($extra,   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_GROUP = json_encode($L->th_group, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_ALLNONE = json_encode($L->th_all_none, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $BTN_ALL  = json_encode($L->btn_all, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $BTN_NONE = json_encode($L->btn_none, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ROW_DEF  = json_encode($L->row_default, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo "<script>\nconst groups = $groups_js;\nconst extraFromSettings = $extra_js;\nconst TXT_GROUP=$TXT_GROUP; const TXT_ALLNONE=$TXT_ALLNONE; const BTN_ALL=$BTN_ALL; const BTN_NONE=$BTN_NONE; const ROW_DEF=$ROW_DEF;\n";
    echo <<<'JS'
(() => {
  const ta   = document.getElementById('mf_matrix_json');
  const wrap = document.getElementById('mf_matrix_wrap');
  const raw  = document.getElementById('mf_matrix_json_raw');
  const btn  = document.getElementById('mf_matrix_toggle');

  let map = {};
  try { map = JSON.parse(ta.value || '{}') || {}; } catch { map = {}; }

  let extras = [];
  try { extras = (JSON.parse(extraFromSettings) || []).map(x => (x.code || '').trim()).filter(Boolean); } catch { extras = []; }

  const tags = Array.from(new Set(['avatar','nick','sign', ...extras]));

  const getRowTags = k => Array.isArray(map[k]) ? map[k].slice() : [];
  const setRowTags = (k, arr) => { if (arr.length > 0) map[k] = arr.slice(); else delete map[k]; save(); };
  const save       = () => { ta.value = JSON.stringify(map); syncRawFromHidden(); };

  const row = (label, key) => {
    const sel = new Set(getRowTags(key));
    const tds = tags.map(t => {
      const on = sel.has(t) ? ' checked' : '';
      return `<td><input type="checkbox" data-g="${key}" data-tag="${t}"${on}></td>`;
    }).join('');
    return `<tr data-key="${key}">
      <td>${label}</td>
      ${tds}
      <td>
        <button type="button" class="button" data-all="1" data-g="${key}">${BTN_ALL}</button>
        <button type="button" class="button" data-none="1" data-g="${key}">${BTN_NONE}</button>
      </td>
    </tr>`;
  };

  const render = () => {
    const thead = ['<table class="general"><thead><tr><th>', TXT_GROUP, '</th>', ...tags.map(t => `<th style="white-space:nowrap">${t}</th>`), '<th>', TXT_ALLNONE, '</th></tr></thead>'].join('');
    const rows = [
      row(ROW_DEF, '*'),
      ...groups.map(g => row(`<b>${g.title}</b> <span class="smalltext" style="opacity:.8">(gid=${g.gid})</span>`, String(g.gid)))
    ].join('');
    wrap.innerHTML = `${thead}<tbody>${rows}</tbody></table>`;

    wrap.querySelectorAll('input[type=checkbox]').forEach(ch => {
      ch.addEventListener('change', () => {
        const g = ch.dataset.g, tag = ch.dataset.tag;
        const set = new Set(getRowTags(g));
        ch.checked ? set.add(tag) : set.delete(tag);
        setRowTags(g, Array.from(set));
      });
    });
    wrap.querySelectorAll('button[data-all]').forEach(b => {
      b.addEventListener('click', () => { setRowTags(b.dataset.g, tags.slice()); render(); });
    });
    wrap.querySelectorAll('button[data-none]').forEach(b => {
      b.addEventListener('click', () => { setRowTags(b.dataset.g, []); render(); });
    });
  };

  const syncRawFromHidden = () => {
    try { raw.value = JSON.stringify(JSON.parse(ta.value || '{}') || {}, null, 2); }
    catch { raw.value = ta.value || '{}'; }
  };

  btn.addEventListener('click', () => {
    if (raw.style.display === 'none') { syncRawFromHidden(); raw.style.display = ''; }
    else { raw.style.display = 'none'; }
  });
  raw.addEventListener('input', () => { ta.value = raw.value; });

  render();
})();
</script>
JS;
}

if ($action === 'fields') {
    $extra    = maskify_admin_read_setting('maskify_extra_fields', '[]');
    $pfields  = maskify_admin_get_profile_fields_from_cache();
    $form     = new Form('index.php?module=config-maskify&action=fields', 'post', 'fields');

    echo '<fieldset class="tborder"><legend><strong>'.htmlspecialchars_uni($L->legend_fields_builder).'</strong></legend>';
    echo '<div class="smalltext" style="opacity:.85;margin:6px 0 10px">'.htmlspecialchars_uni($L->desc_fields_builder).'</div>';
    echo '<div id="mf_fields_box"></div>';
    echo $form->generate_hidden_field('maskify_extra_fields', $extra, ['id'=>'mf_fields_json']);
    echo '<div class="toggle-raw" style="margin:8px 0"><button type="button" class="button" id="mf_fields_toggle">'.htmlspecialchars_uni($L->btn_toggle_json).'</button></div>';
    echo '<textarea id="mf_fields_json_raw" style="display:none;min-height:220px;min-width:100%;font-family:monospace"></textarea>';
    echo '</fieldset>';

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $pf_js = json_encode($pfields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_SEL_FIELD = json_encode($L->option_select_field, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_DEL       = json_encode($L->btn_delete, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_ADD_FIELD = json_encode($L->btn_add_field, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_AVAIL     = json_encode($L->available_fields_prefix, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TXT_NO_FIELDS = json_encode($L->no_active_fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $PH_CODE       = json_encode($L->ph_code, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $TITLE_REPL    = json_encode($L->title_replace_field, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo "<script>\nconst PF = $pf_js;\nconst TXT_SEL_FIELD=$TXT_SEL_FIELD; const TXT_DEL=$TXT_DEL; const TXT_ADD_FIELD=$TXT_ADD_FIELD; const TXT_AVAIL=$TXT_AVAIL; const TXT_NO_FIELDS=$TXT_NO_FIELDS; const PH_CODE=$PH_CODE; const TITLE_REPL=$TITLE_REPL;\n";
    echo <<<'JS'
(() => {
  const ta   = document.getElementById('mf_fields_json');
  const box  = document.getElementById('mf_fields_box');
  const raw  = document.getElementById('mf_fields_json_raw');
  const btn  = document.getElementById('mf_fields_toggle');

  let data  = [];
  try { data = JSON.parse(ta.value || '[]') || []; } catch { data = []; }

  const escapeHtml = s =>
    String(s).replace(/[&<>"']/g, c => (
      c === '&' ? '&amp;'
      : c === '<' ? '&lt;'
      : c === '>' ? '&gt;'
      : c === '"' ? '&quot;'
      : '&#39;'
    ));

  const fidOptions = sel => {
    const opts = [`<option value="0">${TXT_SEL_FIELD}</option>`];
    PF.forEach(p => {
      const selAttr = String(sel) === String(p.fid) ? ' selected' : '';
      opts.push(`<option value="${p.fid}"${selAttr}>fid${p.fid} — ${escapeHtml(p.name)}</option>`);
    });
    return opts.join('');
  };

  const rowTpl = (item, idx) => `
    <div class="row" data-i="${idx}">
      <input type="text" data-k="code" size="12" placeholder="${PH_CODE}" value="${escapeHtml(item.code || '')}"> 
      <select data-k="type">
        <option value="text"${item.type === 'html' ? '' : ' selected'}>text</option>
        <option value="html"${item.type === 'html' ? ' selected' : ''}>html</option>
      </select>
      <input type="number" data-k="max_length" style="width:120px" min="1" step="1" value="${parseInt(item.max_length || 255)}">
      <input type="text" data-k="html_profile" size="12" placeholder="html_profile" value="${escapeHtml(item.html_profile || 'default')}">
      <select data-k="replace_fid" style="min-width:280px" title="${TITLE_REPL}">${fidOptions(item.replace_fid || 0)}</select>
      <button type="button" class="button small_button" data-del="1">${TXT_DEL}</button>
    </div>
  `;

  const save = () => {
    const out = [];
    data.forEach(x => {
      const code = String(x.code || '').trim();
      if (!code) return;
      out.push({
        code,
        type: x.type === 'html' ? 'html' : 'text',
        max_length: parseInt(x.max_length || 255) || 255,
        html_profile: String(x.html_profile || 'default') || 'default',
        replace_fid: parseInt(x.replace_fid || 0) || 0
      });
    });
    ta.value = JSON.stringify(out);
    try { raw.value = JSON.stringify(JSON.parse(ta.value || '[]') || [], null, 2); }
    catch { raw.value = ta.value || '[]'; }
  };

  const render = () => {
    const header = `<div class="smalltext" style="opacity:.8;margin:6px 0">${TXT_AVAIL} ${PF.length ? PF.map(p => 'fid' + p.fid + ' — ' + escapeHtml(p.name)).join(', ') : TXT_NO_FIELDS}</div>`;
    const rows = data.map((item, idx) => rowTpl(item, idx)).join('');
    const add = `<div class="row"><button type="button" class="button" id="mf_add_field">${TXT_ADD_FIELD}</button></div>`;
    box.innerHTML = header + rows + add;

    box.querySelector('#mf_add_field')?.addEventListener('click', () => {
      data.push({code:'',type:'text',max_length:255,html_profile:'default',replace_fid:0});
      render(); save();
    });

    box.querySelectorAll('[data-del]').forEach(b => {
      b.addEventListener('click', () => {
        const i = parseInt(b.closest('.row').dataset.i, 10);
        data.splice(i, 1); render(); save();
      });
    });

    box.querySelectorAll('[data-k]').forEach(inp => {
      inp.addEventListener('change', () => {
        const row = inp.closest('.row'), i = parseInt(row.dataset.i, 10);
        const k = inp.getAttribute('data-k');
        let v = inp.value;
        if (k === 'max_length' || k === 'replace_fid') v = parseInt(v || 0) || 0;
        data[i][k] = v; save();
      });
    });
  };

  btn.addEventListener('click', () => {
    if (raw.style.display === 'none') {
      try { raw.value = JSON.stringify(JSON.parse(ta.value || '[]') || [], null, 2); }
      catch { raw.value = ta.value || '[]'; }
      raw.style.display = '';
    } else {
      raw.style.display = 'none';
    }
  });
  raw.addEventListener('input', () => { ta.value = raw.value; });

  render();
})();
</script>
JS;
}

if ($action === 'html') {
    $presets = [
        'block' => [
            'tags'  => ['div','span','b','i','em','strong','u','small','sup','sub','br','a','img','p','ul','ol','li'],
            'attrs' => ['a'=>['href','rel','target','title'],'img'=>['src','alt','title','width','height'],'*'=>['title','class']],
        ],
        'inline' => [
            'tags'  => ['span','b','i','em','strong','u','small','sup','sub','br','a','img'],
            'attrs' => ['a'=>['href','rel','target','title'],'img'=>['src','alt','title','width','height'],'*'=>['title','class']],
        ],
        'links_only' => [
            'tags'  => ['a','br'],
            'attrs' => ['a'=>['href','rel','target','title']],
        ],
        'rich' => [
            'tags'  => ['div','span','p','ul','ol','li','b','i','em','strong','u','small','sup','sub','br','a','img','h3','h4'],
            'attrs' => ['a'=>['href','rel','target','title'],'img'=>['src','alt','title','width','height'],'*'=>['title','class']],
        ],
    ];
    $wh_json = maskify_admin_read_setting('maskify_html_whitelists', json_encode(['default'=>$presets['block']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

    $form = new Form('index.php?module=config-maskify&action=html', 'post', 'html');

    echo '<fieldset class="tborder"><legend><strong>'.htmlspecialchars_uni($L->legend_html).'</strong></legend>';
    echo '<div class="smalltext" style="opacity:.85;margin:6px 0 10px">'.htmlspecialchars_uni($L->desc_html).'</div>';

    echo '<div style="display:flex;gap:8px;align-items:center;margin:8px 0">
      <label>'.htmlspecialchars_uni($L->lbl_profile_name).': <input type="text" id="mf_html_name" value="default" size="14"></label>
      <label>'.htmlspecialchars_uni($L->lbl_preset).':
        <select id="mf_html_preset">';
    foreach ($presets as $k=>$v) echo '<option value="'.htmlspecialchars_uni($k).'">'.$k.'</option>';
    echo   '</select>
      </label>
      <button type="button" class="button" id="mf_html_apply">'.htmlspecialchars_uni($L->btn_apply_preset).'</button>
    </div>';

    echo '<div class="smalltext" style="opacity:.8;margin:6px 0">'.htmlspecialchars_uni($L->lbl_current_profiles).'</div><div id="mf_html_tags" class="smalltext" style="margin-bottom:8px;opacity:.9"></div>';

    echo '<div class="toggle-raw" style="margin:8px 0"><button type="button" class="button" id="mf_html_toggle">'.htmlspecialchars_uni($L->btn_toggle_json).'</button></div>';
    echo $form->generate_text_area('maskify_html_whitelists', $wh_json, ['id'=>'mf_html_json','style'=>'display:none;min-height:220px;min-width:100%;font-family:monospace']);

    echo '</fieldset>';

    $buttons = [$form->generate_submit_button($L->btn_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();

    $presets_js = json_encode($presets, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo "<script>\nconst presets = $presets_js;\n";
    echo <<<'JS'
<script>
(() => {
  const ta     = document.getElementById('mf_html_json');
  const pills  = document.getElementById('mf_html_tags');
  const btn    = document.getElementById('mf_html_toggle');

  const renderPills = () => {
    let obj = {};
    try { obj = JSON.parse(ta.value || '{}') || {}; } catch { obj = {}; }
    const html = Object.keys(obj).map(k =>
      `<span class="smalltext" style="display:inline-block;border:1px solid #ccc;border-radius:12px;padding:2px 8px;margin:2px 6px 2px 0">${k}</span>`
    ).join('');
    pills.innerHTML = html;
  };

  renderPills();

  document.getElementById('mf_html_apply').addEventListener('click', () => {
    const name = (document.getElementById('mf_html_name').value || 'default').trim() || 'default';
    const p    = document.getElementById('mf_html_preset').value;
    let obj = {};
    try { obj = JSON.parse(ta.value || '{}') || {}; } catch { obj = {}; }
    obj[name] = presets[p];
    ta.value  = JSON.stringify(obj, null, 2);
    renderPills();
  });

  btn.addEventListener('click', () => {
    ta.style.display = (ta.style.display === 'none') ? '' : 'none';
  });
})();
</script>
JS;
}

$page->output_footer();
