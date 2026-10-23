# Commons Domain Mapping

A small, accessible network-admin screen (and a WP-CLI command) for mapping
custom hostnames to sites on the Knowledge Commons WordPress multisite.

## Why it exists

The network's `sunrise.php` resolves hostnames that are not part of any
network (for example `poetry.rcah.msu.edu`) through the legacy
`wp_domain_mapping` table, and uses the mapped hostname as the cookie domain.
Editing a site's address in Network Admin → Sites updates `wp_blogs` and the
site's `home`/`siteurl` options but does **not** add a mapping row. The site
then issues every cookie for the network's default domain, the browser drops
them, and login falls into a redirect loop that surfaces as
"Authentication error: nonce verification failed".

This plugin manages that table so the fix is a form, not a manual SQL insert.

## What it does

- **Network Admin → Settings → Domain Mapping.** Lists every mapped hostname
  with the site it points at and whether it is the site's primary address or
  an alias. Add a mapping by typing the hostname and picking the site from an
  accessible, searchable combobox (search by address or ID; the form also
  works without JavaScript). Remove mappings, or promote one to the site's
  primary address (which does what Network Admin → Edit Site does: updates
  `wp_blogs` and the `home`/`siteurl` options, preserving any `/wp` core
  path).
- **"Needs attention" panel.** Detects sites whose primary address is outside
  every network domain and has no mapping row (the exact configuration that
  breaks login) and offers a one-click fix.
- **WP-CLI.** `wp commons-domain list|add|remove|primary|check` for use over
  SSM on production hosts.

### Access

Only **super administrators** can see the menu entry, load the screen,
submit any form, or call the REST search route. Every entry point checks
`is_super_admin()` directly. The CLI command relies on shell access as its
credential.

### Database

The plugin reads, inserts and deletes rows in `wp_domain_mapping`
(`id`, `blog_id`, `domain`, `active`). It **never creates, alters or drops
tables**; if the table is missing the screen says so and disables the form.
Rows are always written with `active = 1`, because `sunrise.php` does not
consult that column.

### Accessibility

- The site picker follows the WAI-ARIA editable combobox pattern:
  `role="combobox"` with `aria-expanded`, `aria-controls`,
  `aria-activedescendant`; arrow keys, Home/End, Enter, Escape and Tab behave
  as the pattern specifies; a polite live region announces result counts and
  selections.
- Outcome notices use `role="status"` (success) or `role="alert"` (error)
  and receive focus after the redirect, so screen readers announce them.
- Status is conveyed by icon and text as well as colour; all controls have
  visible focus styles; forced-colours and reduced-motion media queries are
  honoured; the mapping table scrolls in a focusable, labelled region on
  narrow screens.

## Installation (Knowledge Commons)

Add the repository and requirement to the site's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/MESH-Research/commons-domain-mapping" }
  ],
  "require": {
    "mesh-research/commons-domain-mapping": "^1.0"
  }
}
```

`composer update mesh-research/commons-domain-mapping` installs it into
`site/web/app/plugins/commons-domain-mapping`. Network-activate it:

```
wp plugin activate commons-domain-mapping --network
```

Requires PHP 8.5 and a multisite install.

## WP-CLI

```
wp commons-domain list [--format=json]
wp commons-domain add poetry.rcah.msu.edu --site=42 [--primary]
wp commons-domain remove poetry.rcah.msu.edu     # hostname or mapping ID
wp commons-domain primary poetry.rcah.msu.edu    # make it the site's primary address
wp commons-domain check                          # exit 1 if any site needs a mapping
```

## Development

```
composer install
composer check          # php -l, PHPUnit, node --test
composer test           # PHPUnit only
composer test:js        # node --test only
```

The domain layer (`src/`) has no WordPress dependency; tests run against
in-memory fakes of the two storage interfaces. The `$wpdb` and
`WP_Site_Query` adapters are exercised against a real multisite. See
`docs/SPEC.md` for the design.

## License

MIT. See `LICENSE`.
