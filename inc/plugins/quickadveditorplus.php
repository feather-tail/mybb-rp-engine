<?php
/**
 *	Quick Advanced Editor Plus
 *
 *	Advanced editor in quick reply with quick quote for MyBB 1.8
 *
 * @Quick Advanced Editor Plus
 * @author	martec
 * @license http://www.gnu.org/copyleft/gpl.html GPLv3 license
 * @version 2.2.4
 * @Special Thanks: Aries-Belgium http://mods.mybb.com/view/quickquote
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('QAEP_PLUGIN_VER', '2.2.6');

// Plugin info
function quickadveditorplus_info ()
{
	global $lang;

	$lang->load('config_quickadveditorplus');

	$QAEP_description = <<<EOF
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
{$lang->quickadveditorplus_plug_desc}
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHLwYJKoZIhvcNAQcEoIIHIDCCBxwCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYBNyd8vlq22jGyHCWFXv4s+wHeWoSn7sVWoUhdat6s/HWn1w8KTbyvQyaCIadj4jr5IGJ57DkZEDjA8nkxNfh4lSHBqFTOgK2YmNSxQ+aaIIdT4sogKKeuflvu9tPGkduZW/wy5jrPHTxDpjiiBJbsNV0jzTCbLKtI2Cg05z51jwDELMAkGBSsOAwIaBQAwgawGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQIK+5H1MZ45vyAgYh5f5TLbR5izXt/7XPCPSp9+Ecb6ZxlQv2CFSmSt/B+Hlag2PN1Y8C/IhfDmgBBDfGxEdEdrZEsPxZEvG6qh20iM0WAJtPaUvxhrj51e3EkLXdv4w8TUyzUdDW/AcNulWXE3ET0pttSL8E08qtbJlOyObTwljYJwGrkyH7lSNPvll22xtLaxIWgoIIDhzCCA4MwggLsoAMCAQICAQAwDQYJKoZIhvcNAQEFBQAwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMB4XDTA0MDIxMzEwMTMxNVoXDTM1MDIxMzEwMTMxNVowgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDBR07d/ETMS1ycjtkpkvjXZe9k+6CieLuLsPumsJ7QC1odNz3sJiCbs2wC0nLE0uLGaEtXynIgRqIddYCHx88pb5HTXv4SZeuv0Rqq4+axW9PLAAATU8w04qqjaSXgbGLP3NmohqM6bV9kZZwZLR/klDaQGo1u9uDb9lr4Yn+rBQIDAQABo4HuMIHrMB0GA1UdDgQWBBSWn3y7xm8XvVk/UtcKG+wQ1mSUazCBuwYDVR0jBIGzMIGwgBSWn3y7xm8XvVk/UtcKG+wQ1mSUa6GBlKSBkTCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb22CAQAwDAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQUFAAOBgQCBXzpWmoBa5e9fo6ujionW1hUhPkOBakTr3YCDjbYfvJEiv/2P+IobhOGJr85+XHhN0v4gUkEDI8r2/rNk1m0GA8HKddvTjyGw/XqXa+LSTlDYkqI8OwR8GEYj4efEtcRpRYBxV8KxAW93YDWzFGvruKnnLbDAF6VR5w/cCMn5hzGCAZowggGWAgEBMIGUMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbQIBADAJBgUrDgMCGgUAoF0wGAYJKoZIhvcNAQkDMQsGCSqGSIb3DQEHATAcBgkqhkiG9w0BCQUxDxcNMTQxMTEwMTAzNjUxWjAjBgkqhkiG9w0BCQQxFgQUYi7NzbM83dI9AKkSz0GHvjSXJE8wDQYJKoZIhvcNAQEBBQAEgYA2/Ve62hw8ocjxIcwHXX4nq0BvWssYqFAmuWGqS1Cwr+6p/s1bdLw3JXrIinGrDJz8huIhM6y6WmAXhJEc2iEJLHwBAgY0shWVbZSyZBgxjmeGVO3wWVBmqjYX2IAhQLcmEUKNyEBqU6mgWYWI10XeWiIK5qjwRsU6lgQWZhfELw==-----END PKCS7-----
">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/pt_BR/i/scr/pixel.gif" width="1" height="1">
</form>
EOF;

	return array(
		"name"			  => "Quick Advanced Editor Plus",
		"description"	 => $QAEP_description,
		"website"		 => "https://github.com/martec/quickadveditorplus",
		"author"		=> "martec",
		"authorsite"	=> "http://community.mybb.com/user-49058.html",
		"version"		 => QAEP_PLUGIN_VER,
		"compatibility" => "18*"
	);
}

function quickadveditorplus_install()
{
	global $db, $lang, $mybb;

	$lang->load('config_quickadveditorplus');

    if($mybb->version_code < 1824)
    {
        flash_message("{$lang->quickadveditorplus_mybbver_req}", "error");
        admin_redirect("index.php?module=config-plugins");
    }

	$query	= $db->simple_select("settinggroups", "COUNT(*) as rows_n");
	$dorder = $db->fetch_field($query, 'rows_n') + 1;

	$groupid = $db->insert_query('settinggroups', array(
		'name'		=> 'quickadveditorplus',
		'title'		=> 'Quick Advanced Editor Plus',
		'description'	=> $lang->quickadveditorplus_sett_desc,
		'disporder'	=> $dorder,
		'isdefault'	=> '0'
	));

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_qurp_heigh',
		'title'		=> $lang->quickadveditorplus_qurp_heigh_title,
		'description'	=> $lang->quickadveditorplus_qurp_heigh_desc,
		'optionscode'	=> 'numeric',
		'value'		=> '280',
		'disporder'	=> '1',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_qued_heigh',
		'title'		=> $lang->quickadveditorplus_qued_heigh_title,
		'description'	=> $lang->quickadveditorplus_qued_heigh_desc,
		'optionscode'	=> 'numeric',
		'value'		=> '300',
		'disporder'	=> '2',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_smile',
		'title'		=> $lang->quickadveditorplus_smile_title,
		'description'	=> $lang->quickadveditorplus_smile_desc,
		'optionscode'	=> 'onoff',
		'value'		=> '0',
		'disporder'	=> '3',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_qedit',
		'title'		=> $lang->quickadveditorplus_qedit_title,
		'description'	=> $lang->quickadveditorplus_qedit_desc,
		'optionscode'	=> 'onoff',
		'value'		=> '1',
		'disporder'	=> '4',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_quickquote',
		'title'		=> $lang->quickadveditorplus_quickquote_title,
		'description'	=> $lang->quickadveditorplus_quickquote_desc,
		'optionscode'	=> 'onoff',
		'value'		=> '1',
		'disporder'	=> '5',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_autosave',
		'title'		=> $lang->quickadveditorplus_autosave_title,
		'description'	=> $lang->quickadveditorplus_autosave_desc,
		'optionscode'	=> 'onoff',
		'value'		=> '1',
		'disporder'	=> '6',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_saveamount',
		'title'		=> $lang->quickadveditorplus_saveamount_title,
		'description'	=> $lang->quickadveditorplus_saveamount_desc,
		'optionscode'	=> 'numeric',
		'value'		=> '20',
		'disporder'	=> '7',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_savetime',
		'title'		=> $lang->quickadveditorplus_savetime_title,
		'description'	=> $lang->quickadveditorplus_savetime_desc,
		'optionscode'	=> 'numeric',
		'value'		=> '15',
		'disporder'	=> '8',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_canonicallink',
		'title'		=> $lang->quickadveditorplus_canonical_title,
		'description'	=> $lang->quickadveditorplus_canonical_desc,
		'optionscode'	=> 'onoff',
		'value'		=> '1',
		'disporder'	=> '9',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_save_lang',
		'title'		=> $lang->quickadveditorplus_save_title,
		'description'	=> $lang->quickadveditorplus_save_desc,
		'optionscode'	=> 'text',
		'value'		=> $lang->quickadveditorplus_save_default,
		'disporder'	=> '10',
		'gid'		=> $groupid
	);

	$new_setting[] = array(
		'name'		=> 'quickadveditorplus_restore_lang',
		'title'		=> $lang->quickadveditorplus_restor_title,
		'description'	=> $lang->quickadveditorplus_restor_desc,
		'optionscode'	=> 'text',
		'value'		=> $lang->quickadveditorplus_restor_default,
		'disporder'	=> '11',
		'gid'		=> $groupid
	);

    $db->insert_query_multiple("settings", $new_setting);
    rebuild_settings();
}

function quickadveditorplus_is_installed()
{
	global $db;

	$query = $db->simple_select("settinggroups", "COUNT(*) as rows_n", "name = 'quickadveditorplus'");
	$rows  = $db->fetch_field($query, 'rows_n');

	return ($rows > 0);
}

function quickadveditorplus_uninstall()
{
	global $db;

	$groupid = $db->fetch_field(
		$db->simple_select('settinggroups', 'gid', "name='quickadveditorplus'"),
		'gid'
	);

	$db->delete_query('settings', 'gid=' . $groupid);
	$db->delete_query("settinggroups", "name = 'quickadveditorplus'");
	rebuild_settings();
}

function quickadveditorplus_activate()
{
	global $db;

	include_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	$new_template_global['codebutquick'] = "<link rel=\"stylesheet\" href=\"{\$mybb->asset_url}/jscripts/sceditor/themes/{\$theme['editortheme']}\" type=\"text/css\" media=\"all\" />
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1822\"></script>
{\$quickquote}
{\$quickquotesty}
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/bbcodes_sceditor.js?ver=1824\"></script>
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/sceditor/plugins/undo.js?ver=1821\"></script>
<script type=\"text/javascript\">
var partialmode = {\$mybb->settings['partialmode']},
MYBB_SMILIES = {
	{\$smilies_json}
},
opt_editor = {
	plugins: \"undo\",
	format: \"bbcode\",
	bbcodeTrim: false,
	style: \"{\$mybb->asset_url}/jscripts/sceditor/styles/jquery.sceditor.{\$theme['editortheme']}?ver=1823\",
	rtl: {\$lang->settings['rtl']},
	locale: \"mybblang\",
	width: \"100%\",
	enablePasteFiltering: true,
	autoUpdate: true,
	emoticonsEnabled: {\$emoticons_enabled},
	emoticons: {
		// Emoticons to be included in the dropdown
		dropdown: {
			{\$dropdownsmilies}
		},
		// Emoticons to be included in the more section
		more: {
			{\$moresmilies}
		},
		// Emoticons that are not shown in the dropdown but will still be converted. Can be used for things like aliases
		hidden: {
			{\$hiddensmilies}
		}
	},
	emoticonsCompat: true,
	toolbar: \"{\$basic1}{\$align}{\$font}{\$size}{\$color}{\$removeformat}{\$basic2}image,{\$email}{\$link}|video{\$emoticon}|{\$list}{\$code}quote|maximize,source\",
};
{\$editor_language}
rinvbquote = {\$rin_vbquote};

function qae_as() {
	if (MyBBEditor) {
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		link_can = document.querySelector(\"link[rel='canonical']\").href;
		if (!sc_asd) {
			sc_asd = {};
		}
		if (MyBBEditor.val() != sc_asd[link_can]) {
			if (\$.trim(MyBBEditor.val())) {
				if(!\$('#autosave').length) {
					$('<div/>', { id: 'autosave', class: 'bottom-right' }).appendTo('body');
				}
				setTimeout(function() {
					\$('#autosave').jGrowl('{\$mybb->settings['quickadveditorplus_save_lang']}', { life: 500, theme: 'jgrowl_success' });
				},200);
				sc_asd[link_can] = MyBBEditor.val();
				localStorage.setItem('sc_as', JSON.stringify(sc_asd));
			}
			else {
				if (sc_asd[link_can]) {
					delete sc_asd[link_can];
					localStorage.setItem('sc_as', JSON.stringify(sc_asd));
				}
			}
		}
	}
}

function qae_ac() {
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	link_can = document.querySelector(\"link[rel='canonical']\").href;
	if (!sc_asd) {
		sc_asd = {};
	}
	if (sc_asd[link_can]) {
		delete sc_asd[link_can];
		localStorage.setItem('sc_as', JSON.stringify(sc_asd));
	}
}

function qae_ar() {
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	if (!sc_asd) {
		sc_asd = {};
	}
	if(Object.keys(sc_asd).length > {\$mybb->settings['quickadveditorplus_saveamount']}) {
		delete sc_asd[Object.keys(sc_asd)[0]];
		localStorage.setItem('sc_as', JSON.stringify(sc_asd));
	}
}

if({\$mybb->settings['quickadveditorplus_qedit']}!=0) {
	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '.quick_edit_button', function () {
		\$.jGrowl('<img src=\"images/spinner_big.gif\" />', {theme: 'jgrowl_success'});
		ed_id = \$(this).attr('id');
		var pid = ed_id.replace( /[^0-9]/g, '');
		\$('#quickedit_'+pid).height('{\$mybb->settings['quickadveditorplus_qued_heigh']}px');
		setTimeout(function() {
			\$('#quickedit_'+pid).sceditor(opt_editor);
			if (\$('#quickedit_'+pid).sceditor('instance')) {
				\$('#quickedit_'+pid).sceditor('instance').focus();
				\$('#quickedit_'+pid).next().css( \"z-index\", \"5\" );
			}
			offset = \$('#quickedit_'+pid).next().offset().top - '{\$mybb->settings['quickadveditorplus_qued_heigh']}';
			setTimeout(function() {
				\$('html, body').animate({
					scrollTop: offset
				}, 700);
				setTimeout(function() {
					\$('#pid_'+pid).find('button[type=\"submit\"]').attr( 'id', 'quicksub_'+pid );
				},200);
				if($(\".jGrowl-notification:last-child\").length) {
					$(\".jGrowl-notification:last-child\").remove();
				}
			},200);
			if('{\$sourcemode}' != '') {
				\$('textarea[name*=\"value\"]').sceditor('instance').sourceMode(true);
			}
		},400);
	});
}

(\$.fn.on || \$.fn.live).call(\$(document), 'click', 'button[id*=\"quicksub_\"]', function () {
	ed_id = \$(this).attr('id');
	pid = ed_id.replace( /[^0-9]/g, '');
	\$('#quickedit_'+pid).sceditor('instance').updateOriginal();
});

(\$.fn.on || \$.fn.live).call(\$(document), 'click', 'input[accesskey*=\"s\"]', function () {
	if({\$mybb->settings['quickadveditorplus_autosave']}!=0) {
		qae_ac();
	}
});

\$(document).ready(function() {
	\$('#message').height('{\$mybb->settings['quickadveditorplus_qurp_heigh']}px');
	\$('#message').sceditor(opt_editor);
	MyBBEditor = $('#message').sceditor('instance');
	{\$sourcemode}
	if({\$mybb->settings['quickadveditorplus_autosave']}!=0) {
		setInterval(function() {
			qae_as();
			qae_ar();
		},{\$mybb->settings['quickadveditorplus_savetime']}*1000);

		setTimeout(function() {
			sc_asd = JSON.parse(localStorage.getItem('sc_as'));
			restitem = \"\";
			link_can = document.querySelector(\"link[rel='canonical']\").href;
			if (sc_asd) {
				restitem = sc_asd[link_can];
			}
			if (restitem) {
				var restorebut = [
					'<a class=\"sceditor-button\" title=\"{\$mybb->settings['quickadveditorplus_restore_lang']}\" onclick=\"MyBBEditor.insert(restitem);\">',
						'<div style=\"background-image: url(images/rest.png); opacity: 1; cursor: pointer;\">{\$mybb->settings['quickadveditorplus_restore_lang']}</div>',
					'</a>'
				];

				\$(restorebut.join('')).appendTo('.sceditor-group:last');
			}
		},600);
		MyBBEditor.blur(function(e) {
			if (\$.trim(MyBBEditor.val())) {
				qae_as();
			}
			else {
				qae_ac();
			}
		});
	}
});

/**********************************
 * Thread compatibility functions *
 **********************************/
