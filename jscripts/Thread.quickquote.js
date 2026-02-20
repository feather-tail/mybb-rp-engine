isWebkit = 'WebkitAppearance' in document.documentElement.style;
$(document).ready(function() {
	if ($('#quick_reply_form').length) {
		$(document).on('mouseup touchend', function(){
			var $me = $(event.target);
			var hide_reply_btn = true;
			var pid = '';

			if (!$me.hasClass('post')) {
				$me = $me.parents('.post');
			}

			if ($me && $me.length) {
				pid = $me[0].id.split('_')[1];
				if ($('#pid_' + pid + '').has('form').length == 0) {
					var selection = window.getSelection();
					if (selection.rangeCount > 0) {
						var nowselect = selection.getRangeAt(0);
						if ($.trim(window.getSelection().toString()) && beforeselect!=nowselect) {
							beforeselect = nowselect;
							if (elementContainsSelection($me.find('.post_body')[0])) {
								range = selection.getRangeAt(0),
								rect = range.getBoundingClientRect();
								$elm = $('#qr_pid_' + pid + '').show();
								$elm.css({
									'top': (window.scrollY + rect.top + rect.height + 6) + 'px',
									'left': (getposition().left - $elm.outerWidth() + 10) + 'px'
								});
								hide_reply_btn = false;
							}
						}
					}
				}
			}

			if (hide_reply_btn) {
				$('#qr_pid_' + pid + '').hide();
			}
		});
	}
});

// Credits: http://stackoverflow.com/a/8340432
function isOrContains(node, container) {
	while (node) {
		if (node === container) {
			return true;
		}
		node = node.parentNode;
	}
	return false;
}

function elementContainsSelection(el) {
    var sel;
    if (window.getSelection) {
        sel = window.getSelection();
        if (sel.rangeCount > 0) {
            for (var i = 0; i < sel.rangeCount; ++i) {
                if (!isOrContains(sel.getRangeAt(i).commonAncestorContainer, el)) {
                    return false;
                }
            }
            return true;
        }
    } else if ( (sel = document.selection) && sel.type != "Control") {
        return isOrContains(sel.createRange().parentElement(), el);
    }
    return false;
}

// Credits: https://stackoverflow.com/a/1589912
 function getposition() {
	var markerTextChar = "\ufeff";
	var markerTextCharEntity = "&#xfeff;";

	var markerEl, markerId = "sel_" + new Date().getTime() + "_" + Math.random().toString().substr(2);

	var position = {};

	var sel, range;

	if (document.selection && document.selection.createRange) {
		// Clone the TextRange and collapse
		range = document.selection.createRange().duplicate();
		range.collapse(false);

		// Create the marker element containing a single invisible character by creating literal HTML and insert it
		range.pasteHTML('<span id="' + markerId + '" style="position: relative;">' + markerTextCharEntity + '</span>');
		markerEl = document.getElementById(markerId);
	} else if (window.getSelection) {
		sel = window.getSelection();

		if (sel.getRangeAt) {
			range = sel.getRangeAt(0).cloneRange();
		} else {
			// Older WebKit doesn't have getRangeAt
			range = document.createRange();
			range.setStart(sel.anchorNode, sel.anchorOffset);
			range.setEnd(sel.focusNode, sel.focusOffset);

			// Handle the case when the selection was selected backwards (from the end to the start in the
			// document)
			if (range.collapsed !== sel.isCollapsed) {
				range.setStart(sel.focusNode, sel.focusOffset);
				range.setEnd(sel.anchorNode, sel.anchorOffset);
			}
		}

		range.collapse(false);

		// Create the marker element containing a single invisible character using DOM methods and insert it
		markerEl = document.createElement("span");
		markerEl.id = markerId;
		markerEl.appendChild( document.createTextNode(markerTextChar) );
		range.insertNode(markerEl);
	}

	if (markerEl) {

			// Find markerEl position http://www.quirksmode.org/js/findpos.html
		var obj = markerEl;
		var left = 0, top = 0;
		do {
			left += obj.offsetLeft;
			top += obj.offsetTop;
		} while (obj = obj.offsetParent);

			// Move the button into place.
			// Substitute your jQuery stuff in here

			position['left'] = left;
			position['top'] = top;

			markerEl.parentNode.removeChild(markerEl);

			return position;
	}
}

var beforeselect = null;
function quick_quote(pid, username, dateline) {
	if ($('#quick_reply_form').length) {
		$('body:not("#pid_' + pid + '")').click(function (e){
			if (!$.trim(window.getSelection().toString())){
				$('#qr_pid_' + pid + '').hide();
			}
		});
		$('#qr_pid_' + pid + '').click(function (e){
			e.preventDefault();
			setTimeout(function() {
				if (elementContainsSelection(document.getElementById('pid_' + pid + ''))) {
					Thread.quickQuote(pid,'' + username + '',dateline);
					$('#qr_pid_' + pid + '').hide();
					var sel = window.getSelection ? window.getSelection() : document.selection;
					if (sel) {
						if (sel.removeAllRanges) {
							sel.removeAllRanges();
						} else if (sel.empty) {
							sel.empty();
						}
					}
				}
				else {
					$('#qr_pid_' + pid + '').hide();
				}
			},200);
		})		
	}
}

