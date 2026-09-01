@extends('layouts.admin')

@section('title', 'API client')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / API client
@endsection

@section('content')
    @php
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser?->isSuperAdmin() ?? false;
        $apiScopeLabels = [
            'tahun_ajaran:read' => 'Tahun ajaran (baca)',
            'guru:read' => 'Guru (baca)',
            'kelas:read' => 'Kelas (baca)',
            'siswa:read' => 'Siswa (baca)',
            'karyawan:read' => 'Karyawan (baca)',
        ];
        $apiFieldProfileLabels = [
            'minimal' => 'Minimal',
            'academic' => 'Akademik',
            'contact' => 'Kontak',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">API client</h1>
            <p class="page-header__description">
                Kelola client aplikasi konsumen yang dapat mengambil data lembaga melalui API key.
            </p>
        </div>
    </div>

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

    @if ($isSuperAdmin)
        <form method="GET" action="{{ route('admin.api-clients.index') }}" class="toolbar">
            <select name="lembaga_id" class="field-control" aria-label="Filter lembaga API client">
                <option value="" @selected($selectedLembagaId === '')>Semua lembaga</option>
                @foreach ($lembagaOptions as $lembaga)
                    <option value="{{ $lembaga->id }}" @selected($selectedLembagaId === $lembaga->id)>
                        {{ $lembaga->nama }}
                    </option>
                @endforeach
            </select>
            <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
            @if ($selectedLembagaId !== '')
                <x-ui.button href="{{ route('admin.api-clients.index') }}" variant="secondary">Semua lembaga</x-ui.button>
            @endif
        </form>
    @endif

    <div class="form-card form-card--wide" style="margin-bottom: 1.5rem;">
        <h2 class="section__title font-display">Tambah API client</h2>
        <form method="POST" action="{{ route('admin.api-clients.store') }}">
            @csrf

            <div class="form-grid">
                @if ($isSuperAdmin)
                    <x-ui.select
                        name="lembaga_id"
                        label="Lembaga"
                        required
                        :error="$errors->first('lembaga_id')"
                    >
                        <option value="">Pilih lembaga</option>
                        @foreach ($lembagaOptions as $lembaga)
                            <option value="{{ $lembaga->id }}" @selected(old('lembaga_id', $selectedLembagaId) === $lembaga->id)>
                                {{ $lembaga->nama }}
                            </option>
                        @endforeach
                    </x-ui.select>
                @endif

                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama')"
                    :error="$errors->first('nama')"
                />

                <x-ui.select
                    name="field_profile"
                    label="Profil data"
                    required
                    :error="$errors->first('field_profile')"
                    hint="Menentukan field yang disertakan saat data diambil melalui API."
                >
                    @foreach ($apiFieldProfileLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('field_profile', 'minimal') === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="field">
                <span class="field-label">
                    Scope <span class="field-required" aria-hidden="true">*</span>
                </span>
                <div class="checkbox-group">
                    @foreach ($apiScopeLabels as $value => $label)
                        <label class="checkbox-option">
                            <input
                                type="checkbox"
                                name="scopes[]"
                                value="{{ $value }}"
                                @checked(in_array($value, old('scopes', []), true))
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('scopes')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Tambah API client</x-ui.button>
            </div>
        </form>
    </div>

    @if ($clients->isEmpty())
        <x-ui.empty-state
            title="Belum ada API client"
            description="Buat API client pertama untuk membuka akses integrasi data."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    @if ($isSuperAdmin)
                        <th>Lembaga</th>
                    @endif
                    <th>Nama</th>
                    <th>Prefix</th>
                    <th>Scope</th>
                    <th>Profil data</th>
                    <th>Status</th>
                    <th>Terakhir digunakan</th>
                </tr>
            </x-slot:thead>
            @foreach ($clients as $client)
                <tr>
                    @if ($isSuperAdmin)
                        <td>
                            @if ($client->lembaga !== null)
                                <a href="{{ route('admin.lembaga.show', $client->lembaga) }}">{{ $client->lembaga->nama }}</a>
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td>{{ $client->nama }}</td>
                    <td><code>{{ $client->api_key_prefix }}</code></td>
                    <td>
                        @foreach ($client->scopes as $scope)
                            <x-ui.badge tone="neutral">{{ $apiScopeLabels[$scope] ?? $scope }}</x-ui.badge>
                        @endforeach
                    </td>
                    <td>{{ $apiFieldProfileLabels[$client->field_profile] ?? $client->field_profile }}</td>
                    <td>
                        @if ($client->revoked_at !== null)
                            <x-ui.badge tone="danger">Dicabut</x-ui.badge>
                        @elseif ($client->is_active)
                            <x-ui.badge tone="ok">Aktif</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>{{ $client->last_used_at?->format('d/m/Y H:i') ?? 'Belum pernah' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
@endsection
