{{-- Hub card icons (catalog / extra.mca.icon names) --}}
@php
    $name = $name ?? 'box';
    $class = trim('mca-ui-icon '.($class ?? ''));
@endphp
@switch($name)
    @case('shield')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.2-7 9.5C8 19.2 5 15.5 5 11V6l7-3z"/><path stroke-linecap="round" d="M9.5 12l1.8 1.8L15 10"/></svg>
        @break
    @case('cog')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        @break
    @case('box')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 9 5-9 5-9-5 9-5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12v5l9 5 9-5v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v10"/></svg>
        @break
    @case('map-pin')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.5 7-10a7 7 0 1 0-14 0c0 5.5 7 10 7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
        @break
    @case('list')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
        @break
    @case('activity')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2-7 4 14 2-7h6"/></svg>
        @break
    @case('mail')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/></svg>
        @break
    @case('search')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
        @break
    @case('message-square')
    @case('message')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v10H8l-4 3V6z"/></svg>
        @break
    @case('upload')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4"/><path stroke-linecap="round" stroke-linejoin="round" d="m7 9 5-5 5 5"/><path stroke-linecap="round" d="M4 20h16"/></svg>
        @break
    @case('cloud')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18a4.5 4.5 0 0 1-.4-9 6 6 0 0 1 11.6 1.7A3.5 3.5 0 0 1 18 18H7.5z"/></svg>
        @break
    @default
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 9 5-9 5-9-5 9-5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12v5l9 5 9-5v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v10"/></svg>
@endswitch
