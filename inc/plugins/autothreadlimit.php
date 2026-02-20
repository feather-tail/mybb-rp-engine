<?php
if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('global_start', 'atl_global_start_capture_draft_and_redirect');
$plugins->add_hook('showthread_start', 'atl_showthread_prepare_notice');
$plugins->add_hook('newreply_start', 'atl_newreply_start_redirect_if_limited_closed');
$plugins->add_hook('newreply_start', 'atl_newreply_prepare_restore');

$plugins->add_hook('datahandler_post_insert_post_end', 'atl_after_insert_post_end');
$plugins->add_hook('datahandler_post_validate_post', 'atl_validate_block_posting_in_limited_closed');

function autothreadlimit_info()
{
    return array(
        'name'          => 'AutoThreadLimit',
        'description'   => 'Лимит постов в теме с автозакрытием, созданием продолжения, редиректом и восстановлением текста (включая админов).',
        'website'       => '',
        'author'        => 'Feathertail / ChatGPT',
        'authorsite'    => '',
        'version'       => '1.0.5',
        'compatibility' => '18*'
    );
}

function autothreadlimit_install()
{
    global $db;

    if(!$db->table_exists('atl_links')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."atl_links (
                oldtid INT UNSIGNED NOT NULL,
                newtid INT UNSIGNED NOT NULL,
                created INT UNSIGNED NOT NULL,
                PRIMARY KEY (oldtid),
                KEY newtid (newtid)
            ) ENGINE=MyISAM {$collation}
        ");
    }

    $group = array(
        'name'        => 'autothreadlimit',
        'title'       => 'AutoThreadLimit',
        'description' => 'Настройки лимита постов и автопродолжений тем.',
        'disporder'   => 1,
        'isdefault'   => 0
    );
    $gid = (int)$db->insert_query('settinggroups', $group);

    $settings = array();

    $settings[] = array(
        'name'        => 'atl_enabled',
        'title'       => 'Включить AutoThreadLimit?',
        'description' => 'Если выключено — плагин ничего не делает.',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 1,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_limit',
        'title'       => 'Лимит постов в теме',
        'description' => 'При достижении лимита тема закрывается и создаётся продолжение. По умолчанию 2000.',
        'optionscode' => 'numeric',
        'value'       => '2000',
        'disporder'   => 2,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_forums',
        'title'       => 'Работать только в форумах (ID через запятую)',
        'description' => 'Пример: 2,5,9. Пусто = работает во всех форумах.',
        'optionscode' => 'text',
        'value'       => '',
        'disporder'   => 3,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_create_continuation',
        'title'       => 'Создавать продолжение автоматически?',
        'description' => 'Если “Да” — при закрытии создаётся новая тема в том же форуме.',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 4,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_close_old',
        'title'       => 'Закрывать тему при лимите?',
        'description' => 'Если “Да” — тема закрывается при достижении лимита.',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 5,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_post_in_old',
        'title'       => 'Добавлять служебный пост в старую тему?',
        'description' => 'Если “Да” — добавит пост со ссылкой на продолжение (учтите: это +1 сообщение).',
        'optionscode' => 'yesno',
        'value'       => '0',
        'disporder'   => 6,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_recovery',
        'title'       => 'Восстановление текста при попытке написать в закрытую тему',
        'description' => 'Сохраняет текст и редиректит в продолжение (там текст подставляется).',
        'optionscode' => 'yesno',
        'value'       => '1',
        'disporder'   => 7,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_part_format',
        'title'       => 'Формат названия части',
        'description' => 'Используйте {n}. Пример: “ — часть {n}”.',
        'optionscode' => 'text',
        'value'       => ' — часть {n}',
        'disporder'   => 8,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_newthread_message',
        'title'       => 'Текст первого сообщения новой темы',
        'description' => 'BBCode разрешён. Плейсхолдеры: {limit}, {oldtid}, {newtid}, {oldurl}, {newurl}, {oldsubject}, {newsubject}, {fid}.',
        'optionscode' => 'textarea',
        'value'       => "[b]Продолжение темы[/b]\n\nПредыдущая часть: [url={oldurl}]{oldsubject}[/url]\n\nПричина: достигнут лимит сообщений ({limit}).",
        'disporder'   => 9,
        'gid'         => $gid
    );

    $settings[] = array(
        'name'        => 'atl_oldthread_message',
        'title'       => 'Текст служебного сообщения в старой теме',
        'description' => 'BBCode разрешён. Плейсхолдеры: {limit}, {oldtid}, {newtid}, {oldurl}, {newurl}, {oldsubject}, {newsubject}, {fid}.',
        'optionscode' => 'textarea',
        'value'       => "Тема достигла лимита сообщений ({limit}). Продолжение: [url={newurl}]{newsubject}[/url]",
        'disporder'   => 10,
        'gid'         => $gid
    );

    foreach($settings as $s) {
        $db->insert_query('settings', $s);
    }

    rebuild_settings();
    atl_templates_install();
}

