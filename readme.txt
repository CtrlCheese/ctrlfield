=== CtrlField ===
Contributors: ctrlcheese
Tags: custom fields, custom post types, taxonomies, acf, developer
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Code-first custom fields, custom post types and taxonomies for WordPress. Define everything in PHP — versionable, testable, no database lock-in.

== Description ==

**CtrlField** is a code-first toolkit for structured content in WordPress. You define your custom fields, custom post types and taxonomies in plain PHP — so your content model lives in your repository, is reviewed in pull requests, and deploys with your code. No clicking through admin screens, no configuration trapped in the database.

It is built for developers who want the ergonomics of a modern framework (a fluent field API, Blade templating, a typed REST layer) while staying fully native to WordPress.

= Why code-first? =

* **Versionable** — your whole content model is in Git. Diff it, review it, roll it back.
* **Testable** — field definitions are plain PHP objects, so you can unit-test them.
* **Portable** — no "export/import" dance between environments. Deploy and it's there.
* **No lock-in** — values are stored in standard post/term/user meta; nothing proprietary.

= Core features (free) =

* **Fields in code** — a fluent API to define text, textarea, number, range, email, URL, select, radio, checkbox, date/time, color, image, file, oEmbed, map, link, WYSIWYG and computed fields.
* **Custom post types & taxonomies** — registered from the same code-first API.
* **Fields anywhere** — attach groups to posts, pages, custom post types, taxonomy terms, users and options pages.
* **Blade templating** — render fields in Blade views with directives made for CtrlField.
* **REST API** — read and write field values through a typed, permission-checked REST layer.
* **Admin niceties** — admin columns, Quick Edit support and a dashboard overview.
* **Field permissions** — restrict fields to read-only (or hidden) per role, enforced on every save path.
* **CSV export** and a schema inspector for your content model.
* **ACF compatibility layer** — ease migration from ACF-style code.

= CtrlField Pro =

A Pro license unlocks the advanced building blocks:

* **Repeater**, **Gallery**, **Flexible Content** and **Clone** fields
* **Relational fields** (post objects, relationships, taxonomy relations)
* **Content Tables** — store large datasets in dedicated, indexed tables with a REST CRUD API
* **Visual Builder** and **Global Blocks**
* **Page-builder integrations** — Elementor, Bricks and WooCommerce
* **Outbound webhooks** on content changes

Learn more at [ctrlcheese.de](https://ctrlcheese.de/ctrlfield). The free plugin is fully functional on its own; Pro is optional.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/ctrlfield`, or install it through **Plugins → Add New**.
2. Activate it through the **Plugins** menu in WordPress.
3. Define your fields, post types and taxonomies in your theme or a small plugin, using the CtrlField API. See the documentation at [ctrlcheese.de](https://ctrlcheese.de/ctrlfield).

CtrlField requires **PHP 8.2+** and **WordPress 6.5+**.

== Frequently Asked Questions ==

= Do I configure fields in the admin? =

No — CtrlField is code-first. You define fields, post types and taxonomies in PHP. That is the point: your content model lives in your codebase, not the database.

= Where are field values stored? =

In standard WordPress post, term, user or options meta. There is no proprietary storage, so your data stays usable even without the plugin.

= Does it work with ACF? =

CtrlField ships a compatibility layer that mirrors common ACF-style helpers, to make migrating existing code easier.

= Is it compatible with block themes and the Site Editor? =

Yes. CtrlField registers standard post types, taxonomies and meta, so they work with the block editor and block themes.

= What does Pro add? =

Repeater, Gallery, Flexible Content and Clone fields, relational fields, Content Tables, a visual builder, and Elementor / Bricks / WooCommerce integrations. The free plugin does not expire or nag.

== Screenshots ==

1. Defining a field group in code.
2. The schema inspector.
3. Fields rendered on a post edit screen.

== Changelog ==

= 1.0.1 =
* First wordpress.org release.

== Upgrade Notice ==

= 1.0.1 =
First wordpress.org release of CtrlField.
