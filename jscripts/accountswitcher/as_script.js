/**
 * Enhanced Account Switcher for MyBB 1.8
 * Copyright (c) 2012-2018 doylecc
 * http://mybbplugins.tk
 *
 *
 */

$(function()
{
	$(".noscript").attr("title", "");
	$("#postswitch").attr("title", "");
	var quick_button = reply_button;
	var as_button = as_desc_button;
	var submit_button = $("input[name=\'submit\']").val();

	// If user can use the account switcher
	if(account_id !== 0 && can_switch === 1 && (AS_SCRIPT == "showthread.php" || AS_SCRIPT == "newthread.php" || AS_SCRIPT == "newreply.php")) {
		// Show the current username on the submit button
		$("#quick_reply_submit").val(quick_button + as_button + account_name);
		$("input[name=\'submit\']").val(submit_button + as_button + account_name);
	}

	// If clicking the switch link do the swap and reload the page
	$(".switchlink").on("click", function() {
		var id = $(this).attr("id");
		var uid = parseInt(id.replace( /[^\d.]/g, ""));
		$.ajax({
			url: "xmlhttp.php?uid=" + uid + "&switchuser=1&my_post_key=" + user_post_key,
			type: "post"
        })
		.done(function(data){
            if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
                history.go(0);
            } else {
                location.reload();
            }
		});
	});

	// If clicking the switch link in postbit do the swap and reload the page
	$(".switchpb").on("click", function() {
		var id = $(this).attr("id");
		var switchid = id.replace( /(.*?)_switch_/g, "");
		var pbuid = parseInt(switchid.replace( /[^\d.]/g, ""));
		$.ajax({
			url: "xmlhttp.php?uid=" + pbuid + "&switchuser=1&my_post_key=" + user_post_key,
			type: "post"
        })
		.done(function(data){
            if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
                history.go(0);
            } else {
                location.reload();
            }
		});
	});

	// If clicking the switch link in memberlist do the swap and reload the page
	$(".switchml").on("click", function() {
		var id = $(this).attr("id");
		var switchid = id.replace( /ml_switch_/g, "");
		var mluid = parseInt(switchid.replace( /[^\d.]/g, ""));
		$.ajax({
			url: "xmlhttp.php?uid=" + mluid + "&switchuser=1&my_post_key=" + user_post_key,
			type: "post"
        })
		.done(function(data){
            if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
                history.go(0);
            } else {
                location.reload();
            }
		});
	});

	// If reload page after dropdown switch is enabled do the swap and reload the page
	if (dropdown_reload == 1) {
		$("#postswitch").on("change", function() {
			var uid = parseInt($(this).val());
			$.ajax({
				url: "xmlhttp.php?uid=" + uid + "&switchuser=1&my_post_key=" + user_post_key,
				type: "post"
            })
			.done(function(data){
                location.hash="#switch";
                if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
                    history.go(0);
                } else {
                    location.reload();
                }
			});
		});
	} else {
		// If reload page after dropdown switch is disabled, do the swap, load the new post code and show button for page refresh
		$("#postswitch").on("change", function() {
			var accountname = $("#postswitch option:selected").text();
			var switch_success = switch_success_text + accountname + "!";
			account_id = $("#postswitch").val();
			$("#quick_reply_submit").val(quick_button + as_button + accountname);
			$("input[name=\'submit\']").val(submit_button + as_button + accountname);
			postcode = $("input[name=\'my_post_key\']").val();
			var uid = parseInt($(this).val());
			$.ajax({
				url: "xmlhttp.php?uid=" + uid + "&switchuser=1&my_post_key=" + postcode + "",
				type: "post"
            })
            .done(function(data){
                if(data.postcode.match(/[0-9a-f]+/gi)) {
                    $("input[name=\'my_post_key\']").val(data.postcode);
                    if(typeof $.jGrowl == "function") {
                        $("div.jGrowl").jGrowl("close");
                        $.jGrowl(switch_success);
                    }
                }
                else {
                    location.hash="#switch";
                    if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
                        history.go(0);
                    } else {
                        location.reload();
                    }
                }
			});
			$("#as_reload").fadeIn("fast");
		});
	}

	// Click the reload arrow and reload the page
	$("#as_reload").on("click", function() {
		location.hash="#switch";
		if (navigator.userAgent.match(/msie/i) || !!navigator.userAgent.match(/Trident.*rv\:11\./) || (navigator.userAgent.match(/opera/i))) {
			history.go(0);
			$("#postswitch").text($("#postswitch option:selected").text());
		} else {
			location.reload();
			$("#postswitch").text($("#postswitch option:selected").text());
		}
	});

	// Reset the form after quick reply submit
	$("#quick_reply_submit").on("click", function() {
		$("#postswitch option[value=\'" + account_id + "\']").attr("selected", true);
		$("#message, #signatue").html("");
	});

	// Click the link in postbit and show account list in postbit
	$("[id^=aj_postuser_]").on("click", function() {
		var id = $(this).attr("id");
		var postid = parseInt(id.replace( /[^\d.]/g, ""));
		$("#aj_postbit_"+postid).fadeIn("slow");
		$(this).fadeOut("slow");
	});

	// Click account list container to hide account list in postbit
	$("[id^=aj_postbit_]").on("click", function() {
		var id = $(this).attr("id");
		var postid = parseInt(id.replace( /[^\d.]/g, ""));
		$("#aj_postuser_"+postid).fadeIn("slow");
		$(this).fadeOut("slow");
	});

	// Click the link in memberlist and show account list in userbit
	$("[id^=aj_user_]").on("click", function() {
		var id = $(this).attr("id");
		var useruid = parseInt(id.replace( /[^\d.]/g, ""));
		$("#aj_memberbit_"+useruid).fadeIn("slow");
		$(this).fadeOut("slow");
	});

	// Click memberlist container to hide account list in userbit
	$("[id^=aj_memberbit_]").on("click", function() {
		var id = $(this).attr("id");
		var useruid = parseInt(id.replace( /[^\d.]/g, ""));
		$("#aj_user_"+useruid).fadeIn("slow");
		$(this).fadeOut("slow");
	});

	// Hide all account lists in memberlist and postbit on doubleclick
	$("body").on("dblclick touchmove", function() {
		$("[id^=aj_user_]").fadeIn("slow");
		$("[id^=aj_memberbit_]").fadeOut("slow");
		$("[id^=aj_postuser_]").fadeIn("slow");
		$("[id^=aj_postbit_]").fadeOut("slow");
	});

	// Id share option in user cp is checked, hide username and password input
	if($('#shareuser').length) {
		if($('#shareuser').is(":checked") === true) {
			$("#as_username").fadeOut("fast");
			$("#as_password").fadeOut("fast");
			$('#accountswitcher_username').fadeOut("fast");
			$('input:password[name=password]').fadeOut("fast");
			$('.select2-container').fadeOut("fast");
	    } else {
			$("#as_username").fadeIn("fast");
			$("#as_password").fadeIn("fast");
			$('#accountswitcher_username').fadeIn("fast");
			$('input:password[name=password]').fadeIn("fast");
			$('.select2-container').fadeIn("fast");
		}

		// Select radio buttons for attach or share account
		$('input:radio[name=select]').on("click", function() {
			if($('#shareuser').is(":checked") === true) {
				// Hide username & password input
				$("#as_username").fadeOut("fast");
				$("#as_password").fadeOut("fast");
				$('#accountswitcher_username').fadeOut("fast");
				$('input:password[name=password]').fadeOut("fast");
				$('.select2-container').fadeOut("fast");
			} else {
				// Show username & password input
				$("#as_username").fadeIn("fast");
				$("#as_password").fadeIn("fast");
				$('#accountswitcher_username').fadeIn("fast");
				$('input:password[name=password]').fadeIn("fast");
				$('.select2-container').fadeIn("fast");
			}
		});
	}
});
