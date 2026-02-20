<?php
/**
 * Scoped Post Counter (CPF) rebuild control panel.
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!function_exists('spc_cpf_rebuild'))
{
    require_once MYBB_ROOT.'inc/plugins/spc_cpf.php';
}

spc_cpf_load_admin_language();
check_admin_permissions(array('module' => 'tools', 'action' => 'spc_cpf_rebuild'), true);

$page->add_breadcrumb_item($lang->spc_cpf_rebuild_menu, 'index.php?module=tools-spc_cpf_rebuild');

$plugins->run_hooks('admin_tools_spc_cpf_rebuild_begin');

if($mybb->input['action'] === 'run')
{
    spc_cpf_handle_rebuild_run();
}
else
{
    spc_cpf_output_rebuild_overview();
}

/**
 * Render the overview page with configuration snapshot and form.
 */
function spc_cpf_output_rebuild_overview()
{
    global $page, $lang, $mybb;

    $page->output_header($lang->spc_cpf_rebuild_page_title);

    $errors = spc_cpf_validate_configuration();
    if(!empty($errors))
    {
        foreach($errors as $error)
        {
            $page->output_error('<p>'.htmlspecialchars_uni($error).'</p>');
        }
    }

    $sub_tabs = array(
        'overview' => array(
            'title' => $lang->spc_cpf_rebuild_tab_overview,
            'link' => 'index.php?module=tools-spc_cpf_rebuild',
            'description' => $lang->spc_cpf_rebuild_tab_desc
        )
    );

    $page->output_nav_tabs($sub_tabs, 'overview');

    $status_table = new Table;
    $target = spc_cpf_target_fid();
    $forums = spc_cpf_get_forum_ids();

    $status_table->construct_header($lang->spc_cpf_status_item, array('class' => 'align_left', 'width' => '30%'));
    $status_table->construct_header($lang->spc_cpf_status_value, array('class' => 'align_left'));

    $status_table->construct_cell($lang->spc_cpf_status_target);
    if($target > 0)
    {
        $status_table->construct_cell('fid'.$target);
    }
    else
    {
        $status_table->construct_cell('<span class="smalltext"><em>'.$lang->spc_cpf_status_target_missing.'</em></span>');
    }
    $status_table->construct_row();

    $status_table->construct_cell($lang->spc_cpf_status_forums);
    if(!empty($forums))
    {
        $status_table->construct_cell(implode(', ', array_map('intval', $forums)));
    }
    else
    {
        $status_table->construct_cell('<span class="smalltext"><em>'.$lang->spc_cpf_status_forums_empty.'</em></span>');
    }
    $status_table->construct_row();

    $status_table->construct_cell($lang->spc_cpf_status_firstpost);
    $status_table->construct_cell(spc_cpf_should_count_first_post() ? $lang->spc_cpf_yes : $lang->spc_cpf_no);
    $status_table->construct_row();

    $status_table->construct_cell($lang->spc_cpf_status_show_zero);
    $status_table->construct_cell(spc_cpf_show_zero() ? $lang->spc_cpf_yes : $lang->spc_cpf_no);
    $status_table->construct_row();

    $status_table->output($lang->spc_cpf_status_title);

    $form = new Form('index.php?module=tools-spc_cpf_rebuild&action=run', 'post');
    $form_container = new FormContainer($lang->spc_cpf_rebuild_form_title);

    $form_container->output_row(
        $lang->spc_cpf_label_uid_from,
        $lang->spc_cpf_label_uid_from_desc,
        $form->generate_text_box('uid_from', $mybb->get_input('uid_from', MyBB::INPUT_INT), array('class' => 'field50')),
        'uid_from'
    );
    $form_container->output_row(
        $lang->spc_cpf_label_uid_to,
        $lang->spc_cpf_label_uid_to_desc,
        $form->generate_text_box('uid_to', $mybb->get_input('uid_to', MyBB::INPUT_INT), array('class' => 'field50')),
        'uid_to'
    );
    $form_container->output_row(
        $lang->spc_cpf_label_batch_size,
        $lang->spc_cpf_label_batch_size_desc,
        $form->generate_text_box('batch_size', max(1, $mybb->get_input('batch_size', MyBB::INPUT_INT) ?: 250), array('class' => 'field50')),
        'batch_size'
    );
    $form_container->output_row(
        $lang->spc_cpf_label_dry_run,
        $lang->spc_cpf_label_dry_run_desc,
        $form->generate_yes_no_radio('dry_run', $mybb->get_input('dry_run', MyBB::INPUT_INT), array('class' => 'radio_input')),
        'dry_run'
    );

    $form_container->end();

    $buttons = array();
    $buttons[] = $form->generate_submit_button($lang->spc_cpf_run_button);
    $form->output_submit_wrapper($buttons);

    $form->end();

    $page->output_footer();
}

/**
 * Handle iterative rebuild runs.
 */
