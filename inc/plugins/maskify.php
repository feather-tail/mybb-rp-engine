<?php

if (!defined('IN_MYBB')) {
  die('Прямой доступ к файлу запрещён.');
}

define('MASKIFY_PLUGIN_VER', '1.2.0');

function maskify_info()
{
  global $lang;
  if (method_exists($lang, 'load')) { $lang->load('maskify'); }

  return [
    'name' => $lang->maskify_name,
    'description' => $lang->maskify_description,
    'website' => 'https://github.com/feather-tail',
    'author' => 'Feathertail',
    'authorsite' => 'https://github.com/feather-tail',
    'version' => '1.2.0',
    'compatibility' => '18*',
  ];
}

function maskify_is_installed()
{
  global $db;
  $q = $db->simple_select('settings', 'sid', "name='maskify_enabled'");
  $row = $db->fetch_array($q);
  return !empty($row);
}

function maskify_install()
{
  global $db, $lang;

  if (maskify_is_installed()) {
    rebuild_settings();
    return;
  }

  $title = isset($lang->maskify_name) ? (string)$lang->maskify_name : 'Maskify';
  $desc  = isset($lang->maskify_description) ? (string)$lang->maskify_description : 'Maskify plugin settings';

  $gid = 0;
  $q = $db->simple_select('settinggroups', 'gid', "name='maskify'");
  if ($r = $db->fetch_array($q)) {
    $gid = (int)$r['gid'];
  } else {
    $gid = (int)$db->insert_query('settinggroups', [
      'name'        => 'maskify',
      'title'       => $db->escape_string($title),
      'description' => $db->escape_string($desc),
      'disporder'   => 50,
      'isdefault'   => 0,
    ]);
  }

  $default_html_whitelists = json_encode([
    "default" => [
      "tags"  => ["div","span","b","i","em","strong","u","small","sup","sub","br","a","img"],
      "attrs" => [
        "a"   => ["href","rel","target","title"],
        "img" => ["src","alt","title","width","height"],
        "*"   => ["title","class"]
      ]
    ]
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $settings = [
    [
      'name'        => 'maskify_enabled',
      'title'       => 'Включить Maskify',
      'description' => 'Глобальный переключатель работы плагина.',
      'optionscode' => 'yesno',
      'value'       => '1',
    ],
    [
      'name'        => 'maskify_allowed_groups',
      'title'       => 'Разрешённые группы (CSV gid)',
      'description' => 'Пусто — разрешены все; иначе перечислите ID групп через запятую.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_user_allow',
      'title'       => 'Разрешённые пользователи (CSV uid)',
      'description' => 'Список пользователей, которым разрешено использовать маски независимо от группы.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_user_deny',
      'title'       => 'Запрещённые пользователи (CSV uid)',
      'description' => 'Список пользователей, которым запрещено использовать маски.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_forum_whitelist',
      'title'       => 'Белый список форумов (CSV fid)',
      'description' => 'Если не пусто — маски и зачистка текста применяются только в этих разделах.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_group_forums_map',
      'title'       => 'Разрешённые форумы по группам (JSON)',
      'description' => 'JSON-объект { gid: "fid1,fid2", ... }',
      'optionscode' => 'textarea',
      'value'       => '{}',
    ],
    [
      'name'        => 'maskify_group_tag_matrix',
      'title'       => 'Матрица "Группа × Тег" (JSON)',
      'description' => 'JSON-объект { gid: ["avatar","nick","sign","..."], "*": [...] }',
      'optionscode' => 'textarea',
      'value'       => '{}',
    ],
    [
      'name'        => 'maskify_html_whitelists',
      'title'       => 'HTML-профили белых списков (JSON)',
      'description' => 'Профили допустимых тегов/атрибутов при вставке HTML в extra-поля.',
      'optionscode' => 'textarea',
      'value'       => $default_html_whitelists,
    ],
    [
      'name'        => 'maskify_extra_fields',
      'title'       => 'Конфигурация Extra-полей (JSON)',
      'description' => 'Массив объектов { code, type, max_length, html_profile, mode, replace_fid }',
      'optionscode' => 'textarea',
      'value'       => '[]',
    ],
    [
      'name'        => 'maskify_avatar_exts',
      'title'       => 'Допустимые расширения аватара (CSV)',
      'description' => 'Проверяется по расширению URL: jpg,jpeg,png,gif,webp…',
      'optionscode' => 'text',
      'value'       => 'jpg,jpeg,png,gif,webp',
    ],
    [
      'name'        => 'maskify_avatar_domains',
      'title'       => 'Допустимые хосты для аватара (CSV)',
      'description' => 'Оставьте пустым, чтобы разрешить любые домены. Иначе перечислите хосты без схемы.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_avatar_maxurl',
      'title'       => 'Макс. длина URL аватара',
      'description' => 'Ограничение длины строки URL аватара.',
      'optionscode' => 'text',
      'value'       => '1024',
    ],
    [
      'name'        => 'maskify_nick_maxlen',
      'title'       => 'Макс. длина ника',
      'description' => 'Ограничение длины значения [nick].',
      'optionscode' => 'text',
      'value'       => '64',
    ],
    [
      'name'        => 'maskify_sign_maxlen',
      'title'       => 'Макс. длина подписи',
      'description' => 'Ограничение длины значения [sign] до зачистки/парсинга.',
      'optionscode' => 'text',
      'value'       => '2000',
    ],
    [
      'name'        => 'maskify_sign_allow_mycode',
      'title'       => 'Разрешить MyCode в подписи',
      'description' => 'Позволить использовать MyCode в [sign].',
      'optionscode' => 'yesno',
      'value'       => '1',
    ],
    [
      'name'        => 'maskify_preserve_forums',
      'title'       => 'Форумы с принудительным применением масок (CSV fid)',
      'description' => 'В этих разделах маски применяются принудительно; зачистка включается, если раздел разрешён.',
      'optionscode' => 'text',
      'value'       => '',
    ],
    [
      'name'        => 'maskify_denied_policy',
      'title'       => 'Политика для запрещённых мест',
      'description' => 'Как поступать с содержимым в запрещённых местах (escape/strip).',
      'optionscode' => 'select\nescape=Экранировать\nstrip=Удалять',
      'value'       => 'strip',
    ],
  ];

  $order = 1;
  foreach ($settings as $s) {
    $row = [
      'name'        => $db->escape_string($s['name']),
      'title'       => $db->escape_string($s['title']),
      'description' => $db->escape_string($s['description']),
      'optionscode' => $db->escape_string($s['optionscode']),
      'value'       => $db->escape_string($s['value']),
      'disporder'   => $order++,
      'gid'         => $gid,
    ];
    $q = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s['name'])."'");
    if (!$db->fetch_array($q)) {
      $db->insert_query('settings', $row);
    }
  }

  rebuild_settings();
}

