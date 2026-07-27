@php
    $branding = app_branding();
    $heroTitle = $heroTitle ?? $branding['name'];
    $heroBody = $heroBody ?? 'Pusat data pendidikan yang aman, terintegrasi, dan siap dipakai operasional harian.';
    $heroEyebrow = $heroEyebrow ?? 'Pusat Data Terintegrasi';
    $heroBadges = $heroBadges ?? ['Aman', 'Terintegrasi', 'Siap sinkron'];
@endphp

<section class="auth-hero" data-auth-hero>
    <div class="auth-hero__inner">
        <p class="auth-hero__eyebrow">{{ $heroEyebrow }}</p>

        <div class="auth-hero__brand">
            @if ($branding['logo_url'])
                <img src="{{ $branding['logo_url'] }}" alt="" class="auth-hero__brand-logo">
            @endif
            <span class="auth-hero__brand-name font-display">{{ $branding['name'] }}</span>
        </div>

        <h1 class="auth-hero__title font-display">{{ $heroTitle }}</h1>
        <p class="auth-hero__body">{{ $heroBody }}</p>

        <div class="auth-hero__badges" aria-label="Keunggulan sistem">
            @foreach ($heroBadges as $badge)
                <span class="auth-hero__badge">{{ $badge }}</span>
            @endforeach
        </div>

        <div class="auth-hero__visual" aria-hidden="true">
            <div class="auth-hero__orb auth-hero__orb--one"></div>
            <div class="auth-hero__orb auth-hero__orb--two"></div>
            <div class="auth-hero__orbit auth-hero__orbit--one"></div>
            <div class="auth-hero__orbit auth-hero__orbit--two"></div>
            <img src="{{ asset('images/auth/guardian.webp') }}" alt="" class="auth-hero__guardian">
        </div>
    </div>
</section>
