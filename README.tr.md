# mca/hub

**Türkçe** | [English](README.md)

Laravel için MCA paket paneli (WordPress eklenti ekranı benzeri): GitHub `mca-*` paketlerini listeler; root kullanıcı onay modalıyla güvenli kur / güncelle / kaldır.

## Özellikler

- **Kurulu / kurulabilir / planlanan** bölümleri
- **GitHub keşif** — `mca-*` repoları otomatik listelenir
- **Kur** — allowlist’li VCS + `composer require` (onay modalı)
- **Güncelle** — GitHub tag/release + `composer update` (path’te kapalı)
- **Kaldır** — `composer remove` (korumalı ve path paketler hariç)
- **Root erişim** — `mca/permission` `isRoot()` veya yedek rol kontrolü
- **Ortak UI** — `mca-ui.css` / `mca-ui.js` onay modalları
- **Çoklu dil** — `tr` / `en`

## Kurulum

```bash
composer require mca/hub
php artisan vendor:publish --tag=mca-hub-assets --force
php artisan vendor:publish --tag=mca-permission-assets --force
```

Root ile `/mca` adresine gidin.

## Yapılandırma

```env
MCA_HUB_ENABLED=true
MCA_HUB_GITHUB_CATALOG=true
MCA_HUB_GITHUB_ORG=MCA43
MCA_HUB_UPDATES=true
MCA_HUB_ALLOW_PATH_UPDATE=false
MCA_HUB_LIFECYCLE=true
MCA_HUB_PROTECTED_PACKAGES=mca/hub,mca/permission
```

| Anahtar | Açıklama |
|---------|----------|
| `lifecycle.enabled` | Hub’dan kur / kaldır |
| `lifecycle.protected` | Kaldırılamayan paketler |
| `updates.allow_path_update` | Path paketlerde composer update (varsayılan kapalı) |
| `github.org` / `repo_prefix` | VCS URL allowlist (`github.com/{org}/mca-*`) |

### Path monorepo (Laragon `packages/mca/*`)

Yerel path/symlink paketler **Yerel bağlı** görünür; Hub üzerinden kur/güncelle/kaldır **engellenir**. Geliştirme için kaynak kodu güncelleyin. Üretimde VCS/dist kurulumda lifecycle butonları aktif olur.

```bash
php artisan mca:hub:check-updates --fresh
```

## Güvenlik

- Yalnızca **root**
- Paket adı: `mca/[a-z0-9-]+`
- Kurulum yalnızca katalogdaki paket + izinli GitHub org
- `mca/hub` ve `mca/permission` kaldırılamaz
- Her işlemde **onay modalı**

## Paket kaydı

`composer.json` → `extra.mca` ile title, route, github, icon tanımlanır.
