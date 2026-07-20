@extends('layouts.admin')

@section('title', $lembaga->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> / {{ $lembaga->nama }}
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $lembaga->nama }}</h1>
                <p class="card__meta">
                    Kode <strong>{{ $lembaga->kode }}</strong>
                    &middot;
                    @if ($lembaga->is_active)
                        <x-ui.badge tone="ok">Aktif</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                    @endif
                </p>
            </div>
            <div class="card__actions">
                <x-ui.button href="{{ route('admin.lembaga.edit', $lembaga) }}" variant="secondary">Ubah</x-ui.button>
                @if ($lembaga->is_active)
                    <button type="button" class="btn btn-danger" data-modal-open="deactivate-lembaga">
                        Nonaktifkan lembaga
                    </button>
                @else
                    <form method="POST" action="{{ route('admin.lembaga.activate', $lembaga) }}">
                        @csrf
                        <x-ui.button type="submit">Aktifkan lembaga</x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        <dl class="detail-grid">
            <div>
                <dt class="detail-grid__label">Jenis</dt>
                <dd class="detail-grid__value">{{ $lembaga->jenis ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon</dt>
                <dd class="detail-grid__value">{{ $lembaga->telepon ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Email</dt>
                <dd class="detail-grid__value">{{ $lembaga->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Kota</dt>
                <dd class="detail-grid__value">{{ $lembaga->kota ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Provinsi</dt>
                <dd class="detail-grid__value">{{ $lembaga->provinsi ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alamat</dt>
                <dd class="detail-grid__value">{{ $lembaga->alamat ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <section class="section">
        <div class="section__header">
            <div>
                <h2 class="section__title font-display">Admin lembaga</h2>
                <p class="section__description">
                    Admin Lembaga yang dapat masuk dan mengelola data {{ $lembaga->nama }}.
                </p>
            </div>
        </div>

        @if ($admins->isEmpty())
            <x-ui.empty-state
                title="Belum ada admin lembaga"
                description="Aksi admin dilengkapi di langkah berikutnya."
            />
        @else
            <x-ui.table>
                <x-slot:thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </x-slot:thead>
                @foreach ($admins as $admin)
                    <tr>
                        <td>{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>
                            @if ($admin->is_active)
                                <x-ui.badge tone="ok">Aktif</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <p class="section__note">
                Aksi admin (tambah, ubah, aktif/nonaktif, reset password) dilengkapi di langkah berikutnya.
            </p>
        @endif
    </section>

    <x-ui.modal id="deactivate-lembaga" title="Nonaktifkan lembaga?">
        <p>
            Menonaktifkan <strong>{{ $lembaga->nama }}</strong> akan berdampak:
        </p>
        <ul>
            <li>Seluruh Admin Lembaga tidak bisa login; sesi yang aktif langsung berakhir.</li>
            <li>API key lembaga ini akan ditolak saat verifikasi (mulai Milestone 7).</li>
            <li>Super Admin dapat mengaktifkan kembali lembaga ini kapan saja.</li>
        </ul>
        <p>
            <strong>{{ $adminsAktif }}</strong> Admin Lembaga aktif dan
            <strong>{{ $apiClientsAktif }}</strong> API client aktif akan terdampak.
        </p>

        <x-slot:actions>
            <form method="dialog">
                <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
            </form>
            <form method="POST" action="{{ route('admin.lembaga.deactivate', $lembaga) }}">
                @csrf
                <x-ui.button variant="danger" type="submit">Nonaktifkan lembaga</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.modal>
@endsection