function autothreadlimit_is_installed()
{
    global $db;
    return $db->fetch_field($db->simple_select('settinggroups', 'gid', "name='autothreadlimit'"), 'gid') ? true : false;
}

function autothreadlimit_uninstall()
{
    global $db;

    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='autothreadlimit'"), 'gid');
    if($gid) {
        $db->delete_query('settings', "gid={$gid}");
        $db->delete_query('settinggroups', "gid={$gid}");
        rebuild_settings();
    }

    if($db->table_exists('atl_links')) {
        $db->drop_table('atl_links');
    }

    atl_templates_uninstall();
}

function autothreadlimit_activate()
{
    atl_templates_apply();
}

function autothreadlimit_deactivate()
{
    atl_templates_revert();
}

function atl_templates_install()
{
    global $db;

    $tpl = array(
        'title' => 'atl_draft_notice',
        'template' => $db->escape_string(
'<div class="tborder" style="margin: 10px 0;">
  <div class="thead"><strong>{$atl_notice_title}</strong></div>
  <div class="trow1" style="padding: 10px;">
    <p style="margin:0 0 8px 0;">{$atl_notice_text}</p>
    {$atl_notice_link}
    <textarea id="atl_draft_text" style="width:100%; min-height: 160px; box-sizing:border-box;">{$atl_draft_escaped}</textarea>
    <div style="margin-top: 8px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="button" id="atl_copy_btn">Копировать</button>
      <button type="button" class="button" id="atl_clear_btn">Очистить</button>
    </div>
  </div>
</div>
<script>
(function(){
  var copyBtn = document.getElementById("atl_copy_btn");
  var clearBtn = document.getElementById("atl_clear_btn");
  var ta = document.getElementById("atl_draft_text");
  if(copyBtn && ta){
    copyBtn.addEventListener("click", async function(){
      try{
        ta.focus(); ta.select();
        if(navigator.clipboard && window.isSecureContext){
          await navigator.clipboard.writeText(ta.value);
        } else {
          document.execCommand("copy");
        }
        copyBtn.textContent = "Скопировано!";
        setTimeout(function(){ copyBtn.textContent = "Копировать"; }, 1200);
      }catch(e){}
    });
  }
  if(clearBtn){
    clearBtn.addEventListener("click", function(){
      if(confirm("Удалить сохранённый текст?")){
        window.location.href = "{$atl_clear_url}";
      }
    });
  }
})();
</script>'
        ),
        'sid' => -1
    );

    $db->insert_query('templates', $tpl);
}

function atl_templates_uninstall()
{
    global $db;
    $db->delete_query('templates', "title='atl_draft_notice'");
}

function atl_templates_apply()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
    find_replace_templatesets('showthread', '#{\$header}#', "{\$header}\n{\$atl_draft_notice}\n", 0);
}

function atl_templates_revert()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
    find_replace_templatesets('showthread', "#\n{\$atl_draft_notice}\n#i", "\n", 0);
}

function atl_forum_is_enabled($fid)
{
    global $mybb;

    $list = trim((string)$mybb->settings['atl_forums']);
    if($list === '') {
        return true;
    }

    $ids = array_filter(array_map('intval', explode(',', $list)));
    return in_array((int)$fid, $ids, true);
}

function atl_get_continuation_tid($oldtid)
{
    global $db;

    $oldtid = (int)$oldtid;
    if($oldtid <= 0) {
        return 0;
    }

    $row = $db->fetch_array($db->simple_select('atl_links', 'newtid', "oldtid={$oldtid} AND newtid > oldtid"));
    if(!empty($row['newtid'])) {
        return (int)$row['newtid'];
    }
    return 0;
}

