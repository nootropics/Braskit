/*
 * PlainIB.js - Copyright (c) 2013 Plainboards.org
 *
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://www.wtfpl.net/ for more details.
 */

//
// Cookie crap
//

function Cookie(options) {
	var expires = 86400 * 365;

	if (typeof options === "object" && expires in options) {
		var date = new Date();
		expires = date.setTime(date.getTime() + options.expires);
	}

	this.get = function(name) {
		name = escape(name);

		var regex = new RegExp("(?:^|;\\s+)" + name + "=(.*?)(?:;|$)");
		var match = regex.exec(document.cookie);

		if (!match || match.length < 2)
			return false;

		var value = unescape(match[1]);

		return value;
	};

	this.set = function(name, value) {
		name = escape(name);
		value = escape(value);

		var cookie = name + "=" + value + "; path=/";

		if (expires)
			cookie += "; expires=" + expires;

		document.cookie = cookie;
	};
}


//
// Style crap
//

function changeStyle(name) {
	var link = $("#sitestyle");
	link.attr("href", styles[name]);
}

function createStyleSwitcher() {
	if (typeof styles !== "object")
		return null;

	// Get selected/defaulted stylesheet
	var cookie = new Cookie();
	var selected = cookie.get("style");

	if (!selected)
		selected = $("#sitestyle").attr("title");

	// Create <select> for switcher
	var switcher = $(document.createElement("select"));

	// Counter for styles
	var count = 0;

	for (style in styles) {
		count++;

		var option = $(document.createElement("option"));

		// The text automatically becomes the value
		$(option).text(style);

		// If this is the current style, make it selected
		if (style === selected)
			$(option).attr("selected", "selected");

		switcher.append(option);
	}

	// no styles to switch between
	if (count < 2)
		return null;

	// set onchange event for the switcher
	switcher.change(function() {
		var value = $(this).val();
		changeStyle(value);

		// Save the new style
		var cookie = new Cookie();
		cookie.set("style", value);
	});

	return switcher;
}

function doStyleSwitchers() {
	var ss = createStyleSwitcher();

	if (!ss) {
		// no styles to switch between
		return;
	}

	$(".ss-list").html(ss);
	$(".ss-unhide").removeClass("noscreen");
}


//
// Global init
//

$(document).ready(function() {
	var cookie = new Cookie();

	// Load style
	var style = cookie.get("style");
	if (typeof styles === "object" && style in styles)
		changeStyle(style);

	// Create style switchers
	doStyleSwitchers();

	// Focus stuff
	$(".focus_onload").first().focus();

	// For W3C compliancy, since size="" isn't allowed on file inputs
	$("input[data-size]").attr("size", function() {
		return $(this).data("size");
	});
});

// Submit dummy form with CSRF token
$(".quick_action").click(function(event) {
	event.preventDefault();

	// Set the URL for the dummy form and submit it.
	$("#dummy_form").attr("action", this.href);
	$("#dummy_form").submit();
});
