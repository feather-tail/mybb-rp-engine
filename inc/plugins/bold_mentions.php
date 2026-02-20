<?php
if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('datahandler_post_insert_post_end', 'bm_bold_mentions_post_saved');
$plugins->add_hook('datahandler_post_insert_thread_end', 'bm_bold_mentions_post_saved');
$plugins->add_hook('datahandler_post_update_end', 'bm_bold_mentions_post_saved');
$plugins->add_hook('myalerts_register_client_alert_formatters', 'bm_bold_mentions_register_formatters');

function bold_mentions_info()
{
    return array(
        'name'          => 'Bold Mentions',
        'description'   => 'Создаёт уведомления MyAlerts, если в сообщении встречается [b]Username[/b].',
        'website'       => '',
        'author'        => 'Feathertail',
        'authorsite'    => '',
        'version'       => '1.0.0',
        'compatibility' => '18*'
    );
}

function bold_mentions_activate()
{
    bm_bold_mentions_myalerts_integrate();
}

function bold_mentions_deactivate()
{
}

function bm_bold_mentions_post_saved(&$datahandler)
{
    global $db;

    if (!bm_bold_mentions_myalerts_active()) {
        return;
    }

    $pid = 0;
    if (isset($datahandler->pid)) {
        $pid = (int)$datahandler->pid;
    } elseif (isset($datahandler->data['pid'])) {
        $pid = (int)$datahandler->data['pid'];
    }
    if ($pid <= 0) {
        return;
    }

    $tid = 0;
    if (isset($datahandler->data['tid'])) {
        $tid = (int)$datahandler->data['tid'];
    } elseif (isset($datahandler->tid)) {
        $tid = (int)$datahandler->tid;
    }

    $fromUid = 0;
    if (isset($datahandler->data['uid'])) {
        $fromUid = (int)$datahandler->data['uid'];
    }

    $fromName = '';
    if ($fromUid <= 0 && isset($datahandler->data['username'])) {
        $fromName = trim((string)$datahandler->data['username']);
    }

    $message = '';
    if (isset($datahandler->data['message'])) {
        $message = (string)$datahandler->data['message'];
    }
    if ($message === '') {
        return;
    }

    $candidates = bm_bold_mentions_extract_usernames($message);
    if (empty($candidates)) {
        return;
    }

    $escaped = array();
    foreach ($candidates as $name) {
        $escaped[] = "'" . $db->escape_string($name) . "'";
    }

    $uids = array();
    $query = $db->simple_select('users', 'uid, username', "username IN(" . implode(',', $escaped) . ")");
    while ($u = $db->fetch_array($query)) {
        $uid = (int)$u['uid'];
        if ($uid > 0 && $uid !== $fromUid) {
            $uids[$uid] = true;
        }
    }

    if (empty($uids)) {
        return;
    }

    bm_bold_mentions_send_alerts(array_keys($uids), $fromUid, $fromName, $pid, $tid);
}

function bm_bold_mentions_extract_usernames($message)
{
    $message = bm_bold_mentions_strip_indirect_content($message);

    $matches = array();
    preg_match_all('~\\[b\\]([^\\[\\]\\r\\n]{1,120})\\[/b\\]~iu', $message, $matches);

    if (empty($matches[1])) {
        return array();
    }

    $names = array();
    foreach ($matches[1] as $raw) {
        $name = trim(preg_replace('~\\s+~u', ' ', $raw));
        if ($name === '') {
            continue;
        }
        $names[$name] = true;
    }

    return array_keys($names);
}

function bm_bold_mentions_strip_indirect_content($message)
{
    $message = preg_replace('~\\[(code|php|mysql|xml|html|css|js)\\](.*?)\\[/\\1\\]~is', '', $message);

    for ($i = 0; $i < 10; $i++) {
        $new = preg_replace('~\\[quote(?:=[^\\]]*)?\\].*?\\[/quote\\]~is', '', $message);
        if ($new === $message) {
            break;
        }
        $message = $new;
    }

    return $message;
}

