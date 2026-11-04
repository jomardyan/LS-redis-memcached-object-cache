# WordPress.org directory preparation

The installable archive, checksum and per-file SHA256 manifest are committed in `release/`. Rebuild using `python tools/package-wordpress.py` (Python 3.9 or later); `make package` delegates to the same command. `wordpress-package.json` is the explicit runtime allowlist. The exact current package and its files are listed in `release/wordpress-upload.json`. ZIP entries are stored without compression so different zlib versions cannot change release hashes. The upload stays below10MiB. ZIP timestamps, permissions and ordering are fixed, CRCs and byte-for-byte source equality are checked. The plugin directory matches the existing text domain. Runtime identifiers, database names, hooks and saved data keys are preserved.

The plugin's `readme.txt` gives its actual requirements, license, installation instructions, source links and external-service/data handling details. Existing contributor credits are preserved. A WordPress.org account owner must verify the Contributors field before submission. No new Tested up to claim is inferred from static checks. Existing compatibility claims need the live staging checks described in the repository and must be updated only after those versions were exercised.

## Validation

The archive applies Git's canonical clean filters to the reviewed source files, so Windows and Unix line endings produce the same upload. It includes working-tree edits without changing the Git index. Release only from a reviewed clean commit; verify generated assets and commit intentional source/build changes first.

Run the repository's documented PHP syntax and behavior tests and any asset build. Run official Plugin Check against the extracted upload package, not the development tree:

```sh
python tools/package-wordpress.py
wp plugin check /absolute/path/to/extracted/PLUGIN-SLUG --slug=PLUGIN-SLUG --format=strict-json
```

Replace PLUGIN-SLUG with the slug in `wordpress-package.json`. Static Plugin Check does not prove activation, database migrations, WooCommerce checkout, provider calls, refunds or uninstall safety. Exercise those on a disposable staging site with backups and no production credentials. Review every reported finding; do not disable check categories to obtain a clean report.

## Submission

Verify your WordPress.org contributor username, the final plugin name/slug and any brand permission, current tested-version claims and the external services' current terms/privacy links. Submit the ZIP at https://wordpress.org/plugins/developers/add/ . WordPress.org reviewers make the approval decision; this repository does not claim approval. No upload/submission or deployment is performed by packaging.

The directory is a distribution host. Once approved, maintain the stable version in its assigned SVN repository, increment versions for releases, and keep the header and readme Stable tag matched.

Official guidance reviewed November 2026:

* https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
* https://developer.wordpress.org/plugins/wordpress-org/common-issues/
* https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
* https://wordpress.org/plugins/plugin-check/
