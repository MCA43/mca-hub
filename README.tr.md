# mca/hub

**Türkçe** | [English](README.md)

Laravel için MCA paket paneli: `/mca` altında framework uyumlu kartlar, kurulum tespiti ve isteğe bağlı uzak katalog.

## Özellikler

- **Kurulum tespiti** — `composer.lock` / `InstalledVersions`
- **Uzak katalog** — GitHub `packages.json` (önbellekli)
- **GitHub keşif** — `mca-*` repoları otomatik listelenir (`extra.mca` okunur)
- **Framework filtresi** — Laravel 11/12/13 otomatik algılama
- **Composer `extra.mca`** — paketler kendini tanımlar
- **Root erişim** — `mca/permission` yüklüyse `isRoot()` kullanır
- **Ortak UI** — `mca/permission` asset'leri varsa `mca-ui.css` paylaşır
- **Çoklu dil** — `tr` / `en`

## Kurulum

```bash
composer require mca/hub
php artisan vendor:publish --tag=mca-hub-assets --force
```

İzin paketi ile birlikte:

```bash
composer require mca/permission mca/hub
php artisan mca:permission:install
php artisan vendor:publish --tag=mca-permission-assets --force
php artisan vendor:publish --tag=mca-hub-assets --force
```

Root kullanıcı ile `/mca` adresine gidin.

## Yapılandırma

```env
MCA_HUB_ENABLED=true
MCA_HUB_CATALOG_URL=https://raw.githubusercontent.com/MCA43/mca-catalog/main/packages.json
MCA_HUB_GITHUB_CATALOG=true
MCA_HUB_GITHUB_ORG=MCA43
MCA_HUB_GITHUB_ACCOUNT_TYPE=auto
MCA_HUB_GITHUB_REPO_PREFIX=mca-
MCA_HUB_USE_PERMISSION_ROOT=true
MCA_HUB_ROLE_COLUMN=role_id
```

| Anahtar | Açıklama |
|---------|----------|
| `catalog.url` | Uzak `packages.json` (opsiyonel) |
| `github.enabled` | GitHub'dan `mca-*` repolarını çek |
| `github.org` | GitHub kullanıcı veya organizasyon adı |
| `github.account_type` | `auto` (önce org, sonra user), `org` veya `user` |
| `github.repo_prefix` | Repo adı öneki (varsayılan `mca-`) |
| `github.token` | Rate limit için opsiyonel PAT |
| `access.use_permission_root` | Root kontrolünü permission'a devret |
| `access.role_column` | Permission yokken yedek (`role_id` önerilir) |

Uzak URL yoksa paket içi `catalog/packages.json` kullanılır. GitHub keşif açıksa `mca-hub`, `mca-permission` gibi repolar kataloga eklenir; yerel/uzak kayıtlar önceliklidir.

## Paket kaydı

`composer.json` → `extra.mca`:

```json
{
  "slug": "permission",
  "title": { "en": "Permissions", "tr": "İzinler" },
  "route": "mca.permission.index",
  "github": "https://github.com/MCA43/mca-permission",
  "frameworks": ["laravel13"]
}
```

## Publish

```bash
php artisan vendor:publish --tag=mca-hub-config
php artisan vendor:publish --tag=mca-hub-assets --force
php artisan vendor:publish --tag=mca-hub-catalog
```

## GitHub

Depo: [github.com/MCA43/mca-hub](https://github.com/MCA43/mca-hub)

Sürüm notları: [CHANGELOG.md](CHANGELOG.md)

## Lisans

[MIT](LICENSE)
