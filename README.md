=== Postino for Bale ===
Contributors: persianfx, omidsamadbin
Tags: bale, auto publish, social media, post sharing, messaging
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule WordPress posts for your Bale channel with custom messages and optional featured images.

== Description ==

Postino for Bale helps site administrators automatically share WordPress posts with a channel on Bale.

Features:

* Automatically queue newly published posts.
* Send queued posts to a Bale channel through the Bale Bot API.
* Configure the bot token and channel identifier from the WordPress administration area.
* Set the interval between queued posts, from 60 seconds to 24 hours.
* Optionally include the post's featured image.
* Customize message content with template variables.
* Test the bot connection before publishing.
* Add previously published posts to the queue.
* Attempt delivery up to three times per queued post.

To use the plugin, create or configure a Bale bot, add it to your channel with permission to publish messages, then enter the bot token and channel identifier in Settings > Postino for Bale.

Available template variables:

* `{post_title}` - The post title.
* `{post_excerpt}` - The post excerpt.
* `{categories}` - The post categories formatted as hashtags.
* `{short_link}` - The WordPress short link, when available.
* `{post_url}` - The post permalink.
* `{site_name}` - The site name.
* `{date}` - The post date.

The plugin uses WordPress Cron (WP-Cron) to process the queue. Delivery times may vary depending on site traffic and server configuration.

Postino is independently developed by Omid Samadbin. It is not affiliated with or endorsed by Bale. Contact: baleplugin@titrano.ir.

== External services ==

This plugin uses the Bale Bot API at tapi.bale.ai to publish messages in the channel selected by the site administrator. Each request uses the configured bot token to authenticate and includes the destination channel identifier.

* When an administrator clicks the connection test, the plugin sends a real test message containing the WordPress site name to the configured channel.
* When WP-Cron processes a queued post, including a previously published post manually added to the queue, it sends the rendered message and the post permalink for the message button. Depending on the template, the message can include custom text, the title, an excerpt (which may be derived from the post content), category hashtags, a short link, the permalink, site name and publication date.
* If featured images are enabled and the post has one, the plugin sends its image URL to Bale. A photo and its message may be delivered separately. Failed deliveries can trigger additional requests.

[Bale Terms of Service and Privacy Policy (Persian)](https://bale.ai/terms)

== Installation ==

1. Upload the `postino-for-bale` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Postino for Bale.
4. Enter your Bale bot token and channel identifier, then save the settings.
5. Use the test connection option to verify that the bot can send messages to your channel.

== Frequently Asked Questions ==

= Does the bot need administrator permissions in the channel? =

The bot must be a member of the channel and have permission to publish messages.

= Why might a post be sent later than its scheduled time? =

The plugin uses WP-Cron. WordPress Cron events are triggered by site visits, so processing may be delayed on sites with low traffic. Server-side cron configuration can help provide more consistent scheduling.

= Are previously published posts shared automatically after activation? =

No. Use the option to add existing posts to the queue if you want to share previously published posts.

= Which content is queued automatically? =

Standard WordPress posts enter the queue when their status changes to Published. Editing an already published post does not add it again. Pages and custom post types are outside the scope of this plugin.

= What should I check if my channel receives no posts? =

First, use the connection test in Settings > Postino for Bale. If the test fails, check the bot token, channel identifier and the bot’s posting permission. If the test arrives but queued posts do not, check the queue, the configured delay and whether WP-Cron is running on your site.

== Changelog ==

= 1.0.2 =
* Renamed the plugin to Postino for Bale and expanded the setup and queue documentation.

= 1.0.1 =
* Updated the plugin name, author contact details and Bale Bot API disclosures.

= 1.0.0 =
* Initial release.
