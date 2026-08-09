<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @if(file_exists(public_path('vendor/mca-permission/mca-ui.css')))
        <link rel="stylesheet" href="{{ asset('vendor/mca-permission/mca-ui.css') }}">
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
                    <form method="post" action="{{ route('mca.hub.updates.refresh') }}" class="mca-hub-inline-form">
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
                    <form method="post" action="{{ route('mca.hub.updates.run') }}" onsubmit="return confirm(@json(mca_hub('updates.confirm', ['package' => $hubPackage['name'], 'version' => $hubPackage['latest_version'] ?? ''])))">
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
            <div class="mca-hub-grid">
                @foreach($packages as $package)
                    <article class="mca-ui-card mca-hub-card mca-hub-card--{{ $package['status'] }} @if(in_array($package['update_status'] ?? '', ['update_available', 'path_linked'], true)) mca-hub-card--update @endif">
                        <div class="mca-hub-card__head">
                            <span class="mca-hub-icon mca-hub-icon--{{ $package['icon'] ?? 'box' }}" aria-hidden="true">
                                @include('mca-hub::partials.icon', [
                                    'name' => $package['icon'] ?? 'box',
                                    'class' => 'mca-ui-icon--sm',
                                ])
                            </span>
                            <div>
                                <h2 class="mca-hub-card__title">{{ $package['title'] }}</h2>
                                <code class="mca-hub-mono">{{ $package['name'] }}</code>
                            </div>
                            <div class="mca-hub-card__badges">
                                <span class="mca-ui-badge mca-hub-status mca-hub-status--{{ $package['status'] }}">
                                    {{ mca_hub('status.'.$package['status']) }}
                                </span>
                                @if(($package['update_status'] ?? '') === 'update_available')
                                    <span class="mca-ui-badge mca-hub-status mca-hub-status--update">{{ mca_hub('status.update_available') }}</span>
                                @elseif(($package['update_status'] ?? '') === 'path_linked')
                                    <span class="mca-ui-badge mca-hub-status mca-hub-status--path">{{ mca_hub('status.path_linked') }}</span>
                                @elseif(($package['update_status'] ?? '') === 'uptodate' && ($package['installed'] ?? false))
                                    <span class="mca-ui-badge mca-hub-status mca-hub-status--uptodate">{{ mca_hub('status.uptodate') }}</span>
                                @endif
                            </div>
                        </div>

                        <p class="mca-hub-card__desc">{{ $package['description'] }}</p>

                        <dl class="mca-hub-card__meta">
                            <div>
                                <dt>{{ mca_hub('card.frameworks') }}</dt>
                                <dd>{{ implode(', ', $package['framework_labels']) }}</dd>
                            </div>
                            @if($package['version'])
                                <div>
                                    <dt>{{ mca_hub('card.installed_version') }}</dt>
                                    <dd>{{ $package['version'] }}</dd>
                                </div>
                            @endif
                            @if(! empty($package['latest_version']))
                                <div>
                                    <dt>{{ mca_hub('card.latest_version') }}</dt>
                                    <dd>{{ $package['latest_version'] }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mca-hub-card__actions">
                            @if($package['route'] && $package['route_exists'])
                                <a href="{{ route($package['route']) }}" class="mca-ui-btn mca-hub-btn mca-hub-btn--primary">
                                    <svg class="mca-ui-icon mca-ui-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M14 5h5v5"/><path stroke-linecap="round" d="M10 14 19 5"/><path stroke-linecap="round" d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4"/></svg>
                                    {{ mca_hub('card.open') }}
                                </a>
                            @elseif($package['installed'] && ! $package['route_exists'] && ! ($package['is_hub'] ?? false))
                                <span class="mca-hub-muted">{{ mca_hub('card.not_routed') }}</span>
                            @elseif(! $package['installed'] && $package['status'] !== 'planned')
                                <code class="mca-hub-mono mca-hub-install">{{ $package['composer'] }}</code>
                            @endif

                            @if($package['can_update'] ?? false)
                                <form method="post" action="{{ route('mca.hub.updates.run') }}" class="mca-hub-inline-form" onsubmit="return confirm(@json(mca_hub('updates.confirm', ['package' => $package['name'], 'version' => $package['latest_version'] ?? ''])))">
                                    @csrf
                                    <input type="hidden" name="package" value="{{ $package['name'] }}">
                                    <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--update">{{ mca_hub('updates.update_now') }}</button>
                                </form>
                            @elseif(($package['update_status'] ?? '') === 'path_linked')
                                <span class="mca-hub-muted mca-hub-update-hint">{{ mca_hub('updates.path_hint', ['version' => $package['latest_version'] ?? '']) }}</span>
                            @endif

                            @if($package['github'])
                                <a href="{{ $package['github'] }}" class="mca-ui-btn mca-hub-btn mca-hub-btn--ghost" target="_blank" rel="noopener">
                                    <svg class="mca-ui-icon mca-ui-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M14 5h5v5"/><path stroke-linecap="round" d="M10 14 19 5"/><path stroke-linecap="round" d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4"/></svg>
                                    {{ mca_hub('card.github') }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
