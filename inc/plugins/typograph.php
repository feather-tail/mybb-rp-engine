<?php
if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('datahandler_post_validate_post', 'typograph_on_post');
$plugins->add_hook('datahandler_post_validate_thread', 'typograph_on_thread');
$plugins->add_hook('datahandler_post_update', 'typograph_on_update');
$plugins->add_hook('datahandler_pm_validate', 'typograph_on_pm');

$plugins->add_hook('xmlhttp_update_post', 'typograph_on_xmlhttp_update_post');

function typograph_info()
{
    return array(
        'name'          => 'Typograph',
        'description'   => 'Нормализует тире/дефисы и переводит кавычки в «ёлочки» при сохранении сообщений (без поломки BBCode и без обработки [code]).',
        'website'       => '',
        'author'        => 'Feathertail',
        'authorsite'    => '',
        'version'       => '1.0.1',
        'compatibility' => '18*'
    );
}

function typograph_is_installed()
{
    global $db;
    return (bool)$db->fetch_field(
        $db->simple_select('settings', 'COUNT(*) AS c', "name='typograph_enabled'"),
        'c'
    );
}

function typograph_install()
{
    global $db;

    $group = array(
        'name'        => 'typograph',
        'title'       => 'Typograph',
        'description' => 'Настройки нормализации тире/кавычек.',
        'disporder'   => 1,
        'isdefault'   => 0
    );

    $gid = (int)$db->insert_query('settinggroups', $group);

    $settings = array();

    $settings[] = array(
        'name'        => 'typograph_enabled',
        'title'       => 'Включить',
        'description' => 'Нормализовать текст при сохранении постов/тем.',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 1,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'typograph_dash_mode',
        'title'       => 'Тире / дефис',
        'description' => '0 — не менять; 1 — тире (—/–/−) → дефис (-); 2 — дефис между пробелами и двойной дефис → тире (—).',
        'optionscode' => "select\n0=Не менять\n1=Тире → дефис (-)\n2=Дефис между пробелами → тире (—)",
        'value'       => '1',
        'disporder'   => 2,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'typograph_quotes',
        'title'       => 'Кавычки-ёлочки',
        'description' => 'Преобразовывать прямые/типографские двойные кавычки (" “ ” „) в «…» (по парам).',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 3,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'typograph_skip_inches',
        'title'       => 'Не трогать дюймы 5"',
        'description' => 'Если включено — кавычка после цифры (например 5") останется как есть.',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 4,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'typograph_apply_pms',
        'title'       => 'Применять к личным сообщениям',
        'description' => 'Если включено — нормализация будет также в ЛС.',
        'optionscode' => 'yesno',
        'value'       => '0',
        'disporder'   => 5,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'typograph_protected',
        'title'       => 'Защищённые теги',
        'description' => 'Список тегов через |, внутри которых ничего не менять (например: code|php|sql|nobbcode|noparse).',
        'optionscode' => 'text',
        'value'       => 'code|php|sql|nobbcode|noparse',
        'disporder'   => 6,
        'gid'         => $gid
    );

    foreach($settings as $s) {
        $db->insert_query('settings', $s);
    }

    rebuild_settings();
}

function typograph_uninstall()
{
    global $db;

    $db->delete_query('settings', "name IN (
        'typograph_enabled',
        'typograph_dash_mode',
        'typograph_quotes',
        'typograph_skip_inches',
        'typograph_apply_pms',
        'typograph_protected'
    )");

    $db->delete_query('settinggroups', "name='typograph'");

    rebuild_settings();
}

function typograph_activate() {}
function typograph_deactivate() {}

function typograph_on_post(&$posthandler)
{
    typograph_apply_to_handler($posthandler, 'message', false);
}

function typograph_on_thread(&$posthandler)
{
    typograph_apply_to_handler($posthandler, 'message', false);
}

function typograph_on_update(&$posthandler)
{
    typograph_apply_to_handler($posthandler, 'message', false);
}

function typograph_on_pm(&$pmhandler)
{
    typograph_apply_to_handler($pmhandler, 'message', true);
}

function typograph_on_xmlhttp_update_post()
{
    global $mybb, $posthandler, $post, $parser, $parser_options;

    if(empty($mybb->settings['typograph_enabled'])) {
        return;
    }

    if(!isset($posthandler) || !is_object($posthandler) || !isset($posthandler->data['message'])) {
        return;
    }

    if(!isset($parser) || !is_object($parser) || !isset($parser_options) || !is_array($parser_options)) {
        return;
    }

    $raw = $posthandler->data['message'];
    $post['message'] = $parser->parse_message($raw, $parser_options);
}

