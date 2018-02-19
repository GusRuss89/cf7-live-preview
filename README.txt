=== Contact Form 7 Live Preview ===
Contributors: gusruss89
Tags: contact form 7, contact form 7 style, contact form 7 skin, contact form 7 templates, contact form 7 styling, contact form 7 theme, CF7 style, CF7, CF7 themes, CF7 templates, styling contact form, styling contact form 7, CF7 addon, contact form 7 addon, CF7 form messages styling, conditional fields for contact form 7
Requires at least: 4.4
Tested up to: 4.9
Stable tag: trunk
Donate link: https://www.paypal.me/AngusRussell
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

View a live preview of your form as you're building it. Contact Form 7's form tags can be hard to wrangle. Immediate feedback saves you time.

== Description ==

View a live preview of your form as you're building it.

= Contact Form 7 =
Contact Form 7 is great, but building your forms using form tags can be painful and slow when you have to constantly save, switch tabs and refresh to see your changes.

Contact Form 7 Live Preview relieves this pain by displaying a live preview of your form directly underneath the form editor.

= How does it work =
Contact Form 7 Live Preview creates a "dummy" form and displays it in the live preview pane. Whenever you make changes to a form you're working on, it saves your current state to the dummy form and refreshes the preview. This means you don't have to save your form to see your changes, which can be especially important if your form is currently live.

The dummy form is set to [`demo_mode: on`](https://contactform7.com/additional-settings/), which means you can test the validation and success messages and no emails will be sent.

= Note: It may not work with some themes or plugins =
The preview window displays _just_ the form. It loads your site's scripts and styles as long as they're enqueued properly via [hooks](https://codex.wordpress.org/Plugin_API/Action_Reference/wp_enqueue_scripts). If something isn't looking right in the preview, make sure your scripts and styles are enqueued correctly, and aren't loaded conditionally (E.g. `is_page('contact'))`.

If it doesn't work for you, please don't go straight for leaving a bad review. Let me know in the forums and we'll figure out if the problem is with the plugin, or with your theme. If it's a problem with the plugin I'll try and fix it.

= Features: =
* Live preview changes to your form
* Test validation and error messages without sending emails
* Set the background colour of the preview (helpful if your form is going to be on a different coloured background once live)
* Works with most CF7 addons that affect the look and behaviour of your form

= Tested with: =
* Conditional Fields for Contact Form 7

== Installation ==
1. Upload the zip to the `/wp-content/plugins/` directory and unzip
1. Activate the plugin through the 'Plugins' menu in WordPress

OR go to 'Plugins' > 'Add new', and search for 'contact form 7 live preview' to install through the WordPress dashboard.

== Screenshots ==
1. Live preview window directly beneath form editor

== Changelog ==
= 0.1.0 =
* First release - beta