function maskify_uninstall()
{
  global $db;

  $db->delete_query('settings', "name LIKE 'maskify_%'");
  $db->delete_query('settinggroups', "name='maskify'");

  rebuild_settings();
}

function maskify_activate()
{
  if (!maskify_is_installed()) {
    maskify_install();
  } else {
    rebuild_settings();
  }
}
function maskify_deactivate()
{
  rebuild_settings();
}

$plugins->add_hook('postbit', 'maskify_postbit');
$plugins->add_hook('parse_message_start', 'maskify_parse_message_start');
$plugins->add_hook('parse_message_end', 'maskify_parse_message_end');

if (defined('IN_ADMINCP')) {
  $plugins->add_hook('admin_config_menu', 'maskify_admin_config_menu');
  $plugins->add_hook('admin_config_action_handler', 'maskify_admin_config_action_handler');
}

function maskify_admin_config_menu(&$sub_menu)
{
  global $lang;
  if (method_exists($lang, 'load')) { $lang->load('maskify'); }
  $title = isset($lang->maskify_nav_title) ? $lang->maskify_nav_title : 'Maskify (Маски)';
  $sub_menu[] = ['id' => 'maskify', 'title' => $title, 'link' => 'index.php?module=config-maskify'];
}

function maskify_admin_config_action_handler(&$actions)
{
  $actions['maskify'] = ['active' => 'maskify', 'file' => 'maskify.php'];
}

