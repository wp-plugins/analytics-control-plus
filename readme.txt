=== Analytics Control Plus ===
Contributors: aykira
Tags: analytics, google, traffic, bounce rate, google analytics, demographics, link tracking, tracking, stats, statistics, page views, time on site, seo, conversion, privacy, universal analytics
Requires at least: 3.5
Tested up to: 3.9.0
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Set up Google Analytics with options (demographics and enhanced link tracking), no JavaScript editing. Does bounce timeout, so more accurate stats.

== Description ==

Google Analytics can be operated in several modes:

* Plain simple Google Analytics - usage of the site is tracked
* Google Analytics with demographics tracking
* Google Analytics with enhanced link attribution
* Google Analytics with demographics and link attribution
* Universal Analytics (new version)
* Universal Analytics with demographics tracking
* Universal Analytics with enhanced link attribution
* Universal Analytics with demographics and link attribution

To swap between these usually requires editing the Google JavaScript and ensuring you have everything correct, often requiring technical skills.
This plugin avoids the need to edit JavaScript by doing it all for you and thereby avoiding costly mistakes.
Plus it makes it easy to see exactly what you have enabled per site.

This plugin also provides a fix for bounce tracking, in that an event gets generated after a configurable timeout once they have scrolled down the page. This way if the user is actually reading a page it won't be counted as a bounce.

The plugin also won't insert the Google Analytics code if you are logged in as the Admin.

The plugin also allows you to turn off Google Analytics tracking for specific pages if required (i.e. privacy or performance).

You are also now able to turn off Google Analytics for a set of request IP's or subnets (i.e. your office), saves having to fiddle with IP settings in GA itself.

Upcoming Features:

* Fine grain control over when the code is used (i.e. role type)

[Plugin Home Page](http://www.aykira.com.au/2014/03/analytics-control/)

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `analytics-control-plus.zip` to the `/wp-content/plugins/` directory
2. Unzip the zip file in place.
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Setting &rarr; Analytics Control+ to enter your Google Analytics ID and set the options.
5. Make sure your settings on Google Analytics match what you have selected.

== Frequently Asked Questions ==

= Can I select the user type who is tracked? =

No, but it is planned to be done

= How do I get help? =

Contact us using [this form](http://www.aykira.com.au/contact/)

== Screenshots ==

1. Just enter your Analytics ID into the settings page, set the options and you are done.

== Changelog ==

= 1.3 =
* Support for Universal Analytics - just set to Yes in the settings and its changed.

= 1.2 =
* Can disable GA by request source IP if required.

= 1.1 =
* Can disable GA per page if required.

= 1.0 =
* Initial Release
