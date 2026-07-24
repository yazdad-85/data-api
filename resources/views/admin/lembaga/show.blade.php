@extends('layouts.admin')

@section('title', 'Detail lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> / {{ $lembaga->nama }}
@endsection

@section('content')
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

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $lembaga->nama }}</h1>
                <p class="card__meta">
                    Kode <strong>{{ $lembaga->kode }}</strong>
                    &middot;
                    Kode NIY <strong>{{ $lembaga->niy_kode ?? '—' }}</strong>
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
                description="Tambahkan admin lembaga melalui formulir di bawah."
            />
        @else
            <x-ui.table>
                <x-slot:thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Aksi</th>
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
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-secondary btn-sm" data-modal-open="edit-admin-{{ $admin->id }}">
                                    Ubah
                                </button>

                                @if ($admin->is_active)
                                    <form method="POST" action="{{ route('admin.lembaga.admins.deactivate', [$lembaga, $admin]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">Nonaktifkan</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.lembaga.admins.activate', [$lembaga, $admin]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Aktifkan</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.lembaga.admins.reset-password', [$lembaga, $admin]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Reset kata sandi</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <div class="form-card" style="margin-top: 1.5rem;">
            <h3 class="section__title font-display">Tambah admin lembaga</h3>
            <form method="POST" action="{{ route('admin.lembaga.admins.store', $lembaga) }}">
                @csrf

                <div class="form-grid">
                    <x-ui.input
                        name="name"
                        label="Nama"
                        required
                        :value="old('name')"
                        :error="$errors->first('name')"
                    />
                    <x-ui.input
                        name="email"
                        type="email"
                        label="Email"
                        required
                        :value="old('email')"
                        :error="$errors->first('email')"
                        hint="Kata sandi awal dibuat otomatis dan ditampilkan sekali setelah admin dibuat."
                    />
                </div>

                <div class="form-actions">
                    <x-ui.button type="submit">Tambah admin</x-ui.button>
                </div>
            </form>
        </div>
    </section>

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

    <section class="section">
        <div class="section__header">
            <div>
                <h2 class="section__title font-display">API client</h2>
                <p class="section__description">
                    Client aplikasi konsumen yang dapat mengambil data {{ $lembaga->nama }} melalui API key.
                </p>
            </div>
        </div>

        @if ($apiClients->isEmpty())
            <x-ui.empty-state
                title="Belum ada API client"
                description="Tambahkan API client melalui formulir di bawah."
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
                        <th>Aksi</th>
                    </tr>
                </x-slot:thead>
                @foreach ($apiClients as $apiClient)
                    <tr>
                        <td>{{ $apiClient->nama }}</td>
                        <td><code>{{ $apiClient->api_key_prefix }}</code></td>
                        <td>
                            @foreach ($apiClient->scopes as $scope)
                                <x-ui.badge tone="neutral">{{ $apiScopeLabels[$scope] ?? $scope }}</x-ui.badge>
                            @endforeach
                        </td>
                        <td>{{ $apiFieldProfileLabels[$apiClient->field_profile] ?? $apiClient->field_profile }}</td>
                        <td>
                            @if ($apiClient->revoked_at !== null)
                                <x-ui.badge tone="danger">Dicabut</x-ui.badge>
                            @elseif ($apiClient->is_active)
                                <x-ui.badge tone="ok">Aktif</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                @if ($apiClient->revoked_at === null)
                                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="edit-api-client-{{ $apiClient->id }}">
                                        Ubah
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="rotate-api-client-{{ $apiClient->id }}">
                                        Rotate key
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-modal-open="revoke-api-client-{{ $apiClient->id }}">
                                        Cabut
                                    </button>
                                @else
                                    <span class="section__note">Client ini sudah dicabut.</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <div class="form-card" style="margin-top: 1.5rem;">
            <h3 class="section__title font-display">Tambah API client</h3>
            <form method="POST" action="{{ route('admin.lembaga.api-clients.store', $lembaga) }}">
                @csrf

                <div class="form-grid">
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
    </section>

    @foreach ($apiClients as $apiClient)
        @if ($apiClient->revoked_at === null)
            <x-ui.modal id="edit-api-client-{{ $apiClient->id }}" title="Ubah API client">
                <form method="POST" action="{{ route('admin.lembaga.api-clients.update', [$lembaga, $apiClient]) }}">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label for="edit-api-client-nama-{{ $apiClient->id }}" class="field-label">
                            Nama <span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="edit-api-client-nama-{{ $apiClient->id }}"
                            type="text"
                            name="nama"
                            value="{{ $apiClient->nama }}"
                            required
                            class="field-control"
                        >
                    </div>

                    <div class="field">
                        <label for="edit-api-client-profile-{{ $apiClient->id }}" class="field-label">
                            Profil data <span class="field-required" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="edit-api-client-profile-{{ $apiClient->id }}"
                            name="field_profile"
                            required
                            class="field-control"
                        >
                            @foreach ($apiFieldProfileLabels as $value => $label)
                                <option value="{{ $value }}" @selected($apiClient->field_profile === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
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
                                        @checked(in_array($value, $apiClient->scopes, true))
                                    >
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-actions">
                        <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                        <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                    </div>
                </form>
            </x-ui.modal>

            <x-ui.modal id="rotate-api-client-{{ $apiClient->id }}" title="Rotate API key?">
                <p>
                    Rotate key untuk <strong>{{ $apiClient->nama }}</strong> akan berdampak:
                </p>
                <ul>
                    <li>Key lama (<code>{{ $apiClient->api_key_prefix }}</code>) langsung tidak berlaku dan tidak dapat digunakan lagi.</li>
                    <li>Integrator harus segera memasang key baru yang ditampilkan sekali setelah rotate.</li>
                    <li>ID client dan konfigurasi scope/profil data tidak berubah.</li>
                </ul>

                <x-slot:actions>
                    <form method="dialog">
                        <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin.lembaga.api-clients.rotate', [$lembaga, $apiClient]) }}">
                        @csrf
                        <x-ui.button variant="danger" type="submit">Rotate key</x-ui.button>
                    </form>
                </x-slot:actions>
            </x-ui.modal>

            <x-ui.modal id="revoke-api-client-{{ $apiClient->id }}" title="Cabut API client?">
                <p>
                    Mencabut <strong>{{ $apiClient->nama }}</strong> akan berdampak:
                </p>
                <ul>
                    <li>Client tidak dapat lagi melakukan autentikasi ke API (mulai Milestone 7).</li>
                    <li>Pencabutan bersifat final; tidak ada tombol untuk mengaktifkan kembali key ini.</li>
                    <li>Jika integrator masih memerlukan akses, buat API client baru.</li>
                </ul>

                <x-slot:actions>
                    <form method="dialog">
                        <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin.lembaga.api-clients.revoke', [$lembaga, $apiClient]) }}">
                        @csrf
                        <x-ui.button variant="danger" type="submit">Cabut API client</x-ui.button>
                    </form>
                </x-slot:actions>
            </x-ui.modal>
        @endif
    @endforeach

    @foreach ($admins as $admin)
        <x-ui.modal id="edit-admin-{{ $admin->id }}" title="Ubah admin lembaga">
            <form method="POST" action="{{ route('admin.lembaga.admins.update', [$lembaga, $admin]) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="edit-name-{{ $admin->id }}" class="field-label">
                        Nama <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="edit-name-{{ $admin->id }}"
                        type="text"
                        name="name"
                        value="{{ $admin->name }}"
                        required
                        class="field-control"
                    >
                </div>

                <div class="field">
                    <label for="edit-email-{{ $admin->id }}" class="field-label">
                        Email <span class="field-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="edit-email-{{ $admin->id }}"
                        type="email"
                        name="email"
                        value="{{ $admin->email }}"
                        required
                        class="field-control"
                    >
                </div>

                <div class="form-actions">
                    <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                    <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                </div>
            </form>
        </x-ui.modal>
    @endforeach

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
