@extends('layouts.admin')

@section('title', 'API client')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / API client
@endsection

@section('content')
    @php
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
                Daftar client aplikasi konsumen yang dapat mengambil data lembaga Anda melalui API key.
            </p>
        </div>
    </div>

    @if ($clients->isEmpty())
        <x-ui.empty-state
            title="Belum ada API client"
            description="Hubungi Super Admin untuk membuat API client bagi lembaga Anda."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
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
