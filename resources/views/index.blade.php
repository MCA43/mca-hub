<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @php
        $uiCss = config('hub.ui.assets.ui_css', 'vendor/mca-permission/mca-ui.css');
        $uiJs = config('hub.ui.assets.ui_js', 'vendor/mca-permission/mca-ui.js');
    @endphp
    @if(file_exists(public_path($uiCss)))
        <link rel="stylesheet" href="{{ asset($uiCss) }}">
    @endif
    <link rel="stylesheet" href="{{ asset(config('hub.ui.assets.css', 'vendor/mca-hub/mca-hub.css')) }}">
</head>
<body class="mca-ui-root mca-hub-root">
    <header class="mca-ui-shell mca-hub-shell">
        <div class="mca-ui-shell__wrap">
            <div class="mca-ui-shell__inner">
                <a href="{{ route('mca.hub.index') }}" class="mca-ui-brand">
                    <span class="mca-ui-brand__mark" aria-hidden="true">
                        <svg class="mca-ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
                    </span>
                    <span>{{ $title }}</span>
                </a>
            </div>
            <div class="mca-hub-meta">
                <span class="mca-ui-badge mca-hub-badge mca-hub-badge--framework">{{ mca_hub('meta.framework') }}: {{ $frameworkLabel }}</span>
                @if($updatesEnabled ?? false)
                    <form method="post" action="{{ route('mca.hub.updates.refresh') }}" class="mca-hub-inline-form" data-mca-busy="{{ mca_hub('lifecycle.busy_refresh') }}">
                        @csrf
                        <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--ghost mca-hub-btn--sm">{{ mca_hub('updates.refresh') }}</button>
                    </form>
                @endif
                @if($catalogUpdatedAt)
                    <span class="mca-hub-muted">{{ mca_hub('meta.catalog_updated', ['date' => $catalogUpdatedAt]) }}</span>
                @elseif($catalogUrl)
                    <span class="mca-hub-muted">{{ mca_hub('meta.catalog_remote') }}</span>
                @elseif(in_array('github', $catalogSources ?? [], true))
                    <span class="mca-hub-muted">{{ mca_hub('meta.catalog_github', ['org' => config('hub.github.org', 'MCA43')]) }}</span>
                @else
                    <span class="mca-hub-muted">{{ mca_hub('meta.catalog_local') }}</span>
                @endif
            </div>
        </div>
        <p class="mca-hub-subtitle mca-hub-subtitle--bar">{{ mca_hub('app.subtitle') }}</p>
    </header>

    <main class="mca-ui-main mca-hub-main">
        @if (session('status'))
            <div class="mca-ui-alert mca-ui-alert--success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mca-ui-alert mca-ui-alert--danger" role="alert">
                <ul class="mca-ui-alert__list">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if(($updatesEnabled ?? false) && ($updateCount ?? 0) > 0)
            <div class="mca-hub-update-banner" role="status">
                <div>
                    <strong>{{ mca_hub('updates.banner_title') }}</strong>
                    <p>{{ mca_hub('updates.banner_body', ['count' => $updateCount]) }}</p>
                </div>
                @if(($hubPackage['can_update'] ?? false) === true)
                    <form method="post"
                          action="{{ route('mca.hub.updates.run') }}"
                          data-mca-confirm="{{ mca_hub('updates.confirm', ['package' => $hubPackage['name'], 'version' => $hubPackage['latest_version'] ?? '']) }}"
                          data-mca-confirm-title="{{ mca_hub('lifecycle.confirm_title') }}"
                          data-mca-confirm-text="{{ mca_hub('updates.update_hub') }}"
                          data-mca-confirm-danger="0"
                          data-mca-busy="{{ mca_hub('lifecycle.busy_update', ['package' => $hubPackage['name']]) }}">
                        @csrf
                        <input type="hidden" name="package" value="{{ $hubPackage['name'] }}">
                        <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--update">{{ mca_hub('updates.update_hub') }}</button>
                    </form>
                @elseif(in_array($hubPackage['update_status'] ?? '', ['update_available', 'path_linked'], true))
                    <span class="mca-hub-muted">{{ mca_hub('updates.hub_path_hint', ['version' => $hubPackage['latest_version'] ?? '']) }}</span>
                @endif
            </div>
        @endif

        @if(count($packages) === 0)
            <div class="mca-hub-empty">
                {{ mca_hub('empty', ['framework' => $frameworkLabel]) }}
            </div>
        @else
            @if(count($installedPackages) > 0)
                <section class="mca-hub-section" aria-labelledby="mca-hub-installed">
                    <h2 id="mca-hub-installed" class="mca-hub-section__title">{{ mca_hub('sections.installed') }}</h2>
                    <p class="mca-hub-section__help">{{ mca_hub('sections.installed_help') }}</p>
                    <div class="mca-hub-grid">
                        @foreach($installedPackages as $package)
                            @include('mca-hub::partials.package-card', ['package' => $package, 'lifecycleEnabled' => $lifecycleEnabled ?? true])
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($availablePackages) > 0)
                <section class="mca-hub-section" aria-labelledby="mca-hub-available">
                    <h2 id="mca-hub-available" class="mca-hub-section__title">{{ mca_hub('sections.available') }}</h2>
                    <p class="mca-hub-section__help">{{ mca_hub('sections.available_help') }}</p>
                    <div class="mca-hub-grid">
                        @foreach($availablePackages as $package)
                            @include('mca-hub::partials.package-card', ['package' => $package, 'lifecycleEnabled' => $lifecycleEnabled ?? true])
                        @endforeach
                    </div>
                </section>
            @endif

            @if(count($plannedPackages) > 0)
                <section class="mca-hub-section" aria-labelledby="mca-hub-planned">
                    <h2 id="mca-hub-planned" class="mca-hub-section__title">{{ mca_hub('sections.planned') }}</h2>
                    <p class="mca-hub-section__help">{{ mca_hub('sections.planned_help') }}</p>
                    <div class="mca-hub-grid">
                        @foreach($plannedPackages as $package)
                            @include('mca-hub::partials.package-card', ['package' => $package, 'lifecycleEnabled' => $lifecycleEnabled ?? true])
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </main>

    <div id="mca-hub-busy" class="mca-hub-busy" hidden aria-hidden="true" role="alertdialog" aria-live="assertive" aria-busy="true">
        <div class="mca-hub-busy__panel">
            <div class="mca-hub-busy__spinner" aria-hidden="true"></div>
            <p class="mca-hub-busy__title">{{ mca_hub('lifecycle.busy') }}</p>
            <p class="mca-hub-busy__msg" data-mca-hub-busy-msg>{{ mca_hub('lifecycle.busy') }}</p>
        </div>
    </div>

    @php
        $mcaUiI18n = [
            'ok' => mca_hub('modal.ok'),
            'confirm' => mca_hub('modal.confirm'),
            'cancel' => mca_hub('modal.cancel'),
            'close' => mca_hub('modal.close'),
            'alert_title' => mca_hub('modal.alert_title'),
            'confirm_title' => mca_hub('modal.confirm_title'),
        ];
        $mcaHubI18n = [
            'busy' => mca_hub('lifecycle.busy'),
        ];
        $hubJs = config('hub.ui.assets.js', 'vendor/mca-hub/mca-hub.js');
    @endphp
    <script>window.McaUiI18n = @json($mcaUiI18n); window.McaHubI18n = @json($mcaHubI18n);</script>
    @if(file_exists(public_path($uiJs)))
        <script src="{{ asset($uiJs) }}" defer></script>
    @endif
    @if(file_exists(public_path($hubJs)))
        <script src="{{ asset($hubJs) }}" defer></script>
    @endif
</body>
</html>