function maskify_without_code_blocks($text)
{
    return preg_replace(
        '#(\[code(?:=[^\]]+)?\][\s\S]*?\[/code\]|\[php\][\s\S]*?\[/php\])#i',
        '',
        $text
    );
}

function maskify_extract_bbcode_value($text, $tag)
{
    $tag = preg_quote($tag, '#');
    if (preg_match('#\['.$tag.'\]([\s\S]*?)\[/'.$tag.'\]#i', $text, $m)) {
        return trim($m[1]);
    }
    return null;
}

function maskify_replace_profilefield_value(&$html, $field_name, $new_html)
{
  if ($html === '' || $field_name === '') return false;
  $pattern =
    '#((?:\s*<!--.*?-->\s*)*\s*(?:<br\s*/?>|\r?\n)\s*' .
    preg_quote($field_name, '#') .
    '\s*:\s*)(.*?)(?=(?:\s*<br\s*/?>|\r?\n|$))#si';
  $replaced = preg_replace($pattern, '$1' . $new_html, $html, 1);
  if ($replaced !== null && $replaced !== $html) {
    $html = $replaced;
    return true;
  }
  return false;
}

function maskify_user_in_additional_groups($user, $allowed_groups_set)
{
  if (empty($user['additionalgroups'])) return false;
  $arr = explode(',', $user['additionalgroups']);
  foreach ($arr as $g) {
    if (isset($allowed_groups_set[(int)$g])) return true;
  }
  return false;
}

function maskify_csv_to_set($csv)
{
  $out = [];
  $csv = trim((string)$csv);
  if ($csv === '') return $out;
  foreach (explode(',', $csv) as $v) {
    $v = trim($v);
    if ($v === '') continue;
    $out[(int)$v] = true;
  }
  return $out;
}

function maskify_get_user_group_ids($user)
{
  $out = [];
  $out[] = (int)($user['usergroup'] ?? 0);
  if (!empty($user['additionalgroups'])) {
    foreach (explode(',', $user['additionalgroups']) as $g) {
      $g = (int)trim($g);
      if ($g) $out[] = $g;
    }
  }
  return array_values(array_unique($out));
}

function maskify_get_group_forums_map()
{
  global $mybb;
  static $cache = null;
  if ($cache !== null) return $cache;
  $cache = [];
  $json = trim((string)$mybb->settings['maskify_group_forums_map']);
  if ($json !== '') {
    $obj = @json_decode($json, true);
    if (is_array($obj)) {
      foreach ($obj as $gid => $csv) {
        $gid = (int)$gid;
        $set = maskify_csv_to_set((string)$csv);
        $cache[$gid] = $set;
      }
    }
  }
  return $cache;
}

function maskify_get_preserve_forums_set()
{
  global $mybb;
  static $set = null;
  if ($set !== null) return $set;
  $set = maskify_csv_to_set($mybb->settings['maskify_preserve_forums'] ?? '');
  return $set;
}