if(typeof Thread !== 'undefined')
{
	var quickReplyFunc = Thread.quickReply;
	Thread.quickReply = function(e) {
		if(MyBBEditor) {
			MyBBEditor.updateOriginal();
			if({\$mybb->settings['quickadveditorplus_autosave']}!=0) {
				qae_ac();
			}
			$('form[id*=\"quick_reply_form\"]').bind('reset', function() {
				MyBBEditor.val('').emoticons(true);
			});
		}

		return quickReplyFunc.call(this, e);
	};
	Thread.multiQuotedLoaded =  function(request)
	{
		if(MyBBEditor) {
			var json = JSON.parse(request.responseText);
			if(typeof json == 'object')
			{
				if(json.hasOwnProperty(\"errors\"))
				{
					\$.each(json.errors, function(i, message)
					{
						\$.jGrowl(lang.post_fetch_error + ' ' + message, {theme:'jgrowl_error'});
					});
					return false;
				}
			}

			MyBBEditor.insert(json.message);

			Thread.clearMultiQuoted();
			\$('#quickreply_multiquote').hide();
			\$('#quoted_ids').val('all');

			\$('#message').trigger('focus');
		}
	};
};
</script>";

	$new_template_global['codebutquick_pm'] = "<link rel=\"stylesheet\" href=\"{\$mybb->asset_url}/jscripts/sceditor/themes/{\$theme['editortheme']}\" type=\"text/css\" media=\"all\" />
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1822\"></script>
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/bbcodes_sceditor.js?ver=1824\"></script>
<script type=\"text/javascript\" src=\"{\$mybb->asset_url}/jscripts/sceditor/plugins/undo.js?ver=1821\"></script>
<script type=\"text/javascript\">
var partialmode = {\$mybb->settings['partialmode']},
opt_editor = {
	plugins: \"undo\",
	format: \"bbcode\",
	bbcodeTrim: false,
	style: \"{\$mybb->asset_url}/jscripts/sceditor/styles/jquery.sceditor.{\$theme['editortheme']}?ver=1823\",
	rtl: {\$lang->settings['rtl']},
	locale: \"mybblang\",
	width: \"100%\",
	enablePasteFiltering: true,
	autoUpdate: true,
	emoticonsEnabled: {\$emoticons_enabled},
	emoticons: {
		// Emoticons to be included in the dropdown
		dropdown: {
			{\$dropdownsmilies}
		},
		// Emoticons to be included in the more section
		more: {
			{\$moresmilies}
		},
		// Emoticons that are not shown in the dropdown but will still be converted. Can be used for things like aliases
		hidden: {
			{\$hiddensmilies}
		}
	},
	emoticonsCompat: true,
	toolbar: \"{\$basic1}{\$align}{\$font}{\$size}{\$color}{\$removeformat}{\$basic2}image,{\$email}{\$link}|video{\$emoticon}|{\$list}{\$code}quote|maximize,source\",
};
{\$editor_language}

function qae_as() {
	if (MyBBEditor) {
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		link_can = location.href;
		if (!sc_asd) {
			sc_asd = {};
		}
		if (MyBBEditor.val() != sc_asd[link_can]) {
			if (\$.trim(MyBBEditor.val())) {
				if(!\$('#autosave').length) {
					$('<div/>', { id: 'autosave', class: 'bottom-right' }).appendTo('body');
				}
				setTimeout(function() {
					\$('#autosave').jGrowl('{\$mybb->settings['quickadveditorplus_save_lang']}', { life: 500 });
				},200);
				sc_asd[link_can] = MyBBEditor.val();
				localStorage.setItem('sc_as', JSON.stringify(sc_asd));
			}
			else {
				if (sc_asd[link_can]) {
					delete sc_asd[link_can];
					localStorage.setItem('sc_as', JSON.stringify(sc_asd));
				}
			}
		}
	}
}

function qae_ac() {
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	link_can = location.href;
	if (!sc_asd) {
		sc_asd = {};
	}
	if (sc_asd[link_can]) {
		delete sc_asd[link_can];
		localStorage.setItem('sc_as', JSON.stringify(sc_asd));
	}
}

function qae_ar() {
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	if (!sc_asd) {
		sc_asd = {};
	}
	if(Object.keys(sc_asd).length > {\$mybb->settings['quickadveditorplus_saveamount']}) {
		delete sc_asd[Object.keys(sc_asd)[0]];
		localStorage.setItem('sc_as', JSON.stringify(sc_asd));
	}
}

(\$.fn.on || \$.fn.live).call(\$(document), 'click', 'input[accesskey*=\"s\"]', function () {
	MyBBEditor.updateOriginal();
	if({\$mybb->settings['quickadveditorplus_autosave']}!=0) {
		qae_ac();
	}
});

(\$.fn.on || \$.fn.live).call(\$(document), 'click', 'input[name*=\"preview\"]', function () {
	MyBBEditor.updateOriginal();
});

\$(document).ready(function() {
	\$('#message').height('{\$mybb->settings['quickadveditorplus_qurp_heigh']}px');
	\$('#message').sceditor(opt_editor);
	MyBBEditor = $('#message').sceditor('instance');
	{\$sourcemode}
	if({\$mybb->settings['quickadveditorplus_autosave']}!=0) {
		setInterval(function() {
			qae_as();
			qae_ar();
		},{\$mybb->settings['quickadveditorplus_savetime']}*1000);

		setTimeout(function() {
			sc_asd = JSON.parse(localStorage.getItem('sc_as'));
			link_can = location.href;
			restitem = \"\";
			if (sc_asd) {
				restitem = sc_asd[link_can];
			}
			if (restitem) {
				var restorebut = [
					'<a class=\"sceditor-button\" title=\"{\$mybb->settings['quickadveditorplus_restore_lang']}\" onclick=\"MyBBEditor.insert(restitem);\">',
						'<div style=\"background-image: url(images/rest.png); opacity: 1; cursor: pointer;\">{\$mybb->settings['quickadveditorplus_restore_lang']}</div>',
					'</a>'
				];

				\$(restorebut.join('')).appendTo('.sceditor-group:last');
			}
		},600);
		MyBBEditor.blur(function(e) {
			if (\$.trim(MyBBEditor.val())) {
				qae_as();
			}
			else {
				qae_ac();
			}
		});
	}
});
</script>";

    foreach($new_template_global as $title => $template)
	{
		$new_template_global = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template), 'sid' => '-1', 'version' => '1801', 'dateline' => TIME_NOW);
		$db->insert_query('templates', $new_template_global);
	}

	$new_template['postbit_quickquote'] = "<div class=\"rin-qc\" style=\"display: none;\" id=\"qr_pid_{\$post['pid']}\"><span>{\$lang->postbit_button_quote}</span></div>