// Credits: http://mods.mybb.com/view/quickquote
Thread.quickQuote = function(pid, username, dateline)
{
	if(isWebkit || window.getSelection().toString().trim()) {
		var sel = window.getSelection();
		var userSelection = sel.getRangeAt(0).cloneContents();
		if (parseInt(rinvbquote)) {
			var	quoteText = "[quote="+username+";"+pid+"]\n";
		}
		else {
			var quoteText = "[quote='" + username + "' pid='" + pid + "' dateline='" + dateline + "']\n";
		}

		var parentNode = sel.getRangeAt(0).commonAncestorContainer;
		while (typeof parentNode.tagName == "undefined") {
			if (parentNode.parentNode) {
				parentNode = parentNode.parentNode;
			} else	break;
		}
		quoteText += Thread.domToBB(userSelection, MYBB_SMILIES, parentNode, 1);
		quoteText += "\n[/quote]\n";

		delete userSelection;

		Thread.updateMessageBox(quoteText);
	}
}

Thread.updateMessageBox = function(message)
{
	MyBBEditor.insert(message);
	setTimeout(function() {
		offset = $('#quickreply_e').offset().top - 60;
		setTimeout(function() {
			$('html, body').animate({
				scrollTop: offset
			}, 700);
		},200);
	},100);	
}

Thread.RGBtoHex = function (R,G,B) {return Thread.toHex(R)+Thread.toHex(G)+Thread.toHex(B)}
Thread.toHex = function(N)
{
	if (N==null) return "00";
	N=parseInt(N); if (N==0 || isNaN(N)) return "00";
	N=Math.max(0,N); N=Math.min(N,255); N=Math.round(N);
	return "0123456789ABCDEF".charAt((N-N%16)/16)
			+ "0123456789ABCDEF".charAt(N%16);
}

Thread.textNodeSpanToBB = function(spanEl)
{
	var openTag = '';
	var content = '';
	var closeTag = '';
	var compStyles = window.getComputedStyle(spanEl, null);

	if(compStyles.getPropertyValue("text-decoration") == "underline")
	{
		openTag = "[u]" + openTag;
		closeTag = closeTag + "[/u]";
	}
	if(compStyles.getPropertyValue("font-weight") > 400 || compStyles.getPropertyValue("font-weight") == "bold")
	{
		openTag = "[b]" + openTag;
		closeTag = closeTag + "[/b]";
	}
	if(compStyles.getPropertyValue("font-style") == "italic")
	{
		openTag = "[i]" + openTag;
		closeTag = closeTag + "[/i]";
	}
	var colourVal = Thread.normaliseColour(compStyles.getPropertyValue("color"));
	var post_colour = Thread.normaliseColour($('.post_body').css('color'));
	if (post_colour != colourVal) {
		openTag = "[color=" + colourVal + "]" + openTag;
		closeTag = closeTag + "[/color]";
	}

	content = spanEl.childNodes[0].data.replace(/[\n\t]+/,'');

	if (content) {
		return openTag + content + closeTag;
	} else	return '';
}