function maskify_can_use_masks($post, $author)
{
  global $mybb;

  if (empty($mybb->settings['maskify_enabled'])) {
    return [false, false];
  }

  $deny = maskify_csv_to_set($mybb->settings['maskify_user_deny']);
  if (isset($deny[$author['uid']])) {
    return [false, false];
  }

  $allow_users = maskify_csv_to_set($mybb->settings['maskify_user_allow']);
  $allowed_groups = maskify_csv_to_set($mybb->settings['maskify_allowed_groups']);
  $forum_wl_global = maskify_csv_to_set($mybb->settings['maskify_forum_whitelist']);

  $global_forum_ok = empty($forum_wl_global) || isset($forum_wl_global[(int)$post['fid']]);

  $user_groups = maskify_get_user_group_ids($author);
  $user_in_allowed = false;
  foreach ($user_groups as $g) {
    if (isset($allowed_groups[$g])) { $user_in_allowed = true; break; }
  }

  $group_ok = $user_in_allowed || isset($allow_users[(int)$author['uid']]);

  $pergroup_ok = false;
  if (isset($allow_users[(int)$author['uid']])) {
    $pergroup_ok = true;
  } elseif ($user_in_allowed) {
    $map = maskify_get_group_forums_map();
    foreach ($user_groups as $g) {
      if (!isset($allowed_groups[$g])) continue;
      if (!isset($map[$g]) || empty($map[$g])) { $pergroup_ok = true; break; }
      if (isset($map[$g][(int)$post['fid']])) { $pergroup_ok = true; break; }
    }
  } else {
    $pergroup_ok = false;
  }

  $has_right = $group_ok && $pergroup_ok && $global_forum_ok;
  return [$has_right, $global_forum_ok];
}

function maskify_group_tag_allowed($gid, $tag)
{
  global $mybb;
  $json = trim($mybb->settings['maskify_group_tag_matrix']);
  if ($json === '') return true;

  $map = @json_decode($json, true);
  if (!is_array($map)) return true;

  $gid_int = (int)$gid;
  $gid_str = (string)$gid_int;

  $tags = null;
  if (isset($map[$gid_int]) && is_array($map[$gid_int])) {
    $tags = $map[$gid_int];
  } elseif (isset($map[$gid_str]) && is_array($map[$gid_str])) {
    $tags = $map[$gid_str];
  } elseif (isset($map['*']) && is_array($map['*'])) {
    $tags = $map['*'];
  }

  if ($tags === null) return false;
  return in_array($tag, $tags, true);
}

function maskify_extract_masks($raw_text, $extra_fields_map)
{
  $raw_text = maskify_without_code_blocks($raw_text);

  $result = ['avatar' => null, 'nick' => null, 'sign' => null, 'extra' => []];

  if (preg_match_all('#\[avatar\]([\s\S]*?)\[/avatar\]#i', $raw_text, $m)) $result['avatar'] = trim(end($m[1]));
  if (preg_match_all('#\[nick\]([\s\S]*?)\[/nick\]#i',   $raw_text, $m)) $result['nick']   = trim(end($m[1]));
  if (preg_match_all('#\[sign\]([\s\S]*?)\[/sign\]#i',   $raw_text, $m)) $result['sign']   = trim(end($m[1]));

  foreach ($extra_fields_map as $code => $conf) {
    $tag = preg_quote($code, '#');
    if (preg_match_all("#\\[$tag\\]([\\s\\S]*?)\\[/$tag\\]#i", $raw_text, $m)) {
      $result['extra'][$code] = trim(end($m[1]));
    }
  }
  return $result;
}

function maskify_sanitize_url_avatar($url)
{
  global $mybb;
  $url = trim($url);
  if (strlen($url) > (int)$mybb->settings['maskify_avatar_maxurl']) return null;
  if (!preg_match('#^https?://#i', $url)) return null;
  $exts = [];
  foreach (explode(',', strtolower($mybb->settings['maskify_avatar_exts'])) as $e) {
    $e = trim($e);
    if ($e) $exts[$e] = true;
  }
  $path = parse_url($url, PHP_URL_PATH);
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext && !isset($exts[$ext])) return null;
  $domains = [];
  foreach (explode(',', strtolower($mybb->settings['maskify_avatar_domains'])) as $d) {
    $d = trim($d); if ($d) $domains[$d] = true;
  }
  if (!empty($domains)) {
    $host = strtolower(parse_url($url, PHP_URL_HOST));
    if (!$host || !isset($domains[$host])) return null;
  }
  return $url;
}