function typograph_apply_to_handler(&$handler, $field, $is_pm)
{
    global $mybb;

    if(empty($mybb->settings['typograph_enabled'])) {
        return;
    }

    if($is_pm && empty($mybb->settings['typograph_apply_pms'])) {
        return;
    }

    if(!isset($handler->data[$field]) || $handler->data[$field] === '') {
        return;
    }

    $opts = array(
        'dash_mode'    => (int)$mybb->settings['typograph_dash_mode'],
        'quotes'       => (int)$mybb->settings['typograph_quotes'] ? true : false,
        'skip_inches'  => (int)$mybb->settings['typograph_skip_inches'] ? true : false,
        'protected'    => (string)$mybb->settings['typograph_protected']
    );

    $handler->data[$field] = typograph_process_message($handler->data[$field], $opts);
}

function typograph_process_message($message, $opts)
{
    $protect_re = typograph_build_protect_regex($opts['protected']);
    $quote_state = false;

    if($protect_re) {
        $parts = preg_split($protect_re, $message, -1, PREG_SPLIT_DELIM_CAPTURE);
        if(!is_array($parts)) {
            return $message;
        }

        $out = '';
        foreach($parts as $part) {
            if($part !== '' && preg_match($protect_re, $part)) {
                $out .= $part;
                continue;
            }
            $out .= typograph_process_nonprotected($part, $opts, $quote_state);
        }
        return $out;
    }

    return typograph_process_nonprotected($message, $opts, $quote_state);
}

function typograph_build_protect_regex($protected)
{
    $protected = trim((string)$protected);
    if($protected === '') {
        return '';
    }

    $tags = preg_split('/\s*\|\s*/', $protected);
    if(!is_array($tags)) {
        return '';
    }

    $clean = array();
    foreach($tags as $t) {
        $t = trim($t);
        if($t !== '') {
            $clean[] = preg_quote($t, '~');
        }
    }

    if(empty($clean)) {
        return '';
    }

    $alt = implode('|', $clean);

    return '~(\[(?:' . $alt . ')\b[^\]]*\].*?\[/\s*(?:' . $alt . ')\])~is';
}

function typograph_get_mention_regex()
{
    return '~(?<![\p{L}\p{N}_\.\-])(@"[^"\r\n]+"(?:\s*#\d+)?|@[\p{L}\p{N}_][\p{L}\p{N}_\.\-]*)~u';
}

function typograph_apply_rules($plain, $opts, &$quote_state)
{
    if((int)$opts['dash_mode'] === 1) {
        $plain = str_replace(array("—", "–", "‒", "−"), "-", $plain);
    } elseif((int)$opts['dash_mode'] === 2) {
        $plain = preg_replace('~(\s)--(\s)~u', '$1—$2', $plain);
        $plain = preg_replace('~(?<=\s)-(?=\s)~u', '—', $plain);
    }

    if(!empty($opts['quotes'])) {
        $plain = typograph_replace_quotes($plain, $quote_state, !empty($opts['skip_inches']));
    }

    return $plain;
}

function typograph_process_plain_with_mentions($plain, $opts, &$quote_state)
{
    $re = typograph_get_mention_regex();

    $parts = preg_split($re, $plain, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(!is_array($parts) || count($parts) === 1) {
        return typograph_apply_rules($plain, $opts, $quote_state);
    }

    $out = '';
    foreach($parts as $p) {
        if($p === '') {
            continue;
        }
        if(preg_match($re, $p)) {
            $out .= $p;
            continue;
        }
        $out .= typograph_apply_rules($p, $opts, $quote_state);
    }

    return $out;
}

function typograph_process_nonprotected($text, $opts, &$quote_state)
{
    if($text === '') {
        return '';
    }

    $tokens = preg_split('~(\[[^\]\r\n]+\])~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(!is_array($tokens)) {
        return $text;
    }

    $out = '';
    foreach($tokens as $tok) {
        if($tok === '') {
            continue;
        }

        $is_tag = (substr($tok, 0, 1) === '[' && substr($tok, -1) === ']');

        if($is_tag) {
            $out .= $tok;
            continue;
        }

        $out .= typograph_process_plain_with_mentions($tok, $opts, $quote_state);
    }

    return $out;
}

function typograph_replace_quotes($text, &$state, $skip_inches)
{
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if(!is_array($chars)) {
        return $text;
    }

    $quote_map = array(
        '"'  => true,
        '“'  => true,
        '”'  => true,
        '„'  => true,
        '‟'  => true
    );

    $out = '';
    $n = count($chars);

    for($i = 0; $i < $n; $i++) {
        $ch = $chars[$i];

        if(isset($quote_map[$ch])) {
            $prev = ($i > 0) ? $chars[$i - 1] : '';
            $next = ($i + 1 < $n) ? $chars[$i + 1] : '';

            if($skip_inches && $prev !== '' && preg_match('~\d~u', $prev) && ($next === '' || preg_match('~\s~u', $next))) {
                $out .= $ch;
                continue;
            }

            $out .= $state ? '»' : '«';
            $state = !$state;
            continue;
        }

        $out .= $ch;
    }

    return $out;
}