function atl_thread_is_limited_closed($tid)
{
    global $db;

    $tid = (int)$tid;
    if($tid <= 0) {
        return false;
    }

    $thread = $db->fetch_array($db->simple_select('threads', 'tid,fid,closed,visible', "tid={$tid}"));
    if(!$thread) {
        return false;
    }

    if(!atl_forum_is_enabled((int)$thread['fid'])) {
        return false;
    }

    if((int)$thread['visible'] === 0) {
        return false;
    }

    if(empty($thread['closed']) || $thread['closed'] == '0') {
        return false;
    }

    return atl_get_continuation_tid($tid) > 0;
}

function atl_newreply_start_redirect_if_limited_closed()
{
    global $mybb, $lang;

    if(empty($mybb->settings['atl_enabled']) || empty($mybb->settings['atl_recovery'])) {
        return;
    }

    $tid = (int)$mybb->get_input('tid');
    if($tid <= 0) {
        return;
    }

    if(atl_thread_is_limited_closed($tid)) {
        $newtid = atl_get_continuation_tid($tid);
        if($newtid > 0) {
            if(!isset($lang->atl_redirect_notice)) {
                $lang->atl_redirect_notice = 'Эта тема закрыта из-за лимита сообщений. Вас перенаправили в продолжение.';
            }
            $bburl = rtrim((string)$mybb->settings['bburl'], '/');
            redirect($bburl.'/newreply.php?tid='.$newtid, $lang->atl_redirect_notice);
        }
    }
}

function atl_global_start_capture_draft_and_redirect()
{
    global $mybb, $db, $lang;

    if(empty($mybb->settings['atl_enabled']) || empty($mybb->settings['atl_recovery'])) {
        return;
    }

    if(basename($_SERVER['PHP_SELF']) !== 'newreply.php') {
        return;
    }

    if($mybb->get_input('action') !== 'do_newreply') {
        return;
    }

    $tid = (int)$mybb->get_input('tid');
    if($tid <= 0) {
        return;
    }

    $message = $mybb->get_input('message', MyBB::INPUT_STRING);
    if($message === '' || $message === null) {
        return;
    }

    $thread = $db->fetch_array($db->simple_select('threads', 'tid,fid,closed,visible', "tid={$tid}"));
    if(!$thread) {
        return;
    }

    if(!atl_forum_is_enabled((int)$thread['fid'])) {
        return;
    }

    if((int)$thread['visible'] === 0) {
        return;
    }

    if(!empty($thread['closed']) && $thread['closed'] != '0') {
        $newtid = atl_get_continuation_tid($tid);
        if($newtid > 0) {
            $_SESSION['atl_draft_'.$tid] = array('message' => $message, 'time' => TIME_NOW);
            $_SESSION['atl_draft_'.$newtid] = array('message' => $message, 'time' => TIME_NOW);

            if(!isset($lang->atl_redirect_notice)) {
                $lang->atl_redirect_notice = 'Эта тема закрыта из-за лимита сообщений. Вас перенаправили в продолжение, текст сохранён.';
            }

            $bburl = rtrim((string)$mybb->settings['bburl'], '/');
            redirect($bburl.'/newreply.php?tid='.$newtid, $lang->atl_redirect_notice);
        }
    }
}

function atl_validate_block_posting_in_limited_closed(&$dh)
{
    global $mybb;

    if(empty($mybb->settings['atl_enabled'])) {
        return;
    }

    $tid = 0;
    if(isset($dh->data['tid'])) {
        $tid = (int)$dh->data['tid'];
    } elseif(isset($dh->post_insert_data['tid'])) {
        $tid = (int)$dh->post_insert_data['tid'];
    }

    if($tid <= 0) {
        return;
    }

    if(atl_thread_is_limited_closed($tid)) {
        $dh->set_error('threadclosed');
    }
}