function maskify_sanitize_text($s, $maxlen)
{
  $s = trim($s);
  $s = preg_replace('/[\x00-\x1F\x7F]/', '', $s);
  if (mb_strlen($s, 'UTF-8') > (int)$maxlen) {
    $s = mb_substr($s, 0, (int)$maxlen, 'UTF-8');
  }
  return $s;
}

function maskify_get_html_whitelists()
{
  global $mybb;
  $json = $mybb->settings['maskify_html_whitelists'];
  $profiles = @json_decode($json, true);
  if (!is_array($profiles)) $profiles = [];
  return $profiles;
}

function maskify_sanitize_html($html, $profile_name)
{
  $profiles = maskify_get_html_whitelists();
  $p = isset($profiles[$profile_name]) ? $profiles[$profile_name] : (isset($profiles['default']) ? $profiles['default'] : null);
  if (!$p) return htmlspecialchars_uni($html);

  $allowed_tags = array_flip((array)$p['tags']);
  $allowed_attrs = isset($p['attrs']) && is_array($p['attrs']) ? $p['attrs'] : [];

  return preg_replace_callback(
    '#<\s*/?\s*([a-z0-9]+)([^>]*)>#i',
    function ($m) use ($allowed_tags, $allowed_attrs) {
      $tag = strtolower($m[1]);
      $attrs = $m[2];

      if (!isset($allowed_tags[$tag])) return '';
      if ($m[0][1] == '/') return "</{$tag}>";

      $out_attrs = [];
      if (preg_match_all('#([a-zA-Z0-9:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#', $attrs, $am, PREG_SET_ORDER)) {
        foreach ($am as $a) {
          $name = strtolower($a[1]);
          $val = html_entity_decode(
            isset($a[3]) && $a[3] !== '' ? $a[3] : (isset($a[4]) && $a[4] !== '' ? $a[4] : $a[5]),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
          );
          $allow = false;
          if (isset($allowed_attrs[$tag]) && in_array($name, $allowed_attrs[$tag], true)) $allow = true;
          if (isset($allowed_attrs['*']) && in_array($name, $allowed_attrs['*'], true)) $allow = true;
          if (!$allow) continue;
          if (strpos($name, 'on') === 0) continue;
          if ($name === 'style') continue;
          if ($name === 'href' || $name === 'src') {
            $v = trim($val);
            if (!preg_match('#^(https?|mailto):#i', $v)) continue;
          }
          $out_attrs[] = $name . '="' . htmlspecialchars_uni($val) . '"';
        }
      }
      $attr_str = '';
      if (!empty($out_attrs)) $attr_str = ' ' . implode(' ', $out_attrs);
      return "<{$tag}{$attr_str}>";
    },
    $html
  );
}

function maskify_strip_tags_from_message($message, $extra_fields_map)
{
  $placeholders = [];
  $ph_prefix = "\x1A" . "MASKIFY_CODE_BLOCK_";
  $ph_suffix = "_END" . "\x1A";
  $idx = 0;

  $message = preg_replace_callback(
    '#(\[code(?:=[^\]]+)?\][\s\S]*?\[/code\]|\[php\][\s\S]*?\[/php\])#i',
    function ($m) use (&$placeholders, &$idx, $ph_prefix, $ph_suffix) {
      $key = $ph_prefix . ($idx++) . $ph_suffix;
      $placeholders[$key] = $m[0];
      return $key;
    },
    $message
  );

  $is_html = strpos($message, '<br') !== false || strpos($message, '</') !== false;

  $patterns = [
    '#\s*\[avatar\][\s\S]*?\[/avatar\]\s*#i',
    '#\s*\[nick\][\s\S]*?\[/nick\]\s*#i',
    '#\s*\[sign\][\s\S]*?\[/sign\]\s*#i',
  ];
  foreach ($extra_fields_map as $code => $_) {
    $t = preg_quote($code, '#');
    $patterns[] = "#\\s*\\[$t\\][\\s\\S]*?\\[/$t\\]\\s*#i";
  }
  $message = preg_replace($patterns, '', $message);

  if ($is_html) {
    $message = preg_replace('#(?:\s*<br\s*/?>\s*){3,}#i', "<br />\n<br />", $message);
    $message = preg_replace('#^(?:\s*<br\s*/?>\s*)+#i', '', $message);
    $message = preg_replace('#(?:\s*<br\s*/?>\s*)+$#i', '', $message);
  } else {
    $message = preg_replace("#(\r?\n){3,}#", "\n\n", $message);
    $message = trim($message, "\r\n");
  }

  if (!empty($placeholders)) {
    $message = strtr($message, $placeholders);
  }

  return $message;
}