function bm_bold_mentions_send_alerts(array $toUids, $fromUid, $fromName, $pid, $tid)
{
    global $db, $cache, $plugins, $mybb;

    if (!bm_bold_mentions_myalerts_init()) {
        return;
    }

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if (!$alertTypeManager) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    }

    $alertType = $alertTypeManager->getByCode('bold_mentions_post');
    if (!$alertType) {
        return;
    }

    $existing = array();
    $typeId = (int)$alertType->getId();
    $q = $db->simple_select('alerts', 'uid', "alert_type_id={$typeId} AND object_id=".(int)$pid);
    while ($row = $db->fetch_array($q)) {
        $existing[(int)$row['uid']] = true;
    }

    $alertManager = MybbStuff_MyAlerts_AlertManager::getInstance();
    if (!$alertManager) {
        $alertManager = MybbStuff_MyAlerts_AlertManager::createInstance($mybb, $db, $cache, $plugins, $alertTypeManager);
    }

    $alerts = array();
    foreach ($toUids as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0 || ($fromUid > 0 && $uid === (int)$fromUid) || isset($existing[$uid])) {
            continue;
        }

        $extra = array();
        if ((int)$tid > 0) {
            $extra['tid'] = (int)$tid;
        }
        if ((int)$fromUid <= 0 && $fromName !== '') {
            $extra['from_name'] = $fromName;
        }

        $alert = MybbStuff_MyAlerts_Entity_Alert::make($uid, $alertType, (int)$pid, $extra);
        $alert->setFromUserId((int)$fromUid);
        $alerts[] = $alert;
    }

    if (!empty($alerts)) {
        $alertManager->addAlerts($alerts);
        $alertManager->commit();
    }
}

function bm_bold_mentions_register_formatters(&$formatterManager)
{
    global $mybb, $lang;

    if (!bm_bold_mentions_myalerts_init()) {
        return;
    }

    if (!class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
        return;
    }

    if (!class_exists('BM_BoldMentionsPostFormatter')) {
        class BM_BoldMentionsPostFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
        {
            public function init()
            {
            }

            public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
            {
                $from = $outputAlert['from_user'];
                if ((int)$alert->getFromUserId() <= 0) {
                    $extra = $alert->getExtraDetails();
                    if (!empty($extra['from_name'])) {
                        $from = htmlspecialchars_uni($extra['from_name']);
                    } elseif (!empty($this->lang->guest)) {
                        $from = $this->lang->guest;
                    } else {
                        $from = 'Гость';
                    }
                }

                $link = htmlspecialchars_uni($this->buildShowLink($alert));
                return $from.' упомянул вас в <a href="'.$link.'">сообщении</a>.';
            }

            public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
            {
                $extra = $alert->getExtraDetails();
                $pid = (int)$alert->getObjectId();
                $tid = isset($extra['tid']) ? (int)$extra['tid'] : 0;

                if (function_exists('get_post_link') && $tid > 0) {
                    $rel = get_post_link($pid, $tid);
                    return $this->mybb->settings['bburl'].'/'.$rel;
                }

                return $this->mybb->settings['bburl'].'/showthread.php?pid='.$pid.'#pid'.$pid;
            }
        }
    }

    $formatter = new BM_BoldMentionsPostFormatter($mybb, $lang, 'bold_mentions_post');
    $formatter->init();
    $formatterManager->registerFormatter($formatter);
}

function bm_bold_mentions_myalerts_integrate()
{
    global $db, $cache;

    if (!bm_bold_mentions_myalerts_active()) {
        return;
    }
    if (!bm_bold_mentions_myalerts_init()) {
        return;
    }

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    $existing = $alertTypeManager->getByCode('bold_mentions_post');

    if (!$existing) {
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('bold_mentions_post');
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);
        $alertType->setDefaultUserEnabled(true);
        $alertTypeManager->add($alertType);
    }
}

function bm_bold_mentions_myalerts_active()
{
    global $cache;

    $pluginsCache = $cache->read('plugins');
    if (empty($pluginsCache['active']) || !is_array($pluginsCache['active'])) {
        return false;
    }

    return in_array('myalerts', $pluginsCache['active'], true);
}

function bm_bold_mentions_myalerts_init()
{
    if (class_exists('MybbStuff_MyAlerts_Entity_Alert')) {
        return true;
    }

    $core = MYBB_ROOT . 'inc/plugins/MybbStuff/Core/ClassLoader.php';
    if (!is_readable($core)) {
        return false;
    }

    require_once $core;

    $classLoader = new MybbStuff_Core_ClassLoader();
    $classLoader->registerNamespace('MybbStuff_MyAlerts', array(MYBB_ROOT . 'inc/plugins/MybbStuff/MyAlerts/src'));
    $classLoader->register();

    return class_exists('MybbStuff_MyAlerts_Entity_Alert');
}
