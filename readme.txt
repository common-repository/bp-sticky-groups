=== BP Sticky Groups ===
Contributors: dot07
Donate link: https://dot07.com
Tags: BuddyPress, groups, sticky
Requires at least: 3.6
Tested up to: 3.8
Stable tag: 1.0.3
License: GPLv2

Stick groups to top of groups directory !

== Description ==
This BuddyPress (version 1.8+) plugin allows you to stick groups on top of the groups directory front page. Administrators can set a group as sticky from the WordPress backend group Admin UI.

NB : version 1.0.3 of this plugin requires at least BuddyPress 1.9

The sticky groups will appear whatever the sort order of the groups loop is. As soon as a search query is performed, the sticky feature won't be applied so that the result of the search remains consistent.

http://vimeo.com/74177071

This plugin is available in french and english.

== Installation ==

You can download and install BP Sticky Groups using the built in WordPress plugin installer. If you download BP Sticky Groups manually, make sure it is uploaded to "/wp-content/plugins/bp-sticky-groups/".

Activate BP Sticky Groups in the "Plugins" admin panel using the "Network Activate" (or "Activate" if you are not running a network) link.

== Frequently Asked Questions ==

= If you have a question =

Please use the support forum of this plugin

== Screenshots ==

1. The group Admin UI / Groups directoty loop.

== Changelog ==

= 1.0.3 =
* Stops using a filter on bp_get_group_name in favor of bp_get_group_class (available since 1.9) for Groups directory & bp_groups_admin_row_class for Groups administration screen
* BuddyPress 1.9 required

= 1.0.2 =
* In front, to avoid trouble with the groups widget, the group name filter is now "encapsulated" between two "do_actions". It starts on bp_before_groups_loop and ends at bp_after_groups_loop. So themes need to make sure to include this 2 "do_actions" before and after the groups loop in the template /groups/groops-loop.php.
* Side note : filtering bp_get_group_name is far from being the greatest way of indicating a group is sticky, as people may use the bp_group_name() tag inside a link title attribute, i'll work on this as soon as BuddyPress will include a specific tag class to the group li element in groups loop.
* This version also fixes a silly mistake i made to get the name of the groupmeta table (really sorry for this one!!)

= 1.0.1 =
* Fixes a PHP 5.4 strict error

= 1.0 =
* BuddyPress 1.8 required

== Upgrade Notice ==

= 1.0.3 =
BuddyPress 1.9 required

= 1.0.2 =
nothing particular.

= 1.0.1 =
nothing particular.

= 1.0 =
no upgrades, just a first install..