<script type=\"text/javascript\">
	\$(document).ready(function() {
		quick_quote({\$post['pid']},'{\$post['username']}',{\$post['dateline']});
	});
</script>";

	$new_template['usercp_qae_drafts'] = "<html>
<head>
<title>{\$mybb->settings['bbname']} - {\$lang->quickadveditorplus_page_title}</title>
{\$headerinclude}
<script type=\"text/javascript\">
\$(document).ready(function() {
	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '.remove_autosave', function (e) {
		e.preventDefault();
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		if (!sc_asd) {
			sc_asd = {};
		}
		if (sc_asd[\$(this).attr('id')]) {
			delete sc_asd[\$(this).attr('id')];
		}
		localStorage.setItem('sc_as', JSON.stringify(sc_asd));
		\$(this).parents('.as_tr').fadeOut('slow');
		if(!Object.keys(sc_asd).length) {
			if (!\$('.as_none').length) {
				\$('#sc_auto').append( '<tr class=\"as_none\"><td class=\"trow1\" colspan=\"7\">{\$lang->quickadveditorplus_any_draft}</td><tr>' );
			}
		}
	});

	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '#morelink', function (e) {
		e.preventDefault();
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		var restitem = \"\";
		link_can = \$(this).attr('href');
		if (sc_asd) {
			restitem = sc_asd[link_can];
		}
		if (!restitem) {
			restitem = \"{\$lang->quickadveditorplus_not_message}\";
		}
		heightwin = window.innerHeight*0.6;
		\$('body').append( '<div class=\"redmore\"><div style=\"overflow-y: auto;max-height: '+heightwin+'px !important; \"><table cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\"><tr><td class=\"thead\" colspan=\"2\"><div><strong>{\$lang->quickadveditorplus_message}</strong></div></td></tr><td class=\"trow1\"><textarea readonly=\"readonly\" style=\"width:99%;height: '+heightwin*0.8+'px;\" >'+restitem+'</textarea></td></table></div></div>' );
		\$('.redmore').modal();
	});

	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '.edit_autosave', function (e) {
		e.preventDefault();
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		var restitem = \"\";
		link_can = \$(this).attr('href');
		if (sc_asd) {
			restitem = sc_asd[link_can];
		}
		if (!restitem) {
			restitem = \"{\$lang->quickadveditorplus_not_message}\";
		}
		heightwin = window.innerHeight*0.6;
		\$('body').append( '<div class=\"edit\"><div style=\"overflow-y: auto;max-height: '+heightwin+'px !important; \"><table cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\"><tr><td class=\"thead\" colspan=\"2\"><div><strong>{\$lang->quickadveditorplus_edit_message}</strong></div></td></tr><td class=\"trow1\"><textarea id=\"edit_textarea\" style=\"width:99%;height: '+heightwin*0.8+'px;\" >'+restitem+'</textarea></td></table></div><button id=\"sv_edit\" style=\"margin:4px;\" ided=\"'+link_can+'\">{\$lang->quickadveditorplus_save}</button></div>' );
		\$('.edit').modal();
	});

	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '#sv_edit', function (e) {
		e.preventDefault();
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		var restitem = \"\";
		link_can = \$(this).attr('ided');
		if (!sc_asd) {
			sc_asd = {};
		}
		if (\$('#edit_textarea').val() != sc_asd[link_can]) {
			if (\$.trim(\$('#edit_textarea').val())) {
				sc_asd[link_can] = \$('#edit_textarea').val();
				localStorage.setItem('sc_as', JSON.stringify(sc_asd));
			}
			else {
				if (sc_asd[link_can]) {
					delete sc_asd[link_can];
					localStorage.setItem('sc_as', JSON.stringify(sc_asd));
				}
			}
		}
		else {
			if(!\$('#mes_no_edit').length) {
				$('<div/>', { id: 'mes_no_edit', class: 'bottom-right' }).appendTo('body');
			}
			setTimeout(function() {
				$('#mes_no_edit').jGrowl('{\$lang->quickadveditorplus_not_edit}', { life: 500 });
			},200);
			return;
		}
		location.reload();
	});

	(\$.fn.on || \$.fn.live).call(\$(document), 'click', '#remove_all', function (e) {
		e.preventDefault();
		sc_asd = JSON.parse(localStorage.getItem('sc_as'));
		localStorage.setItem('sc_as', JSON.stringify({}));
		\$(document).find('.as_tr').fadeOut('slow');
		if (!\$('.as_none').length) {
			\$('#sc_auto').append( '<tr class=\"as_none\"><td class=\"trow1\" colspan=\"7\">{\$lang->quickadveditorplus_any_draft}</td><tr>' );
		}
	});

	var i = 0;
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	if (!sc_asd) {
		sc_asd = {};
	}
	if(!Object.keys(sc_asd).length) {
		\$('#sc_auto').append( '<tr class=\"as_none\"><td class=\"trow1\" colspan=\"7\">{\$lang->quickadveditorplus_any_draft}</td><tr>' );
	}
	\$.each( sc_asd, function( key, value ) {
		i +=1;
		numtrow = 2;
		if (i % 2 == 0) { numtrow = 1; }
		if (value.length > 200) { value = value.substr(0,200) + '... <a href=\"'+key+'\" id=\"morelink\">{\$lang->quickadveditorplus_readmore}</a>'; }
		$('#sc_auto').append( '<tr class=\"as_tr\"><td class=\"trow'+numtrow+'\"><a href=\"'+key+'\"><span class=\"smalltext\">'+key.substr(key.lastIndexOf('/') + 1)+'</span></a></td><td class=\"trow'+numtrow+'\"><span class=\"smalltext\">'+value+'</span></td><td class=\"trow'+numtrow+'\" align=\"center\"><a href=\"'+key+'\" class=\"edit_autosave\"><img src=\"{\$mybb->settings['bburl']}/images/icons/pencil.png\" title=\"{\$lang->quickadveditorplus_edit}\" alt=\"{\$lang->quickadveditorplus_edit}\" /></a></td><td class=\"trow'+numtrow+'\" align=\"center\"><a href=\"'+key+'\" class=\"remove_autosave\" id=\"'+key+'\"><img src=\"{\$mybb->settings['bburl']}/images/invalid.png\" title=\"{\$lang->quickadveditorplus_delete}\" alt=\"{\$lang->quickadveditorplus_delete}\" /></a></td><tr>' );
	});
});
</script>
</head>
<body>
{\$header}
<table width=\"100%\" border=\"0\" align=\"center\">
	<tr>
		{\$usercpnav}
		<td valign=\"top\">
			<table id=\"sc_auto\" border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder no_bottom_border\">
				<thead>
					<tr>
						 <td class=\"thead\" colspan=\"4\"><strong>{\$lang->quickadveditorplus_page_title}</strong></td>
					</tr>
					<tr>
						<td class=\"tcat\" width=\"20%\" ><span class=\"smalltext\"><strong>{\$lang->quickadveditorplus_local}</strong></span></td>
						<td class=\"tcat\" width=\"70%\"><span class=\"smalltext\"><strong>{\$lang->quickadveditorplus_content}</strong></span></td>
						<td class=\"tcat\" align=\"center\" width=\"5%\"><span class=\"smalltext\"><strong>{\$lang->quickadveditorplus_edit}</strong></span></td>
						<td class=\"tcat\" align=\"center\" width=\"5%\"><span class=\"smalltext\"><strong>{\$lang->quickadveditorplus_delete}</strong></span></td>
					</tr>
				</thead>
			</table>
			<br />
			<div align=\"center\">
				<button id=\"remove_all\">{\$lang->quickadveditorplus_remove_all}</button>
			</div>
		</td>
	</tr>
