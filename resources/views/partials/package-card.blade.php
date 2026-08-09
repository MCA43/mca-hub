@php
    /** @var array<string, mixed> $package */
    $np = config('hub.routes.name_prefix', 'mca.hub.');
@endphp
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
            @elseif(($package['update_status'] ?? '') === 'path_linked' || ($package['is_path'] ?? false))
                <span class="mca-ui-badge mca-hub-status mca-hub-status--path">{{ mca_hub('status.path_linked') }}</span>
            @elseif(($package['update_status'] ?? '') === 'uptodate' && ($package['installed'] ?? false))
                <span class="mca-ui-badge mca-hub-status mca-hub-status--uptodate">{{ mca_hub('status.uptodate') }}</span>
            @endif
            @if($package['is_protected'] ?? false)
                <span class="mca-ui-badge mca-hub-status mca-hub-status--protected">{{ mca_hub('status.protected') }}</span>
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
        @endif

        @if($package['can_install'] ?? false)
            <form method="post"
                  action="{{ route($np.'packages.install') }}"
                  class="mca-hub-inline-form"
                  data-mca-confirm="{{ mca_hub('lifecycle.confirm_install', ['package' => $package['name']]) }}"
                  data-mca-confirm-title="{{ mca_hub('lifecycle.confirm_title') }}"
                  data-mca-confirm-text="{{ mca_hub('lifecycle.install') }}"
                  data-mca-confirm-danger="0">
                @csrf
                <input type="hidden" name="package" value="{{ $package['name'] }}">
                <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--primary">{{ mca_hub('lifecycle.install') }}</button>
            </form>
        @elseif(($package['status'] ?? '') === 'available' && ! ($lifecycleEnabled ?? true))
            <code class="mca-hub-mono mca-hub-install">{{ $package['composer'] }}</code>
        @elseif(($package['status'] ?? '') === 'available' && ! ($package['can_install'] ?? false))
            <code class="mca-hub-mono mca-hub-install">{{ $package['composer'] }}</code>
        @endif

        @if($package['can_update'] ?? false)
            <form method="post"
                  action="{{ route($np.'updates.run') }}"
                  class="mca-hub-inline-form"
                  data-mca-confirm="{{ mca_hub('updates.confirm', ['package' => $package['name'], 'version' => $package['latest_version'] ?? '']) }}"
                  data-mca-confirm-title="{{ mca_hub('lifecycle.confirm_title') }}"
                  data-mca-confirm-text="{{ mca_hub('updates.update_now') }}"
                  data-mca-confirm-danger="0">
                @csrf
                <input type="hidden" name="package" value="{{ $package['name'] }}">
                <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--update">{{ mca_hub('updates.update_now') }}</button>
            </form>
        @elseif(($package['update_status'] ?? '') === 'path_linked')
            <span class="mca-hub-muted mca-hub-update-hint">{{ mca_hub('updates.path_hint', ['version' => $package['latest_version'] ?? '']) }}</span>
        @endif

        @if($package['can_remove'] ?? false)
            <form method="post"
                  action="{{ route($np.'packages.remove') }}"
                  class="mca-hub-inline-form"
                  data-mca-confirm="{{ mca_hub('lifecycle.confirm_remove', ['package' => $package['name']]) }}"
                  data-mca-confirm-title="{{ mca_hub('lifecycle.confirm_title') }}"
                  data-mca-confirm-text="{{ mca_hub('lifecycle.remove') }}"
                  data-mca-confirm-danger="1">
                @csrf
                <input type="hidden" name="package" value="{{ $package['name'] }}">
                <button type="submit" class="mca-ui-btn mca-hub-btn mca-hub-btn--danger">{{ mca_hub('lifecycle.remove') }}</button>
            </form>
        @elseif(($package['installed'] ?? false) && ($package['is_path'] ?? false))
            <span class="mca-hub-muted">{{ mca_hub('lifecycle.path_hint_remove') }}</span>
        @elseif(($package['installed'] ?? false) && ($package['is_protected'] ?? false))
            <span class="mca-hub-muted">{{ mca_hub('lifecycle.protected_hint') }}</span>
        @endif

        @if($package['github'])
            <a href="{{ $package['github'] }}" class="mca-ui-btn mca-hub-btn mca-hub-btn--ghost" target="_blank" rel="noopener">
                <svg class="mca-ui-icon mca-ui-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M14 5h5v5"/><path stroke-linecap="round" d="M10 14 19 5"/><path stroke-linecap="round" d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4"/></svg>
                {{ mca_hub('card.github') }}
            </a>
        @endif
    </div>
</article>