function atl_showthread_prepare_notice()
{
    global $mybb, $db, $templates, $atl_draft_notice;

    $atl_draft_notice = '';

    if(empty($mybb->settings['atl_enabled']) || empty($mybb->settings['atl_recovery'])) {
        return;
    }

    $tid = (int)$mybb->get_input('tid');
    if($tid <= 0) {
        return;
    }

    if($mybb->get_input('atl_clear') == '1') {
        unset($_SESSION['atl_draft_'.$tid]);
        return;
    }

    if(empty($_SESSION['atl_draft_'.$tid]['message'])) {
        return;
    }

    $thread = $db->fetch_array($db->simple_select('threads', 'tid,fid', "tid={$tid}"));
    if(!$thread || !atl_forum_is_enabled((int)$thread['fid'])) {
        return;
    }

    $draft = $_SESSION['atl_draft_'.$tid]['message'];

    $bburl = rtrim((string)$mybb->settings['bburl'], '/');

    $newtid = atl_get_continuation_tid($tid);
    $atl_notice_link = '';
    if($newtid > 0) {
        $atl_notice_link = '<p style="margin:0 0 8px 0;"><a href="'.$bburl.'/newreply.php?tid='.$newtid.'"><strong>Ответить в продолжении темы</strong></a> · <a href="'.$bburl.'/showthread.php?tid='.$newtid.'">Открыть продолжение</a></p>';
    }

    $atl_notice_title = 'Сохранённый текст сообщения';
    $atl_notice_text  = 'Вы пытались отправить сообщение в закрытую тему. Текст сохранён — скопируйте его или перейдите в продолжение (там он подставится автоматически).';

    $atl_draft_escaped = htmlspecialchars_uni($draft);
    $atl_clear_url = $bburl.'/showthread.php?tid='.$tid.'&atl_clear=1';

    eval("\$atl_draft_notice = \"".$templates->get('atl_draft_notice')."\";");
}

function atl_newreply_prepare_restore()
{
    global $mybb, $db;

    if(empty($mybb->settings['atl_enabled']) || empty($mybb->settings['atl_recovery'])) {
        return;
    }

    $tid = (int)$mybb->get_input('tid');
    if($tid <= 0) {
        return;
    }

    $thread = $db->fetch_array($db->simple_select('threads', 'tid,fid', "tid={$tid}"));
    if(!$thread || !atl_forum_is_enabled((int)$thread['fid'])) {
        return;
    }

    if(!empty($_SESSION['atl_draft_'.$tid]['message']) && empty($mybb->input['message'])) {
        $mybb->input['message'] = $_SESSION['atl_draft_'.$tid]['message'];
    }
}

function atl_tpl_render($tpl, $vars)
{
    $out = (string)$tpl;
    foreach($vars as $k => $v) {
        $out = str_replace('{'.$k.'}', (string)$v, $out);
    }
    return $out;
}