</table>
{\$footer}
</body>
</html>";

	$new_template['usercp_nav_qae'] = "<script type=\"text/javascript\">
\$(document).ready(function() {
	sc_asd = JSON.parse(localStorage.getItem('sc_as'));
	if (!sc_asd) {
		sc_asd = {};
	}
	var titlangas = \"{\$lang->quickadveditorplus_page_title}\";
	if(Object.keys(sc_asd).length) {
		var titlangas = \"<strong>\" + titlangas + \" (\" + Object.keys(sc_asd).length + \")\" + \"</strong>\";
	}
	\$('#itenum').html(titlangas);
});
</script>
<tbody>
<tr>
	<td class=\"tcat tcat_menu tcat_collapse{\$collapsedimg['qaedraftlist']}\">
		<div class=\"expcolimage\"><img src=\"{\$theme['imgdir']}/collapse{\$collapsedimg['qaedraftlist']}.png\" id=\"qaedraftlist_img\" class=\"expander\" alt=\"[-]\" title=\"[-]\" /></div>
		<div><span class=\"smalltext\"><strong>{\$lang->quickadveditorplus_page_title}</strong></span></div>
	</td>
</tr>
</tbody>
<tbody style=\"{\$collapsed['qaedraftlist_e']}\" id=\"qaedraftlist_e\">
	<tr><td class=\"trow1 smalltext\"><a href=\"usercp.php?action=qae_autosave\" class=\"usercp_nav_item usercp_nav_drafts\" id=\"itenum\"></a></td></tr>
