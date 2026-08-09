# mca/hub

**English** | [Türkçe](README.tr.md)

WordPress-style MCA package hub for Laravel: lists GitHub `mca-*` packages as installed / available, and lets **root** install, update, or remove them via confirmation modals.

## Features

- **Installed / available / planned** sections
- **GitHub discovery** — auto-lists `mca-*` repos
- **Install** — allowlisted VCS + `composer require` (confirm modal)
- **Update** — GitHub tag/release + `composer update` (blocked for path installs)
- **Remove** — `composer remove` (protected and path packages excluded)
- **Root access** — `mca/permission` `isRoot()` or role fallback
- **Shared UI** — `mca-ui` confirmation modals
- **i18n** — English and Turkish

## Install

```bash
composer require mca/hub
php artisan vendor:publish --tag=mca-hub-assets --force
php artisan vendor:publish --tag=mca-permission-assets --force
```

Open `/mca` as a root user.

## Configuration

```env
MCA_HUB_ENABLED=true
MCA_HUB_GITHUB_CATALOG=true
MCA_HUB_GITHUB_ORG=MCA43
MCA_HUB_UPDATES=true
MCA_HUB_ALLOW_PATH_UPDATE=false
MCA_HUB_LIFECYCLE=true
MCA_HUB_PROTECTED_PACKAGES=mca/hub,mca/permission
```

| Key | Description |
|-----|-------------|
| `lifecycle.enabled` | Install / remove from Hub |
| `lifecycle.protected` | Packages that cannot be removed |
| `updates.allow_path_update` | Allow composer update for path installs (default off) |
| `github.org` / `repo_prefix` | VCS URL allowlist (`github.com/{org}/mca-*`) |

### Path monorepo (`packages/mca/*`)

Local path/symlink packages show as **Local path**; Hub install/update/remove is **blocked**. Use VCS/dist installs in production for lifecycle buttons.

```bash
php artisan mca:hub:check-updates --fresh
```

## Security

- **Root only**
- Package name: `mca/[a-z0-9-]+`
- Install only catalog packages + allowed GitHub org
- `mca/hub` and `mca/permission` cannot be removed
- Every action requires a **confirmation modal**

## Register a package

Define `extra.mca` in the package `composer.json` (title, route, github, icon).

## License

[MIT](LICENSE)
