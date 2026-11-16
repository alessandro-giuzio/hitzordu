=== Hitzordu ===
Contributors: alessandrogiuzio
Tags: appointments, booking, scheduling
Requires at least: 6.5
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Appointment and call booking for WordPress.

== Description ==

Hitzordu lets visitors book appointments. This version sets up the plugin
structure and the database tables (meeting types, availability rules, date
overrides and bookings). The booking interface and REST API are still to come.

== Installation ==

1. Run `composer install` in the plugin folder.
2. Activate the plugin from the Plugins screen.

== Uninstall ==

Deleting the plugin keeps its data by default. Set the option
`hitzordu_delete_data_on_uninstall` to a truthy value to drop the tables on delete.

== Changelog ==

= 1.0.0 =
* Initial structure and database schema.