Thread.normaliseColour = function(colourStr) {
	var match;

	colourStr = colourStr || '#000';

	// rgb(n,n,n);
	if ((match = colourStr.match(/rgb\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3})\)/i))) {
		return '#' + Thread.RGBtoHex(match[1], match[2], match[3]);
	}

	// rgba(n,n,n,f.p);
	// Strip transparency component (f.p).
	if ((match = colourStr.match(/rgba\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3}),\s*?(\d*\.?\d+\s*)\)/i))) {
		return '#' + Thread.RGBtoHex(match[1], match[2], match[3]);
	}

	// expand shorthand
	if ((match = colourStr.match(/#([0-f])([0-f])([0-f])\s*?$/i))) {
		return '#' +
		       match[1] + match[1] +
		       match[2] + match[2] +
		       match[3] + match[3];
	}

	return colourStr;
}

Thread.domToBB = function(domEl, smilies, parentNode, depth)
{
	var output = "";
	var childNode;
	var openTag;
	var content;
	var closeTag;

	for(var i = 0 ; i < domEl.childNodes.length ; i++)
	{
		childNode = domEl.childNodes[i];
		openTag = "";
		content = "";
		closeTag = "";
		var clonedNode = null;
		var newSpan = null;

		if(typeof childNode.tagName == "undefined")
		{
			switch(childNode.nodeName)
			{
				case '#text':
					if (depth == 1 && typeof parentNode.tagName !== "undefined") {
						// Add the cloned text node to the document invisibly under
						// its rightful parent node, so that the call to
						// window.getComputedStyle() in textNodeSpanToBB() works.
						newSpan = document.createElement('span');
						clonedNode = childNode.cloneNode();
						newSpan.appendChild(clonedNode);
						newSpan.style.display = 'none';
						parentNode.appendChild(newSpan);
						output += Thread.textNodeSpanToBB(newSpan);
						newSpan.removeChild(clonedNode);
						parentNode.removeChild(newSpan);
					} else {
						output += childNode.data.replace(/[\n\t]+/,'');
					}
					break;
				default:
					// do nothing
					break;
			}
		}
		else
		{
			switch(childNode.tagName)
			{
				case "SPAN":
					// check style attributes
					switch(true)
					{
						case childNode.style.textDecoration == "underline":
							openTag = "[u]";
							closeTag = "[/u]";
							break;
						case childNode.style.fontWeight > 0:
						case childNode.style.fontWeight == "bold":
							openTag = "[b]";
							closeTag = "[/b]";
							break;
						case childNode.style.fontStyle == "italic":
							openTag = "[i]";
							closeTag = "[/i]";
							break;
						case childNode.style.fontFamily != "":
							openTag = "[font=" + childNode.style.fontFamily + "]";
							closeTag = "[/font]";
							break;
						case childNode.style.fontSize != "":
							openTag = "[size=" + childNode.style.fontSize + "]";
							closeTag = "[/size]";
							break;
						case childNode.style.color != "":
							if(childNode.style.color.indexOf('rgb') != -1)
							{
								var rgb = childNode.style.color.replace("rgb(","").replace(")","").split(",");
								var hex = "#"+Thread.RGBtoHex(parseInt(rgb[0]) , parseInt(rgb[1]) , parseInt(rgb[2]));
							}
							else
							{
								var hex = childNode.style.color;
							}
							openTag = "[color=" + hex + "]";
							closeTag = "[/color]";
							break;
					}
					break;
				case "STRONG":
				case "B":
					openTag = "[b]";
					closeTag = "[/b]";
					break;
				case "EM":
				case "I":
					openTag = "[i]";
					closeTag = "[/i]";
					break;
				case "U":
					openTag = "[u]";
					closeTag = "[/u]";
					break;
				case "IMG":
					if(smilies[childNode.src])
					{
						openTag ="";
						content = smilies[childNode.src];
						closeTag = "";
					}
					else
					{
						openTag ="[img]";
						content = childNode.src;
						closeTag = "[/img]";
					}
					break;
				case "A":
					switch(true)
					{
						case childNode.href.indexOf("mailto:") == 0:
							openTag = "[email=" + childNode.href.replace("mailto:","") + "]";
							closeTag = "[/email]";
							break;
						default:
							openTag = "[url=" + childNode.href + "]";
							closeTag = "[/url]";
							break;
					}
					break;
				case "OL":
					openTag = "[list=" + childNode.type + "]";
					closeTag = "\n[/list]";
					break;
				case "UL":
					openTag = "[list]";
					closeTag = "\n[/list]";
					break;
				case "LI":
					openTag = "\n[*]";
					closeTag = "";
					break;
				case "BLOCKQUOTE":
					childNode.removeChild(childNode.firstChild);
					openTag = "[quote]\n";
					closeTag = "\n[/quote]";
					break;
				case "DIV":
					if(childNode.style.textAlign)
					{
						openTag = "[align="+childNode.style.textAlign+"]\n";
						closeTag = "\n[/align]\n";
					}

					switch(childNode.className)
					{
						case "codeblock":
							openTag = "[code]\n";
							closeTag = "\n[/code]";
							childNode.removeChild(childNode.getElementsByTagName("div")[0]);
							break;
						case "codeblock phpcodeblock":
							var codeTag = childNode.getElementsByTagName("code")[0];
							childNode.removeChild(childNode.getElementsByTagName("div")[0]);
							openTag = "[php]\n";
							if(codeTag.innerText)
							{
								content = codeTag.innerText;
							}
							else
							{
								//content = codeTag.textContent;
								content = codeTag.innerHTML.replace(/<br([^>]*)>/gi,"\n").replace(/<([^<]+)>/gi,'').replace(/&nbsp;/gi,' ');
							}
							closeTag = "\n[/php]";
							break;
					}
					break;
				case "P":
					closeTag = "\n\n";
					break;
				case "BR":
					closeTag = "\n"
					break;
			}
			output += openTag + content;

			if(content == "" && childNode.childNodes && childNode.childNodes.length > 0)
			{
				output += Thread.domToBB(childNode, smilies, parentNode, depth+1);
			}

			output += closeTag;
		}
	}

	return output;
}