function maskify_parse_message_start(&$message)
{
  $extra_fields_map = maskify_load_extra_fields_map();
  $message = maskify_strip_tags_from_message($message, $extra_fields_map);
}

function maskify_load_extra_fields_map()
{
  global $db, $mybb;
  static $cache = null;
  if ($cache !== null) return $cache;

  $cache = [];

  $json = trim((string)$mybb->settings['maskify_extra_fields']);
  if ($json !== '') {
    $arr = @json_decode($json, true);
    if (is_array($arr)) {
      foreach ($arr as $item) {
        if (empty($item['code'])) continue;
        $code = trim($item['code']);
        $cache[$code] = [
          'code'         => $code,
          'field_type'   => ($item['type'] === 'html') ? 'html' : 'text',
          'max_length'   => (int)($item['max_length'] ?? 255),
          'html_profile' => $item['html_profile'] ?? 'default',
          'mode'         => 'replace',
          'replace_fid'  => isset($item['replace_fid']) ? (int)$item['replace_fid'] : 0,
        ];
      }
    }
  }

  if (empty($cache) && $db->table_exists('maskify_fields')) {
    $query = $db->simple_select('maskify_fields', '*', 'active=1');
    while ($row = $db->fetch_array($query)) {
      $code = trim($row['code']);
      if ($code === '') continue;
      $cache[$code] = [
        'code'         => $code,
        'field_type'   => $row['field_type'],
        'max_length'   => (int)$row['max_length'],
        'html_profile' => $row['html_profile'],
        'mode'         => 'replace',
        'replace_fid'  => 0,
      ];
    }
  }

  return $cache;
}

