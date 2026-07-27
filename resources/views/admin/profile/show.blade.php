@extends('layouts.admin')

@section('title', 'Profil')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Profil
@endsection

@section('content')
    @php
        $roleLabel = match ($user->role) {
            'super_admin' => 'Super Admin',
            'admin_lembaga' => 'Admin Lembaga',
            default => $user->role,
        };
    @endphp

    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Periksa kembali data yang dikirim:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Profil</h1>
            <p class="page-header__description">Kelola nama dan keamanan akun Anda.</p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <span class="field-label">Email</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $user->email }}</p>
                </div>
                <div class="field">
                    <span class="field-label">Role</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $roleLabel }}</p>
                </div>
                @if ($user->isAdminLembaga())
                    <div class="field">
                        <span class="field-label">Lembaga</span>
                        <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $user->lembaga?->nama ?? '—' }}</p>
                    </div>
                @endif
                <x-ui.input
                    name="name"
                    label="Nama"
                    required
                    :value="old('name', $user->name)"
                    :error="$errors->first('name')"
                />
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            </div>
        </form>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.profile.password') }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="current_password"
                    type="password"
                    label="Password saat ini"
                    required
                    :value="''"
                    :error="$errors->first('current_password')"
                />
                <x-ui.input
                    name="password"
                    type="password"
                    label="Password baru"
                    required
                    :value="''"
                    :error="$errors->first('password')"
                    hint="Minimal 12 karakter dan berbeda dari password saat ini."
                />
                <x-ui.input
                    name="password_confirmation"
                    type="password"
                    label="Konfirmasi password baru"
                    required
                    :value="''"
                    :error="$errors->first('password_confirmation')"
                />
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Ganti password</x-ui.button>
            </div>
        </form>
    </div>
@endsection