function atl_after_insert_post_end(&$dh)
{
    global $mybb, $db;
    static $guard = false;

    if($guard) {
        return;
    }

    if(empty($mybb->settings['atl_enabled'])) {
        return;
    }

    $limit = (int)$mybb->settings['atl_limit'];
    if($limit <= 0) {
        return;
    }

    $tid = 0;
    if(isset($dh->post_insert_data['tid'])) {
        $tid = (int)$dh->post_insert_data['tid'];
    } elseif(isset($dh->data['tid'])) {
        $tid = (int)$dh->data['tid'];
    }

    if($tid <= 0) {
        return;
    }

    $thread = $db->fetch_array($db->simple_select('threads', 'tid,fid,subject,replies,closed,visible,uid', "tid={$tid}"));
    if(!$thread) {
        return;
    }

    if(!atl_forum_is_enabled((int)$thread['fid'])) {
        return;
    }

    if((int)$thread['visible'] === 0) {
        return;
    }

    if(!empty($thread['closed']) && $thread['closed'] != '0') {
        return;
    }

    $currentPosts = (int)$thread['replies'] + 1;
    if($currentPosts < $limit) {
        return;
    }

    $guard = true;

    $createContinuation = !empty($mybb->settings['atl_create_continuation']);
    $closeOld = !empty($mybb->settings['atl_close_old']);
    $postInOld = !empty($mybb->settings['atl_post_in_old']);

    $bburl = rtrim((string)$mybb->settings['bburl'], '/');

    $existing = atl_get_continuation_tid($tid);
    if($existing > 0) {
        if($closeOld) {
            $db->update_query('threads', array('closed' => 1), "tid={$tid}");
        }
        $guard = false;
        return;
    }

    $newtid = 0;
    $newSubject = '';

    if($createContinuation) {
        $newSubject = atl_next_part_subject($thread['subject'], (string)$mybb->settings['atl_part_format']);

        require_once MYBB_ROOT.'inc/datahandlers/post.php';
        require_once MYBB_ROOT.'inc/functions_post.php';

        $user = get_user((int)$thread['uid']);
        if(!$user || empty($user['uid'])) {
            $user = array('uid' => 0, 'username' => 'System');
        }

        $oldurl = $bburl.'/showthread.php?tid='.$tid;

        $newthread_tpl = (string)$mybb->settings['atl_newthread_message'];
        $firstMsg = atl_tpl_render($newthread_tpl, array(
            'limit' => $limit,
            'oldtid' => $tid,
            'newtid' => '{newtid}',
            'oldurl' => $oldurl,
            'newurl' => '{newurl}',
            'oldsubject' => $thread['subject'],
            'newsubject' => $newSubject,
            'fid' => (int)$thread['fid'],
        ));

        $posthandler = new PostDataHandler('insert');
        $posthandler->action = 'thread';

        $data = array(
            'fid' => (int)$thread['fid'],
            'subject' => $newSubject,
            'uid' => (int)$user['uid'],
            'username' => $user['username'],
            'message' => $firstMsg,
            'ipaddress' => get_ip(),
            'posthash' => md5(TIME_NOW.mt_rand()),
            'savedraft' => 0,
            'options' => array(
                'signature' => 0,
                'subscriptionmethod' => 0,
                'disablesmilies' => 0
            )
        );

        $posthandler->set_data($data);

        if($posthandler->validate_thread()) {
            $insert = $posthandler->insert_thread();
            $newtid = !empty($insert['tid']) ? (int)$insert['tid'] : 0;

            if($newtid > 0) {
                $db->replace_query('atl_links', array(
                    'oldtid' => $tid,
                    'newtid' => $newtid,
                    'created' => TIME_NOW
                ), 'oldtid');

                $newurl = $bburl.'/showthread.php?tid='.$newtid;

                $fixedFirst = atl_tpl_render($newthread_tpl, array(
                    'limit' => $limit,
                    'oldtid' => $tid,
                    'newtid' => $newtid,
                    'oldurl' => $oldurl,
                    'newurl' => $newurl,
                    'oldsubject' => $thread['subject'],
                    'newsubject' => $newSubject,
                    'fid' => (int)$thread['fid'],
                ));

                $db->update_query('posts', array('message' => $db->escape_string($fixedFirst)), "tid={$newtid} AND replyto=0");
            }
        }
    }

    if($postInOld && $newtid > 0) {
        require_once MYBB_ROOT.'inc/datahandlers/post.php';
        require_once MYBB_ROOT.'inc/functions_post.php';

        $oldurl = $bburl.'/showthread.php?tid='.$tid;
        $newurl = $bburl.'/showthread.php?tid='.$newtid;

        $old_tpl = (string)$mybb->settings['atl_oldthread_message'];
        $msg = atl_tpl_render($old_tpl, array(
            'limit' => $limit,
            'oldtid' => $tid,
            'newtid' => $newtid,
            'oldurl' => $oldurl,
            'newurl' => $newurl,
            'oldsubject' => $thread['subject'],
            'newsubject' => $newSubject,
            'fid' => (int)$thread['fid'],
        ));

        $ph = new PostDataHandler('insert');
        $ph->action = 'reply';

        $data = array(
            'tid' => $tid,
            'uid' => 0,
            'username' => 'System',
            'message' => $msg,
            'ipaddress' => get_ip(),
            'posthash' => md5(TIME_NOW.mt_rand()),
            'savedraft' => 0,
            'options' => array(
                'signature' => 0,
                'subscriptionmethod' => 0,
                'disablesmilies' => 0
            )
        );
        $ph->set_data($data);
        if($ph->validate_post()) {
            $ph->insert_post();
        }
    }

    if($closeOld) {
        $db->update_query('threads', array('closed' => 1), "tid={$tid}");
    }

    $guard = false;
}

function atl_next_part_subject($subject, $format)
{
    $subject = trim((string)$subject);
    $format = (string)$format;
    if($format === '') {
        $format = ' — часть {n}';
    }

    $n = 2;

    if(preg_match('~^(.*?)(\s*[—\-]\s*часть\s*(\d+))\s*$~iu', $subject, $m)) {
        $base = trim($m[1]);
        $oldn = (int)$m[3];
        if($oldn > 0) {
            $n = $oldn + 1;
        }
        $subject = $base;
    }

    $suffix = str_replace('{n}', (string)$n, $format);
    return $subject.$suffix;
}