function maskify_postbit(&$post)
{
  global $db, $mybb, $lang, $templates, $parser, $cache;

  if (empty($mybb->settings['maskify_enabled'])) return;

  if (method_exists($lang, 'load')) $lang->load('maskify');

  $extra_fields_map = maskify_load_extra_fields_map();

  $uid = (int)$post['uid'];
  $author = get_user($uid);
  if (!$author) $author = ['uid' => 0, 'usergroup' => 1, 'additionalgroups' => ''];

  [$has_right, $global_forum_ok] = maskify_can_use_masks($post, $author);

  $pid = (int)$post['pid'];
  $raw = '';
  $q = $db->simple_select('posts', 'message', "pid={$pid}");
  if ($row = $db->fetch_array($q)) $raw = (string)$row['message'];

  $masks = maskify_extract_masks(maskify_without_code_blocks($raw), $extra_fields_map);

  $preserve = maskify_get_preserve_forums_set();
  $is_preserve_forum = isset($preserve[(int)$post['fid']]);

  if (!$has_right) {
    if (!$is_preserve_forum) {
      if ($global_forum_ok) $post['message'] = maskify_strip_tags_from_message($post['message'], $extra_fields_map);
      return;
    }
  }

  $force_apply = $is_preserve_forum;

  if (!empty($masks['avatar']) && ($force_apply || maskify_group_tag_allowed($author['usergroup'], 'avatar'))) {
    $url = maskify_sanitize_url_avatar($masks['avatar']);
    if ($url) $post['useravatar'] = '<img src="' . htmlspecialchars_uni($url) . '" alt="" />';
  }

  if (!empty($masks['nick']) && ($force_apply || maskify_group_tag_allowed($author['usergroup'], 'nick'))) {
    $nick = maskify_sanitize_text($masks['nick'], (int)$mybb->settings['maskify_nick_maxlen']);
    if ($nick !== '') {
      if (!empty($post['profilelink'])) {
        $post['profilelink'] = preg_replace('#>(.*?)</a>#si', '>' . htmlspecialchars_uni($nick) . '</a>', $post['profilelink']);
      }
      $post['username'] = htmlspecialchars_uni($nick);
    }
  }

  if (!empty($masks['sign']) && ($force_apply || maskify_group_tag_allowed($author['usergroup'], 'sign'))) {
    $sign_text = maskify_sanitize_text($masks['sign'], (int)$mybb->settings['maskify_sign_maxlen']);
    require_once MYBB_ROOT . 'inc/class_parser.php';
    if (!isset($parser) || !is_object($parser)) $parser = new postParser();
    $opts = [
      'allow_html' => 0,
      'allow_mycode' => (int)$mybb->settings['maskify_sign_allow_mycode'],
      'allow_smilies' => (int)$mybb->settings['maskify_sign_allow_mycode'],
      'nl2br' => 1,
      'filter_badwords' => 1,
    ];
    $post['signature'] = $parser->parse_message($sign_text, $opts);
  }

  $mask_fields_html = '';
  $profilefield_html = isset($post['profilefield']) ? (string)$post['profilefield'] : '';
  $user_details_html = isset($post['user_details']) ? (string)$post['user_details'] : '';

  $pfcache = [];
  if (is_object($cache)) {
    $raw_pfcache = $cache->read('profilefields');
    if (is_array($raw_pfcache)) {
      foreach ($raw_pfcache as $field_conf) {
        if (!isset($field_conf['fid'])) continue;
        $pfcache[(int)$field_conf['fid']] = $field_conf;
      }
    }
  }

  foreach ($masks['extra'] as $code => $val) {
    if (!isset($extra_fields_map[$code])) continue;
    $conf = $extra_fields_map[$code];

    if (!$force_apply && !maskify_group_tag_allowed($author['usergroup'], $code)) continue;

    $val = (string)$val;
    if ($conf['field_type'] === 'html') {
      $val = maskify_sanitize_html($val, $conf['html_profile']);
    } else {
      $val = htmlspecialchars_uni(maskify_sanitize_text($val, (int)$conf['max_length']));
    }

    $fid = (int)$conf['replace_fid'];
    if ($fid <= 0) continue;

    $field_name = '';
    if (!empty($pfcache[$fid]['name'])) $field_name = htmlspecialchars_uni($pfcache[$fid]['name']);

    $did_replace = false;
    if ($field_name !== '') {
      $r1 = maskify_replace_profilefield_value($profilefield_html, $field_name, $val);
      $r2 = maskify_replace_profilefield_value($user_details_html, $field_name, $val);
      $did_replace = $r1 || $r2;
    }

    if (!$did_replace) {
      $label = ($field_name !== '') ? $field_name : ('fid' . $fid);
      if ($user_details_html !== '') {
        $user_details_html .= '<br />' . $label . ': ' . $val;
      } elseif ($profilefield_html !== '') {
        $profilefield_html .= '<br />' . $label . ': ' . $val;
      } else {
        $user_details_html = $label . ': ' . $val;
      }
    }
  }

  $post['mask_fields'] = $mask_fields_html;
  if (isset($post['profilefield'])) $post['profilefield'] = $profilefield_html;
  if (isset($post['user_details']) || $user_details_html !== '') $post['user_details'] = $user_details_html;
}

function maskify_parse_message_end(&$message)
{
  if (strpos($message, '<br') !== false) {
    $message = preg_replace('#(?:\s*<br\s*/?>\s*){3,}#i', "<br />\n<br />", $message);
    $message = preg_replace('#^(?:\s*<br\s*/?>\s*)+#i', '', $message);
    $message = preg_replace('#(?:\s*<br\s*/?>\s*)+$#i', '', $message);
  }
}

?>
