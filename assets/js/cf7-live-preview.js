// This closure gives access to jQuery as $
// Don't delete it
(function($) {
	// Do stuff
	$(document).ready(function() {
		// === CF7 Live Preview === //

		// Return early if the localization didn't work
		if (typeof window.cf7lp_data.live_preview_metabox === "undefined") return;

		// SETUP VARS ===

		// First vars
		var preview_form_id = window.cf7lp_data.preview_form_id;
		var admin_post_url = window.cf7lp_data.admin_post_url;
		var $html = $(window.cf7lp_data.live_preview_metabox);
		var $sidebar_metabox = $(window.cf7lp_data.sidebar_metabox);
		var $editor = $("#wpcf7-form");
		var $adminForm = $("#wpcf7-admin-form-element");
		var intervalId;

		// Add the preview html
		// $("#contact-form-editor").after($html);
		$("#wpcf7-admin-form-element").after($html);

		// More vars after appending the panel
		var iframe = $("#cf7lp-preview")[0];
		var $refreshBtn = $("#cf7lp-refresh");

		// INITIALIZERS ===

		// Add the sidebar metabox
		$("#informationdiv").after($sidebar_metabox);

		// Colour picker
		$("#cf7lp-background").wpColorPicker({
			change: function(event, ui) {
				updateIframeBackground($(this).val());
				updateOption("background", $(this).val());
			},
			clear: function() {
				updateIframeBackground("#ffffff");
				updateOption("background", "#ffffff");
				$("#cf7lp-background").val("#ffffff");
			}
		});

		// Width
		$("#cf7lp-width").attr("max", $adminForm.width());
		updateIframeWidth($("#cf7lp-width").val());
		$("#cf7lp-width").on("input change", function(e) {
			updateIframeWidth(e.target.value);
		});

		// Update the preview form on load
		saveFormToPreview(true);

		// Maybe setup refresh interval
		if ($("#cf7lp-autoreload").is(":checked")) {
			setupRefreshInterval();
		}

		// EVENT LISTENERS ===

		// Iframe event listener
		window.addEventListener(
			"message",
			function(event) {
				if (
					event.origin !== window.origin ||
					!event.data.hasOwnProperty("cf7lp")
				)
					return;

				switch (event.data.type) {
					case "cf7lp-ready":
						updateIframeBackground();
						break;
					case "cf7lp-iframe-height":
						updateIframeHeight(event.data.height);
						break;
					default:
						break;
				}
			},
			false
		);

		// Refresh button click
		$refreshBtn.click(function(e) {
			e.preventDefault();
			saveFormToPreview(true);
		});

		// Change option
		$(".cf7lp-option").on("change", function(e) {
			if (e.target.dataset.option === "background") {
				return; // the colourpicker handler handles this
			}
			var value = e.target.value;
			if (e.target.type === "checkbox") {
				value = e.target.checked ? 1 : 0;
			}
			if (e.target.dataset.option === "autoreload") {
				if (value === 1) {
					setupRefreshInterval();
				} else {
					stopAutoRefreshing();
				}
			}
			updateOption(e.target.dataset.option, value);
		});

		// FUNCTIONS ===

		// Update iframe background
		function updateIframeBackground(colour) {
			if (typeof colour !== "string") {
				colour = $("#cf7lp-background").val();
			}
			iframe.contentWindow.postMessage(
				{
					cf7lp: true,
					type: "background",
					value: colour
				},
				window.origin
			);
		}

		// Update iframe width
		function updateIframeWidth(value) {
			$(iframe).width(value);
		}

		// Update iframe height
		function updateIframeHeight(height) {
			$(iframe).css("height", height);
		}

		// Setup refresh interval
		function setupRefreshInterval() {
			var cachedVal = $adminForm.serialize();
			intervalId = setInterval(function() {
				var currentVal = $adminForm.serialize();
				if (cachedVal !== currentVal) {
					cachedVal = currentVal;
					saveFormToPreview(true);
				}
			}, 3000);
		}

		// Clear refreh interval
		function stopAutoRefreshing() {
			clearInterval(intervalId);
		}

		// Save form to preview
		function saveFormToPreview(refresh) {
			var $formAction = $("#hiddenaction");

			// Swap action, get data, then swap back
			$formAction.val("cf7lp_update_preview");
			var data = $adminForm.serialize();
			$formAction.val("save");

			$.post(ajaxurl, data, function(response) {
				console.log(response);
				if (refresh) {
					fullRefresh();
				} else {
					console.log("Updated preview form");
				}
			}).fail(function(e) {
				console.error(e);
			});
		}

		// Update one of the settings
		function updateOption(option, value) {
			var data = {
				action: "cf7lp_update_option",
				option: option,
				value: value
			};

			$.post(ajaxurl, data, function(response) {
				//console.log(response)
			});
		}

		// Manual full refresh
		function fullRefresh() {
			iframe.contentWindow.location.reload();
		}

		// Ajax debug - append output to body
		function ajaxDebug(data) {
			$("#cf7lp-debug").html(data);
		}

		// FUNCTIONS NOT CURRENTLY IN USE ===

		// Get admin form data
		function getAdminFormData() {
			var formData = $adminForm.serialize();
			var JSONformData = JSON.stringify(formData);
			return formData;
		}

		// A function that sends the new html directly to the iframe
		function setupAutoUpdates() {
			var cachedVal = "";

			setInterval(function() {
				var currentVal = $editor.val();
				if (cachedVal !== currentVal) {
					cachedVal = currentVal;
					iframe.contentWindow.postMessage(
						{
							cf7lp: true,
							type: "formUpdate",
							formVal: $editor.val()
						},
						window.origin
					);
				}
			}, 2500);
		}

		// Update preview form - this hits a manual
		// ajax endpoint that gets the custom fields
		// and saves them manually.
		// The replacement function, saveFormToPreview,
		// just sends the full form data and uses CF7's
		// form saving function
		function updatePreviewForm(refresh) {
			var data = {
				action: "cf7lp_update_preview",
				formData: getAdminFormData()
			};

			$.post(ajaxurl, data, function(response) {
				ajaxDebug(response);
				if (refresh) {
					fullRefresh();
				} else {
					console.log("Updated preview form");
				}
			});
		}
	});
})(jQuery);
