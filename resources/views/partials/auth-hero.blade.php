@php
    $branding = app_branding();
    $heroTitle = $heroTitle ?? $branding['name'];
    $heroBody = $heroBody ?? 'Pusat data pendidikan yang aman, terintegrasi, dan siap dipakai operasional harian.';
    $heroEyebrow = $heroEyebrow ?? 'Pusat Data Terintegrasi';
    $heroBadges = $heroBadges ?? ['Aman', 'Terintegrasi', 'Siap sinkron'];
@endphp

<section class="auth-hero" data-auth-hero>
    {{-- Dekoratif orbit + orb (absolute, belakang) --}}
    <div class="auth-hero__orb auth-hero__orb--one" aria-hidden="true"></div>
    <div class="auth-hero__orb auth-hero__orb--two" aria-hidden="true"></div>
    <div class="auth-hero__orbit auth-hero__orbit--one" aria-hidden="true"></div>
    <div class="auth-hero__orbit auth-hero__orbit--two" aria-hidden="true"></div>

    {{-- Karakter penjaga: absolute di kanan bawah hero --}}
    <img
        src="{{ asset('images/auth/guardian-cutout.png') }}"
        alt=""
        class="auth-hero__guardian"
        aria-hidden="true"
    >

    {{-- Teks content di kiri, di atas karakter --}}
    <div class="auth-hero__content">
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
    </div>
</section>