function spc_cpf_handle_rebuild_run()
{
    global $page, $lang, $mybb, $db, $admin_session;

    $batch_size = max(1, $mybb->get_input('batch_size', MyBB::INPUT_INT));
    $dry = (bool)$mybb->get_input('dry_run', MyBB::INPUT_INT);
    $uid_from = $mybb->get_input('uid_from', MyBB::INPUT_INT);
    $uid_to = $mybb->get_input('uid_to', MyBB::INPUT_INT);

    if($uid_from <= 0)
    {
        $uid_from = null;
    }
    if($uid_to <= 0)
    {
        $uid_to = null;
    }
    elseif($uid_from !== null && $uid_to < $uid_from)
    {
        $tmp = $uid_from;
        $uid_from = $uid_to;
        $uid_to = $tmp;
    }

    $next_uid = $mybb->get_input('next_uid', MyBB::INPUT_INT);
    $processed_total = $mybb->get_input('processed_total', MyBB::INPUT_INT);
    $changed_total = $mybb->get_input('changed_total', MyBB::INPUT_INT);
    $batch_index = $mybb->get_input('batch_index', MyBB::INPUT_INT);

    if($processed_total < 0)
    {
        $processed_total = 0;
    }
    if($changed_total < 0)
    {
        $changed_total = 0;
    }
    if($batch_index < 0)
    {
        $batch_index = 0;
    }

    if($dry && $batch_index === 0)
    {
        update_admin_session('spc_cpf_rebuild_log', array());
    }

    $where = array('uid > 0');
    if($uid_from !== null)
    {
        $where[] = "uid >= {$uid_from}";
    }
    if($uid_to !== null)
    {
        $where[] = "uid <= {$uid_to}";
    }

    $cursor = 0;
    if($next_uid > 0)
    {
        $cursor = $next_uid;
    }
    elseif($uid_from !== null)
    {
        $cursor = $uid_from;
    }

    if($cursor > 0)
    {
        $where[] = "uid >= {$cursor}";
    }

    $where_clause = implode(' AND ', $where);

    $query = $db->simple_select('users', 'uid', $where_clause, array('order_by' => 'uid', 'order_dir' => 'asc', 'limit' => $batch_size));

    $uids = array();
    while($row = $db->fetch_array($query))
    {
        $uids[] = (int)$row['uid'];
    }

    if(empty($uids))
    {
        spc_cpf_render_rebuild_complete($processed_total, $changed_total, $dry);
        return;
    }

    $batch_index++;
    $batch_start = min($uids);
    $batch_end = max($uids);

    $result = spc_cpf_rebuild($batch_start, $batch_end, $dry);

    $processed_total += (int)$result['processed'];
    $changed_total += (int)$result['changed'];

    if($dry && !empty($result['details']))
    {
        $log = $admin_session['data']['spc_cpf_rebuild_log'] ?? array();
        if(!empty($log['__truncated__']))
        {
            $result['details'] = array();
        }
        foreach($result['details'] as $entry)
        {
            if(count($log) >= 250)
            {
                $log['__truncated__'] = true;
                break;
            }
            $log[] = $entry;
        }
        update_admin_session('spc_cpf_rebuild_log', $log);
    }

    $next_uid = $batch_end + 1;
    if($uid_to !== null && $next_uid > $uid_to)
    {
        spc_cpf_render_rebuild_complete($processed_total, $changed_total, $dry);
        return;
    }

    spc_cpf_render_rebuild_progress(array(
        'uid_from' => $uid_from,
        'uid_to' => $uid_to,
        'batch_size' => $batch_size,
        'dry_run' => $dry ? 1 : 0,
        'next_uid' => $next_uid,
        'processed_total' => $processed_total,
        'changed_total' => $changed_total,
        'batch_index' => $batch_index
    ), $result);
}

/**
 * Output progress auto-redirect form.
 *
 * @param array $state
 * @param array $result
 */
function spc_cpf_render_rebuild_progress(array $state, array $result)
{
    global $page, $lang;

    $page->output_header($lang->spc_cpf_rebuild_page_title);

    $status = $lang->sprintf(
        $lang->spc_cpf_rebuild_progress,
        (int)$state['batch_index'],
        (int)$result['processed'],
        (int)$state['processed_total'],
        (int)$state['changed_total']
    );
    $page->output_inline_message($status);

    $form = new Form('index.php?module=tools-spc_cpf_rebuild&action=run', 'post');

    foreach($state as $key => $value)
    {
        echo $form->generate_hidden_field($key, $value, array('id' => false));
    }

    $form->output_submit_wrapper(array($form->generate_submit_button($lang->spc_cpf_continue_button)));
    output_auto_redirect($form, $lang->spc_cpf_auto_continue);
    $form->end();

    $page->output_footer();
    exit;
}

/**
 * Render completion summary.
 *
 * @param int $processed
 * @param int $changed
 * @param bool $dry
 */
function spc_cpf_render_rebuild_complete($processed, $changed, $dry)
{
    global $page, $lang, $admin_session;

    $page->output_header($lang->spc_cpf_rebuild_page_title);

    $message = $lang->sprintf($lang->spc_cpf_rebuild_finished, (int)$processed, (int)$changed);
    $page->output_success($message);

    if($dry)
    {
        $log = $admin_session['data']['spc_cpf_rebuild_log'] ?? array();
        $table = new Table;
        $table->construct_header($lang->spc_cpf_dry_uid);
        $table->construct_header($lang->spc_cpf_dry_old);
        $table->construct_header($lang->spc_cpf_dry_new);

        $truncated = !empty($log['__truncated__']);
        foreach($log as $key => $entry)
        {
            if($key === '__truncated__')
            {
                continue;
            }
            if(is_array($entry))
            {
                $table->construct_cell((int)$entry['uid']);
                $table->construct_cell((int)$entry['old']);
                $table->construct_cell((int)$entry['new']);
                $table->construct_row();
            }
        }

        if($table->num_rows())
        {
            $table->output($lang->spc_cpf_dry_table_title);
        }
        else
        {
            $page->output_inline_message($lang->spc_cpf_dry_no_changes);
        }

        if($truncated)
        {
            $page->output_alert($lang->spc_cpf_dry_truncated_notice);
        }

        update_admin_session('spc_cpf_rebuild_log', array());
    }

    $buttons = array(
        array('link' => 'index.php?module=tools-spc_cpf_rebuild', 'title' => $lang->spc_cpf_back_button)
    );

    $page->output_footer($buttons);
    exit;
}