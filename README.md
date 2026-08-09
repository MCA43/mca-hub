# mca/hub

**English** | [Türkçe](README.tr.md)

MCA package hub for Laravel: `/mca` dashboard with framework-aware package cards, Composer install detection, and optional remote catalog (GitHub JSON).

## Features

- **Installed detection** — reads `composer.lock` via `InstalledVersions`
- **Remote catalog** — fetch `packages.json` from GitHub (cached)
- **GitHub discovery** — auto-lists `mca-*` repos and reads `extra.mca`
- **Framework filter** — auto-detects Laravel 11/12/13; only lists compatible packages
- **Composer `extra.mca`** — packages self-describe for the hub
- **Root access** — uses `mca/permission` `PermissionService::isRoot()` when available
- **Package updates** — GitHub tag/release checks + confirmed allowlisted `composer update`
- **Shared UI** — loads `mca-ui.css` from `mca/permission` when published
- **i18n** — English and Turkish

## Install

```bash
composer require mca/hub
php artisan vendor:publish --tag=mca-hub-assets --force
```

With permissions admin:

```bash
composer require mca/permission mca/hub
php artisan mca:permission:install
php artisan vendor:publish --tag=mca-permission-assets --force
php artisan vendor:publish --tag=mca-hub-assets --force
```

Open `/mca` as root user.

## Configuration

```env
MCA_HUB_ENABLED=true
MCA_HUB_CATALOG_URL=https://raw.githubusercontent.com/MCA43/mca-catalog/main/packages.json
MCA_HUB_GITHUB_CATALOG=true
MCA_HUB_GITHUB_ORG=MCA43
MCA_HUB_GITHUB_ACCOUNT_TYPE=auto
MCA_HUB_GITHUB_REPO_PREFIX=mca-
MCA_HUB_USE_PERMISSION_ROOT=true
MCA_HUB_ROLE_COLUMN=role_id
MCA_HUB_UPDATES=true
MCA_HUB_ALLOW_PATH_UPDATE=false
```

| Key | Description |
|-----|-------------|
| `catalog.url` | Remote `packages.json` (optional) |
| `github.enabled` | Discover `mca-*` repos from GitHub |
| `github.org` | GitHub user or organization |
| `github.account_type` | `auto` (org then user), `org`, or `user` |
| `github.repo_prefix` | Repo name prefix (default `mca-`) |
| `github.token` | Optional PAT for rate limits |
| `access.use_permission_root` | Delegate root check to permission |
| `access.role_column` | Fallback column without permission |
| `updates.enabled` | GitHub version check + Update button |
| `updates.allow_path_update` | Allow composer update for path installs (default off) |

```bash
php artisan mca:hub:check-updates --fresh
```

## Remote catalog

```json
{
  "updated_at": "2026-06-28",
  "packages": [
    {
      "name": "mca/permission",
      "slug": "permission",
      "frameworks": ["laravel11", "laravel13"],
      "github": "https://github.com/MCA43/mca-permission",
      "route": "mca.permission.index"
    }
  ]
}
```

Without URL, bundled `catalog/packages.json` is used. When GitHub discovery is enabled, repos like `mca-hub` and `mca-permission` are added; local/remote catalog entries take precedence.

## Register a package

In `composer.json`:

```json
"extra": {
  "mca": {
    "slug": "permission",
    "title": { "en": "Permissions", "tr": "İzinler" },
    "route": "mca.permission.index",
    "github": "https://github.com/MCA43/mca-permission",
    "frameworks": ["laravel13"],
    "order": 10
  }
}
```

Or at runtime:

```php
mca_hub_register('permission', [
    'enabled' => fn () => config('permission.enabled'),
]);
```

## Publish tags

| Tag | Output |
|-----|--------|
| `mca-hub-config` | `config/hub.php` |
| `mca-hub-assets` | `public/vendor/mca-hub/` |
| `mca-hub-catalog` | `mca-packages.json` (host override) |

## GitHub

Repository: [github.com/MCA43/mca-hub](https://github.com/MCA43/mca-hub)

```bash
git tag v0.1.0
git push origin main --tags
```

See [CHANGELOG.md](CHANGELOG.md).

## License

[MIT](LICENSE)
