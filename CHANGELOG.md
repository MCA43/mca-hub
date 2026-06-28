# Changelog

Format [Keep a Changelog](https://keepachangelog.com/) esas alınır.

## [Unreleased]

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
- `mca-ui` ile uyumlu hub CSS (permission asset varsa paylaşımlı tasarım)
- İngilizce ve Türkçe çeviriler

### Changed
- Varsayılan erişim kolonu `role_id`; permission yokken `roles.is_root` kontrolü
- Katalog GitHub URL'leri `MCA43` organizasyonu ile hizalandı
