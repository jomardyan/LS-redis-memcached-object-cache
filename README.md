# Lolisoft Press addon Redis and Memcached Object Cache

Persistent WordPress object caching with Redis or Memcached. Includes safe drop-in management, installation-scoped invalidation, diagnostics, and WP-CLI commands.

Version 1.2.1. Requires WordPress 6.0 or newer and PHP 8.0 or newer. Licensed under GPL-3.0-or-later.

## Install

1. Install a Redis or Memcached server and its PHP extension through your host.
2. Run `make build` and upload `build/smart-hybrid-cache.zip` through the WordPress plugin installer.
3. Activate the plugin and open Settings > Lolisoft Press addon Redis and Memcached Object Cache.
4. Save the server settings and run a connection test.
5. Enable or install the object cache drop-in. Reload the page to verify the active engine.

Network activate on multisite. Only a super administrator on the main site can change the shared configuration. The local `wp-content` directory must be writable by PHP for atomic drop-in installation.

The [directory readme](smart-hybrid-cache/readme.txt) contains installation, privacy, storage, compatibility, and recovery details. The [submission guide](docs/wordpress-org-submission.md) explains the remaining WordPress.org account and review steps.

## Cache behavior

- The installed drop-in embeds configuration and starts before the WordPress Options API is available. No settings queries run during cache initialization.
- Auto resolves a single backend when settings are saved or the drop-in is installed. Outages fall back to request-local memory. The plugin does not alternate data stores during an outage.
- Keys include installation identity, configuration generation, blog or global scope, and a hash of the original key. Long keys and groups remain distinct and valid for Memcached.
- Flushes rotate an installation-specific random namespace. They never clear the whole Redis database or Memcached server. Old values expire or are evicted normally.
- Bulk operations return a result for each key. Falsy values remain cache hits. Cached objects are cloned. Counters use Redis transactions or Memcached CAS and retain expiry.
- WordPress core invalidates normal objects. Optional full post-update flushing is disabled by default and runs once at shutdown when enabled.
- Connection diagnostics run on demand. Frontend plugin loading does not open another connection or write connection status to the options table.

Configure only trusted cache servers. Cached WordPress objects require PHP object deserialization. Connection credentials live in the database and the generated PHP drop-in, with owner-only file permissions requested at installation. Diagnostics redact the Redis password.

Current limits include single-server connections, Redis password authentication without ACL usernames, no Redis Cluster or Sentinel, and no Memcached SASL or TLS. If data changed during an outage, flush after the backend recovers. A zero default TTL requires an appropriate eviction policy to reclaim invalidated namespaces.

## Build and verify

Requires PHP CLI with ZipArchive, Make, and the `zip` command.

```sh
make lint
make test
make validate
make release
```

`make test` runs administration, release preparation, runtime, Redis, and Memcached regressions. With native PHP extensions, it connects to local servers on ports 6379 and 11211. Without them, deterministic backend doubles cover API behavior and failure paths. CI explicitly requires real extensions and servers, then tests WordPress 6.0 and 7.1 in separate processes.

`make build` recreates the ZIP from scratch. Validation checks metadata consistency, the runtime license, documented screenshots, the unconfigured drop-in template, and every ZIP entry against source. Tests, development dependencies, directory screenshots, and other build archives are excluded.

Release outputs

- `build/smart-hybrid-cache.zip`
- `build/smart-hybrid-cache-1.2.1.zip`

The GitHub Actions `smart-hybrid-cache` artifact downloads as an installable plugin archive. GitHub releases attach the standalone and versioned ZIPs. Release workflow values are passed through environment variables before use in shell commands.

## WP-CLI

```sh
wp smart-cache status
wp smart-cache test auto
wp smart-cache test redis
wp smart-cache test memcached
wp smart-cache enable redis
wp smart-cache disable
wp smart-cache install-dropin
wp smart-cache remove-dropin
wp smart-cache flush
wp smart-cache diagnostics --pretty
```

`install-dropin --force` and `remove-dropin --force` explicitly authorize replacing or removing a foreign regular drop-in file. Symbolic links require manual handling. Flush failure returns a nonzero CLI exit status.

## Release maintenance

Update the changelog and upgrade notice, then run

```sh
make set-version VERSION=1.2.1
make ci
make release
```

The version tool requires completed changelog and upgrade notes for the target stable version before changing the main plugin header, runtime constant, drop-in version, and stable tag. Invalid input or missing metadata leaves version fields unchanged. Do not release placeholder changelog entries. Review all CI jobs before merging or tagging.

The repository contains GitHub workflows for syntax checks, real-backend regression tests, WordPress integration, full Plugin Check including the drop-in, and packaging. Directory screenshots live in `.wordpress-org` and belong in the top-level SVN `assets` directory after approval.

## Recovery

Add `define( 'SMART_HYBRID_CACHE_DISABLED', true );` to `wp-config.php` before WordPress loads for an immediate runtime bypass. If an older broken drop-in prevents startup, rename the owned `wp-content/object-cache.php` through your hosting file manager, then install the current drop-in from the settings page.

Deactivation removes the owned drop-in by default. If removal is disabled, it writes a disabled configuration instead. Uninstall deletes settings and logs in batches across multisite and respects the owned-drop-in cleanup preference. A filesystem error requires manual correction through your host.

## Contributions and security

Use [GitHub issues](https://github.com/jomardyan/Lolisoft-Smart-Hybryd-Cache-Redis-and-memcached/issues) for reproducible non-sensitive problems. See [SECURITY.md](SECURITY.md) for vulnerability reporting and [CONTRIBUTING.md](CONTRIBUTING.md) for development guidance.

## Display name and upgrade compatibility

The display name is **Lolisoft Press addon Redis and Memcached Object Cache**. Install updates into the existing plugin directory. The plugin filename, text domain, namespaces, hooks, option keys, database tables and cache/drop-in ownership signatures retain their existing identities.

Public web searches on 4 November 2026 found no indexed exact match for the full proposed display name. This is limited conflict screening, not trademark clearance: another business uses the Lolisoft name. Before public directory submission, check [EUIPO availability guidance](https://www.euipo.europa.eu/pt/trade-marks/before-applying/availability) and [EUIPO search tools](https://www.euipo.europa.eu/en/search-ip) for the intended territories and classes.

## Repository quality commands

`make install`, `make dev`, `make test`, `make lint-php`, `make build`, `make clean` and `make package` provide the local entry points. PHP syntax checking works without WordPress. Runtime and production-host integration checks still need a local WordPress/WooCommerce installation; existing regression harnesses do not simulate a full site. `make package` uses the existing distribution builder where available; the callback plugin source ZIP is a source snapshot and requires its asset build before installation.

## Copyright

Copyright (c) 2026 Hayk Jomardyan - Lolisoft. See [COPYRIGHT.md](COPYRIGHT.md); existing licenses and third-party notices remain applicable.

## WordPress.org upload package

Run `python tools/package-wordpress.py` from the repository root. The reviewed runtime allowlist in `wordpress-package.json` produces the installable ZIP, SHA256 checksum, and per-file manifest in `release/`. Upload the ZIP through WordPress or the WordPress.org submission form. Developer dependencies and local test files are excluded. See [WORDPRESS-DIRECTORY.md](WORDPRESS-DIRECTORY.md) for source, checks, and submission requirements.
