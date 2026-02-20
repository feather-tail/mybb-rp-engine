<?php
if (!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Direct access not allowed.');
}

global $page, $mybb, $db, $lang;

// Подгружаем язык (админские строки ищутся в inc/languages/<lang>/admin/custompostcounter.lang.php)
if (method_exists($lang, 'load')) {
    $lang->load('custompostcounter');
}

// Гарантируем наличие функции пересчёта в админ-контексте
if (!function_exists('custompostcounter_rebuild_counts')) {
    // Попробуем подключить файл плагина
    $plugin_file = MYBB_ROOT.'inc/plugins/custompostcounter.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
    }
}

$page->add_breadcrumb_item($lang->cpc_tools_rebuild);

// Вывод
$page->output_header($lang->cpc_tools_rebuild);

// Вспомогательные include (на старых сборках может понадобиться)
if (!class_exists('Form')) {
    require_once MYBB_ROOT.'inc/class_form.php';
}
if (!class_exists('FormContainer')) {
    require_once MYBB_ROOT.'inc/class_form.php';
}

$sub_tabs['custompostcounter_rebuild'] = [
    'title'       => $lang->cpc_tools_rebuild,
    'link'        => "index.php?module=tools-custompostcounter_rebuild",
    'description' => $lang->cpc_tools_rebuild_desc,
];

$page->output_nav_tabs($sub_tabs, 'custompostcounter_rebuild');

if ($mybb->request_method === 'post') {
    // CSRF
    verify_post_check($mybb->get_input('my_post_key'));

    if (function_exists('custompostcounter_rebuild_counts')) {
        custompostcounter_rebuild_counts();
        flash_message($lang->cpc_tools_rebuild_done, 'success');
    } else {
        flash_message('custompostcounter_rebuild_counts() not found (plugin file not loaded).', 'error');
    }

    admin_redirect("index.php?module=tools-custompostcounter_rebuild");
}

$form = new Form("index.php?module=tools-custompostcounter_rebuild", "post", "custompostcounter_rebuild");
$form_container = new FormContainer($lang->cpc_tools_rebuild_desc);

$form_container->output_row(
    $lang->cpc_tools_rebuild,
    "",
    $form->generate_hidden_field("my_post_key", $mybb->post_code) .
    $form->generate_submit_button($lang->cpc_tools_rebuild)
);

$form_container->end();
$form->end();

$page->output_footer();
