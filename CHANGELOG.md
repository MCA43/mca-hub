# Changelog

Format [Keep a Changelog](https://keepachangelog.com/) esas alınır.

## [Unreleased]

### Added
- WordPress-style lifecycle: install / update / remove from Hub UI (root only)
- Sections: Installed / Available / Planned
- `mca-ui` confirmation modals for install, update, remove
- Allowlisted GitHub VCS `composer require` + managed repo tracking
- Protected packages (`mca/hub`, `mca/permission`) cannot be removed
- Path/symlink packages blocked for install/update/remove
- Config: `hub.lifecycle.*` / `MCA_HUB_LIFECYCLE*`
- Routes: `mca.hub.packages.install`, `mca.hub.packages.remove`, `mca.hub.packages.update`

### Changed
- Hub cards split into installed vs available sections
- Package update confirm uses mca-ui modal instead of `window.confirm`

## [0.2.0] - 2026-08-09

### Added
- GitHub release/tag update checks for installed MCA packages
- Card badges: update available / local path / up to date
- Allowlisted `composer update` via Hub UI (root only)
- Hub self-update banner (mca/hub shown when updates enabled)
- `mca:hub:check-updates` artisan command
- Config: `hub.updates.*` / `MCA_HUB_UPDATES*`

### Fixed
- Hub package cards render SVG icons instead of empty colored placeholders

## [0.1.1] - 2026-06-28

### Added
- GitHub katalog kaynağı — `MCA43` altındaki `mca-*` repoları otomatik listelenir
- `composer.json` → `extra.mca` GitHub raw üzerinden okunur
- `MCA_HUB_GITHUB_*` yapılandırma anahtarları (`account_type`: org / user / auto)

## [0.1.0] - 2026-06-28

### Added
- `/mca` paket paneli — kurulu paket kartları, framework filtresi
- Uzak katalog (`MCA_HUB_CATALOG_URL`) + yerel `catalog/packages.json` yedek
- Composer `extra.mca` otomatik keşif (`InstalledVersions`)
- `mca_hub_register()` runtime kayıt
- `mca.hub.access` middleware — `mca/permission` root kontrolü veya `role_id` / slug yedek
- `mca-ui` ile uyumlu hub CSS
- İngilizce ve Türkçe çeviriler
