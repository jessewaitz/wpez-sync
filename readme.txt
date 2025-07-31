=== WPEZtools Sync ===
Contributors: jesse@waitz.com
Tags: server, sync
Requires at least: 6.0.0
Tested up to: 6.0.0
Stable tag: 1.0.1.2

Tools to import wordpress data from another WP Site.

== Description ==

Tools to import wordpress data from another WP Site.

== Changelog ==

= 1.0.1 =
* Full rewrite of plugin upgrading the following:
  1. namespaced and changed the plugin to use modern design practices.
  2. Improved Public Key/Private Key authentication schema.
  3. Includes gzip compression and AES encryption for all remote transactions.
  4. Upgraded all cUrl requests to use Rest API endpoints instead of admin-ajax.
  5. Improved settings page to allow for adding multiple remotes, and exporting configurations.
	6. Moved Github token to settings page and added it to a new keys and tokens page.

= 1.0.0 =
* First stable release.
