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

Hitzordu lets visitors book appointments on your site. Visitors pick a day on an
accessible month calendar, see times in their own time zone, and get a
confirmation email with a cancel link. You get a notification email and an
admin screen with all bookings.

Features:

* Booking widget as a shortcode, `[hitzordu]`, or as a block.
* Weekly working hours, date overrides, buffers, minimum notice and booking window.
* Server-side double-booking protection, a honeypot field and rate limiting.
* Cancel link with a confirm step, so email scanners cannot cancel by accident.
* Translations: English, Spanish, Italian and Basque.

== Installation ==

1. Run `composer install` in the plugin folder.
2. Activate the plugin from the Plugins screen.
3. Add the `[hitzordu]` shortcode to a page, or insert the booking block.

== Uninstall ==

Deleting the plugin keeps its data by default. To drop the tables and settings on
delete, tick the delete-data option on the Hitzordu settings screen first.

== Changelog ==

= 1.0.0 =
* Initial release.