</tbody>";

    foreach($new_template as $title => $template2)
	{
		$new_template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template2), 'sid' => '-2', 'version' => '1801', 'dateline' => TIME_NOW);
		$db->insert_query('templates', $new_template);
	}

	find_replace_templatesets(
		'showthread_quickreply',
		'#' . preg_quote('</textarea>') . '#i',
		'</textarea>{$codebutquick}'
	);

	find_replace_templatesets(
		'private_quickreply',
		'#' . preg_quote('</textarea>') . '#i',
		'</textarea>{$codebutquick}'
	);

	find_replace_templatesets(
		'showthread_quickreply',
		'#' . preg_quote('<span class="smalltext">{$lang->message_note}<br />') . '#i',
		'<span class="smalltext">{$lang->message_note}<br />{$smilieinserter}'
	);

	find_replace_templatesets(
		'private_quickreply',
		'#' . preg_quote('<span class="smalltext">{$lang->message_note}<br />') . '#i',
		'<span class="smalltext">{$lang->message_note}<br />{$smilieinserter}'
	);

	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('<body>') . '#i',
		'<body>
	{$codebutquickedt}'
	);

	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('{$headerinclude}') . '#i',
		'{$headerinclude}
{$can_link}'
	);

	find_replace_templatesets(
		'postbit_classic',
		'#' . preg_quote('{$post[\'iplogged\']}') . '#i',
		'	{$post[\'quick_quote\']}
	{$post[\'iplogged\']}'
	);

	find_replace_templatesets(
		'postbit',
		'#' . preg_quote('{$post[\'iplogged\']}') . '#i',
		'	{$post[\'quick_quote\']}
	{$post[\'iplogged\']}'
	);
}

function quickadveditorplus_deactivate()
{
	global $db;
	include_once MYBB_ROOT."inc/adminfunctions_templates.php";

	$db->delete_query("templates", "title IN('codebutquick','codebutquick_pm','postbit_quickquote','usercp_qae_drafts','usercp_nav_qae')");

	find_replace_templatesets(
		'showthread_quickreply',
		'#' . preg_quote('</textarea>{$codebutquick}') . '#i',
		'</textarea>'
	);

	find_replace_templatesets(
		'private_quickreply',
		'#' . preg_quote('</textarea>{$codebutquick}') . '#i',
		'</textarea>'
	);

	find_replace_templatesets(
		'showthread_quickreply',
		'#' . preg_quote('<span class="smalltext">{$lang->message_note}<br />{$smilieinserter}') . '#i',
		'<span class="smalltext">{$lang->message_note}<br />'
	);

	find_replace_templatesets(
		'private_quickreply',
		'#' . preg_quote('<span class="smalltext">{$lang->message_note}<br />{$smilieinserter}') . '#i',
		'<span class="smalltext">{$lang->message_note}<br />'
	);

	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('<body>
	{$codebutquickedt}') . '#i',
		'<body>'
	);

	find_replace_templatesets(
		'showthread',
		'#' . preg_quote('{$headerinclude}
{$can_link}') . '#i',
		'{$headerinclude}'
	);

	find_replace_templatesets(
		'postbit_classic',
		'#' . preg_quote('	{$post[\'quick_quote\']}
	{$post[\'iplogged\']}') . '#i',
		'{$post[\'iplogged\']}'
	);

	find_replace_templatesets(
		'postbit',
		'#' . preg_quote('	{$post[\'quick_quote\']}
	{$post[\'iplogged\']}') . '#i',
		'{$post[\'iplogged\']}'
	);
}

$plugins->add_hook('global_start', 'advedtplus_cache_codebutquick');
function advedtplus_cache_codebutquick()
{
	global $templatelist, $mybb;

	if (isset($templatelist)) {
		$templatelist .= ',';
	}

	if (THIS_SCRIPT == 'showthread.php') {
		if($mybb->settings['quickadveditorplus_smile'] != 0 && $mybb->settings['quickadveditorplus_quickquote'] != 1) {
			$templatelist .= 'codebutquick,smilieinsert,smilieinsert_smilie,smilieinsert_getmore';
		}
		elseif($mybb->settings['quickadveditorplus_quickquote'] != 0 && $mybb->settings['quickadveditorplus_smile'] != 1) {
			$templatelist .= 'codebutquick,postbit_quickquote';
		}
		elseif($mybb->settings['quickadveditorplus_quickquote'] != 0 && $mybb->settings['quickadveditorplus_smile'] != 0) {
			$templatelist .= 'codebutquick,postbit_quickquote,smilieinsert,smilieinsert_smilie,smilieinsert_getmore';
		}
		else {
			$templatelist .= 'codebutquick';
		}
	}
	if (THIS_SCRIPT == 'private.php') {
		if($mybb->settings['quickadveditorplus_smile'] != 0) {
			$templatelist .= 'codebutquick_pm,smilieinsert,smilieinsert_smilie,smilieinsert_getmore';
		}
		else {
			$templatelist .= 'codebutquick_pm';
		}
	}
	if (THIS_SCRIPT == 'usercp.php') {
		if($mybb->settings['quickadveditorplus_autosave'] != 0) {
			$templatelist .= 'usercp_qae_drafts,usercp_nav_qae';
		}
	}
}

function mycode_inserter_quick($smilies = true)
{
	global $db, $mybb, $theme, $templates, $lang, $smiliecache, $cache;

	$editor_lang_strings = array(
		"editor_bold" => "Bold",
		"editor_italic" => "Italic",
		"editor_underline" => "Underline",
		"editor_strikethrough" => "Strikethrough",
		"editor_subscript" => "Subscript",
		"editor_superscript" => "Superscript",
		"editor_alignleft" => "Align left",
		"editor_center" => "Center",
		"editor_alignright" => "Align right",
		"editor_justify" => "Justify",
		"editor_fontname" => "Font Name",
		"editor_fontsize" => "Font Size",
		"editor_fontcolor" => "Font Color",
		"editor_removeformatting" => "Remove Formatting",
		"editor_cut" => "Cut",
		"editor_cutnosupport" => "Your browser does not allow the cut command. Please use the keyboard shortcut Ctrl/Cmd-X",
		"editor_copy" => "Copy",
		"editor_copynosupport" => "Your browser does not allow the copy command. Please use the keyboard shortcut Ctrl/Cmd-C",
		"editor_paste" => "Paste",
		"editor_pastenosupport" => "Your browser does not allow the paste command. Please use the keyboard shortcut Ctrl/Cmd-V",
		"editor_pasteentertext" => "Paste your text inside the following box:",
		"editor_pastetext" => "PasteText",
		"editor_numlist" => "Numbered list",
		"editor_bullist" => "Bullet list",
		"editor_undo" => "Undo",
		"editor_redo" => "Redo",
		"editor_rows" => "Rows:",
		"editor_cols" => "Cols:",
		"editor_inserttable" => "Insert a table",
		"editor_inserthr" => "Insert a horizontal rule",
		"editor_code" => "Code",
		"editor_width" => "Width (optional):",
		"editor_height" => "Height (optional):",
		"editor_insertimg" => "Insert an image",
		"editor_email" => "E-mail:",
		"editor_insertemail" => "Insert an email",
		"editor_url" => "URL:",
		"editor_insertlink" => "Insert a link",
		"editor_unlink" => "Unlink",
		"editor_more" => "More",
		"editor_insertemoticon" => "Insert an emoticon",
		"editor_videourl" => "Video URL:",
		"editor_videotype" => "Video Type:",
		"editor_insert" => "Insert",
		"editor_insertyoutubevideo" => "Insert a YouTube video",
		"editor_currentdate" => "Insert current date",
		"editor_currenttime" => "Insert current time",
		"editor_print" => "Print",
		"editor_viewsource" => "View source",
		"editor_description" => "Description (optional):",
		"editor_enterimgurl" => "Enter the image URL:",
		"editor_enteremail" => "Enter the e-mail address:",
		"editor_enterdisplayedtext" => "Enter the displayed text:",
		"editor_enterurl" => "Enter URL:",
		"editor_enteryoutubeurl" => "Enter the YouTube video URL or ID:",
		"editor_insertquote" => "Insert a Quote",
		"editor_invalidyoutube" => "Invalid YouTube video",
		"editor_dailymotion" => "Dailymotion",
		"editor_metacafe" => "MetaCafe",
		"editor_mixer" => "Mixer",
		"editor_vimeo" => "Vimeo",
		"editor_youtube" => "Youtube",
		"editor_facebook" => "Facebook",
		"editor_liveleak" => "LiveLeak",
		"editor_insertvideo" => "Insert a video",
		"editor_php" => "PHP",
		"editor_maximize" => "Maximize"
	);
	$editor_language = "(function ($) {\n$.sceditor.locale[\"mybblang\"] = {\n";

	$editor_languages_count = count($editor_lang_strings);
	$i = 0;
	foreach($editor_lang_strings as $lang_string => $key)
	{
		$i++;
		$js_lang_string = str_replace("\"", "\\\"", $key);
		$string = str_replace("\"", "\\\"", $lang->$lang_string);
		$editor_language .= "\t\"{$js_lang_string}\": \"{$string}\"";

		if($i < $editor_languages_count)
		{
			$editor_language .= ",";
		}

		$editor_language .= "\n";
	}

	$editor_language .= "}})(jQuery);";

	if(defined("IN_ADMINCP"))
	{
		global $page;
		$codeinsertquick = $page->build_codebuttons_editor($editor_language, $smilies);
	}
	else
	{
		// Smilies
		$emoticon = "";
		$emoticons_enabled = "false";
		if($smilies && $mybb->settings['smilieinserter'] != 0 && $mybb->settings['smilieinsertercols'] && $mybb->settings['smilieinsertertot'])
		{
			$emoticon = ",emoticon";
			$emoticons_enabled = "true";

			if(!$smiliecache)
			{
				if(!is_array($smilie_cache))
				{
					$smilie_cache = $cache->read("smilies");
				}
				foreach($smilie_cache as $smilie)
				{
					if($smilie['showclickable'] != 0)
					{
						$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
						$smiliecache[$smilie['sid']] = $smilie;
					}
				}
			}

			unset($smilie);

			if(is_array($smiliecache))
			{
				reset($smiliecache);

				$smilies_json = $dropdownsmilies = $moresmilies = $hiddensmilies = "";
				$i = 0;

				foreach($smiliecache as $smilie)
				{
					$finds = explode("\n", $smilie['find']);
					$finds_count = count($finds);

					// Only show the first text to replace in the box
					$smilie['find'] = $finds[0];

					$find = htmlspecialchars_uni($smilie['find']);
					$image = htmlspecialchars_uni($smilie['image']);
					$smilies_json .= '"'.$mybb->asset_url.'/'.$image.'": "'.$find.'",';
					if($i < $mybb->settings['smilieinsertertot'])
					{
						$dropdownsmilies .= '"'.$find.'": "'.$mybb->asset_url.'/'.$image.'",';
					}
					else
					{
						$moresmilies .= '"'.$find.'": "'.$mybb->asset_url.'/'.$image.'",';
					}

					for($j = 1; $j < $finds_count; ++$j)
					{
						$find2 = htmlspecialchars_uni($finds[$j]);
						$hiddensmilies .= '"'.$find.'": "'.$mybb->asset_url.'/'.$image.'",';
					}
					++$i;
				}
			}
		}

		$basic1 = $basic2 = $align = $font = $size = $color = $removeformat = $email = $link = $list = $code = $sourcemode = $quickquote = $rin_vbquote = $quickquotesty = "";

		if($mybb->settings['allowbasicmycode'] == 1)
		{
			$basic1 = "bold,italic,underline,strike|";
			$basic2 = "horizontalrule,";
		}

		if($mybb->settings['allowalignmycode'] == 1)
		{
			$align = "left,center,right,justify|";
		}

		if($mybb->settings['allowfontmycode'] == 1)
		{
			$font = "font,";
		}

		if($mybb->settings['allowsizemycode'] == 1)
		{
			$size = "size,";
		}

		if($mybb->settings['allowcolormycode'] == 1)
		{
			$color = "color,";
		}

		if($mybb->settings['allowfontmycode'] == 1 || $mybb->settings['allowsizemycode'] == 1 || $mybb->settings['allowcolormycode'] == 1)
		{
			$removeformat = "removeformat|";
		}

		if($mybb->settings['allowemailmycode'] == 1)
		{
			$email = "email,";
		}

		if($mybb->settings['allowlinkmycode'] == 1)
		{
			$link = "link,unlink";
		}

		if($mybb->settings['allowlistmycode'] == 1)
		{
			$list = "bulletlist,orderedlist|";
		}

		if($mybb->settings['allowcodemycode'] == 1)
		{
			$code = "code,php,";
		}

		if($mybb->user['sourceeditor'] == 1 && $mybb->user['showquickreply'] == 1)
		{
			$sourcemode = "MyBBEditor.sourceMode(true);";
		}

		if($mybb->settings['quickadveditorplus_quickquote'] == 1)
		{
			$quickquote = "<script type=\"text/javascript\" src=\"".$mybb->asset_url."/jscripts/Thread.quickquote.js?ver=".QAEP_PLUGIN_VER."\"></script>";
			$quickquotesty = "<link rel=\"stylesheet\" href=\"".$mybb->asset_url."/jscripts/quickquote.css?ver=".QAEP_PLUGIN_VER."\" type=\"text/css\" />";
		}
		
		$plu_vb = $cache->read("plugins");
		if($plu_vb['active']['vbquote']) {
			$rin_vbquote = 1;
		}
		else {
			$rin_vbquote = 0;
		}

		if (!strpos($_SERVER['PHP_SELF'],'private.php')) {
			eval("\$codeinsertquick = \"".$templates->get("codebutquick")."\";");
		}
		else {
			eval("\$codeinsertquick = \"".$templates->get("codebutquick_pm")."\";");
		}
	}

	return $codeinsertquick;
}

$plugins->add_hook("showthread_start", "codebuttonsquick");
function codebuttonsquick () {

	global $smilieinserter, $codebutquick, $codebutquickedt, $mybb;

	$codebutquick = mycode_inserter_quick();
	$smilieinserter = $codebutquickedt = '';
	if($mybb->settings['quickadveditorplus_smile'] != 0) {
		$smilieinserter = build_clickable_smilies();
	}
	if(($mybb->settings['quickreply'] == 0) || ($mybb->user['showquickreply'] == 0)) {
		$codebutquickedt = mycode_inserter_quick();
	}
}

$plugins->add_hook("private_start", "codebuttonsquick_pm");
function codebuttonsquick_pm () {

	global $smilieinserter, $codebutquick, $mybb;

	$codebutquick = mycode_inserter_quick();
	$smilieinserter = '';
	if($mybb->settings['quickadveditorplus_smile'] != 0) {
		$smilieinserter = build_clickable_smilies();
	}
}

$plugins->add_hook('postbit', 'qaep_quickquote_postbit');

function canonical($link)
{
    global $settings, $plugins, $can_link;

    if($link)
    {
        $plugins->add_hook('showthread_start', 'google_seo_meta_output');
        $can_link = "<link rel=\"canonical\" href=\"{$settings['bburl']}/$link\" />";
    }
}

function qaep_quickquote_postbit(&$post)
{
	global $templates, $lang, $mybb, $postcounter, $tid, $page;

	$post['quick_quote'] = '';
	if($mybb->settings['quickadveditorplus_quickquote'] != 0) {
		eval("\$post['quick_quote'] = \"" . $templates->get("postbit_quickquote") . "\";");
	}

	if($mybb->settings['quickadveditorplus_canonicallink'] != 0) {
		if (($postcounter - 1) % $mybb->settings['postsperpage'] == "0") {
			if($tid > 0)
			{
				if($page > 1)
				{
					canonical(get_thread_link($tid, $page));
				}

				else
				{
					canonical(get_thread_link($tid));
				}
			}
		}
	}
}

global $settings;

if ($settings['quickadveditorplus_autosave']) {
    $plugins->add_hook('usercp_start', 'QAEP_autosave_list');
}
function QAEP_autosave_list()
{
	global $mybb, $lang, $theme, $templates, $headerinclude, $header, $footer, $usercpnav;

	if ($mybb->input['action'] == 'qae_autosave') {
		if (!$lang->quickadveditorplus) {
			$lang->load('quickadveditorplus');
		}

		add_breadcrumb($lang->nav_usercp, 'usercp.php');
		add_breadcrumb($lang->quickadveditorplus_page_title, 'usercp.php?action=qae_autosave');

		eval("\$content = \"".$templates->get('usercp_qae_drafts')."\";");
		output_page($content);
	}
}

if ($settings['quickadveditorplus_autosave']) {
    $plugins->add_hook('usercp_menu', 'QAEP_ucpmenu', 20);
}
function QAEP_ucpmenu()
{
	global $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;

	if (!$lang->quickadveditorplus) {
		$lang->load('quickadveditorplus');
	}

    eval("\$usercpmenu .= \"".$templates->get('usercp_nav_qae')."\";");
}
?>
