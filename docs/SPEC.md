# Spec: Commons Domain Mapping

## Objective

Knowledge Commons runs a WordPress multisite where `sunrise.php` resolves
custom (non-network) hostnames through the legacy `wp_domain_mapping` table and
uses the mapped hostname as the cookie domain. Changing a site's address in
Network Admin → Sites does **not** add a mapping row, so cookies for a custom
hostname are issued for `hcommons.org`, the browser drops them, and login
falls into the broker redirect loop described in the incident write-up
("nonce verification failed").

This plugin gives network administrators a small, fully accessible screen to
manage that table without touching the database by hand, and a WP-CLI command
for the same operations over SSM.

Success looks like:

- A super admin can map `poetry.rcah.msu.edu` to a site in under a minute,
  finding the site by typing part of its address rather than knowing its ID.
- The screen flags sites whose primary address is outside every network and
  has no mapping row (the exact failure mode), with a one-click fix.
- Everything on the screen is operable with a keyboard and a screen reader.
- The plugin is installable into `knowledge-commons-wordpress` via composer
  with no build step.

## Assumptions

1. The `wp_domain_mapping` table follows the WPMU Domain Mapping schema
   (`id`, `blog_id`, `domain`, `active`) and is provisioned with the network.
   The plugin **never creates, alters or drops tables**: it only reads rows
   and inserts or deletes them, always with `active = 1`. If the table is
   missing the screen says so and disables the form.
2. `sunrise.php` ignores the `active` column, so the UI does not expose an
   enable/disable toggle; a mapping either exists or it does not.
3. The plugin is network-activated and the screen lives in Network Admin.
   **Only super admins** may see the menu entry, load the screen, submit any
   form, or call the REST search route. Every entry point checks
   `is_super_admin()` explicitly rather than relying on a capability that a
   role could be granted; non-super-admins get a 403 with no information
   about the screen.
4. "Make primary" means what Network Admin → Edit Site does: set
   `wp_blogs.domain` to the mapped hostname, `path` to `/`, and the site's
   `home` and `siteurl` options to `https://<hostname>`. HTTPS is assumed
   because the operator handles certificates.
5. Site search matches a site's address (domain, path) or numeric ID. Site
   titles live in per-site option tables and cannot be searched cheaply
   across thousands of sites, so they are displayed in results but not
   searched.
6. No build tooling: plain PHP 8.5, vanilla CSS and JavaScript.

## Tech Stack

- PHP 8.5 (matches production), WordPress multisite 6.x/7.x
- PHPUnit 11 for unit tests, run on the host with `composer`
- Node's built-in test runner for the small amount of pure JavaScript
- No framework, no bundler

## Commands

```
Install dev deps:  composer install
Unit tests (PHP):  composer test            # vendor/bin/phpunit
Unit tests (JS):   node --test tests/js/*.test.js
Lint:              composer lint            # php -l over src and the main file
All:               composer check
```

## Project Structure

```
commons-domain-mapping.php   Plugin header, constants, bootstrap
src/
  Plugin.php                 Wires hooks; the only place WordPress globals are touched
  Hostname.php               Pure hostname normalisation and validation
  Mapping.php                Immutable value object for a table row
  Site.php                   Immutable value object for a site summary
  MappingService.php         Use cases: list, add, remove, make primary, detect gaps
  Storage/
    MappingStore.php         Interface over wp_domain_mapping
    SiteDirectory.php        Interface over wp_blogs / site options / networks
    WpdbMappingStore.php     $wpdb implementation (row reads/writes only)
    WpSiteDirectory.php      WP_Site_Query implementation
  Admin/
    Screen.php               Network admin menu, assets, rendering
    FormHandler.php          admin-post handlers -> Outcome objects
    Outcome.php              Result of a form action (notice + redirect)
  Rest/
    SitesController.php      GET /commons-domain-mapping/v1/sites?search=
  Cli/
    Command.php              wp commons-domain list|add|remove|primary|check
assets/
  admin.css                  Screen styles (WP admin palette, focus-visible, reduced motion)
  admin.js                   Progressive enhancement: ARIA combobox, confirmations
  combobox-logic.js          Pure helpers shared with tests
tests/
  bootstrap.php              Autoload + WordPress function stubs
  stubs/wordpress.php        Minimal stubs for i18n/escaping/WP_Error
  unit/*Test.php             Behaviour tests against in-memory fakes
  fakes/*.php                InMemoryMappingStore, InMemorySiteDirectory
  js/*.test.js               node --test specs for combobox-logic.js
docs/SPEC.md                 This document
```

## Code Style

WordPress coding style for PHP (tabs, snake_case functions, Yoda-free), PSR-4
namespaces under `MeshResearch\DomainMapping`, typed properties and return
types everywhere, no static state. Example:

```php
final class MappingService {
	public function __construct(
		private MappingStore $store,
		private SiteDirectory $sites,
	) {}

	/** @throws InvalidHostname|MappingConflict|UnknownSite */
	public function add( string $hostname, int $blog_id, bool $make_primary = false ): Mapping {
		$hostname = Hostname::normalize( $hostname );
		...
	}
}
```

JavaScript: ES2019, no transpilation, `'use strict'`, functions small enough
to name.

## Testing Strategy

Red/green. Tests are written first against stubs that throw
`NotImplementedError` (PHP: `\RuntimeException('Not implemented')`), run to
confirm failure, then implemented.

- `Hostname`: table-driven normalisation and rejection cases.
- `MappingService`: behaviour against `InMemoryMappingStore` and
  `InMemorySiteDirectory` (add, duplicate rejection, unknown site, remove,
  make primary, gap detection).
- `FormHandler`: given a request array, returns an `Outcome` with the right
  notice type/message and side effects visible through the fakes.
- `SitesController::search`: result shaping and ID lookups through the fake
  directory.
- `combobox-logic.js`: keyboard index arithmetic, query building, result
  labelling.

The `$wpdb`/`WP_Site_Query` adapters are thin and exercised against the local
Docker multisite rather than unit-tested.

## Boundaries

- Always: run `composer check` before committing; validate and normalise
  hostnames before they reach the database; escape all output; nonce-check
  every write and require `is_super_admin()` on every entry point (menu,
  screen, admin-post handlers, REST).
- Ask first: adding runtime dependencies.
- Never: create, alter or drop any table; write `active = 0`; delete or alter rows in tables other than
  `wp_domain_mapping`, `wp_blogs` (domain/path only) and the site's
  `home`/`siteurl` options; include attribution to any AI tool anywhere.

## Success Criteria

- `composer check` passes.
- On the local multisite (with the table provisioned by hand, as in
  production): adding a mapping inserts a row that `sunrise.php` resolves (request with the mapped
  `Host` header returns the site and a `Set-Cookie` scoped to that host);
  removing deletes the row.
- Keyboard-only walkthrough: search, pick a site with arrow keys and Enter,
  submit, remove a mapping. Screen reader announcements for result counts
  and selection via a live region.
- axe-core reports no violations on the screen.

## Open Questions

- Whether `sunrise.php` should start honouring `active`. Out of scope here;
  noted in the README.
