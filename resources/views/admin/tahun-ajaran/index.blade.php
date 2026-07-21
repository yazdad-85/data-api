@extends('layouts.admin')

@section('title', 'Tahun ajaran')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Tahun ajaran
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tahun ajaran</h1>
            <p class="page-header__description">
                Kelola tahun ajaran lembaga Anda. Hanya satu tahun ajaran yang dapat aktif dalam satu waktu.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.tahun-ajaran.create') }}">Tambah tahun ajaran</x-ui.button>
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

    @if ($tahunAjarans->isEmpty())
        <x-ui.empty-state
            title="Belum ada tahun ajaran"
            description="Mulai dengan menambahkan tahun ajaran pertama."
        >
            <x-ui.button href="{{ route('admin.tahun-ajaran.create') }}">Tambah tahun ajaran</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($tahunAjarans as $tahunAjaran)
                <tr>
                    <td>{{ $tahunAjaran->nama }}</td>
                    <td>{{ $tahunAjaran->tanggal_mulai->format('d/m/Y') }}</td>
                    <td>{{ $tahunAjaran->tanggal_selesai->format('d/m/Y') }}</td>
                    <td>
                        @if ($tahunAjaran->is_aktif)
                            <x-ui.badge tone="ok">Aktif</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button
                                href="{{ route('admin.tahun-ajaran.edit', $tahunAjaran) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                            @unless ($tahunAjaran->is_aktif)
                                <button type="button" class="btn btn-primary btn-sm" data-modal-open="activate-ta-{{ $tahunAjaran->id }}">
                                    Aktifkan
                                </button>
                            @endunless
                            <button type="button" class="btn btn-danger btn-sm" data-modal-open="delete-ta-{{ $tahunAjaran->id }}">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$tahunAjarans" />
    @endif

    @foreach ($tahunAjarans as $tahunAjaran)
        @unless ($tahunAjaran->is_aktif)
            <x-ui.modal id="activate-ta-{{ $tahunAjaran->id }}" title="Aktifkan tahun ajaran?">
                <p>
                    Mengaktifkan <strong>{{ $tahunAjaran->nama }}</strong> akan berdampak:
                </p>
                <ul>
                    <li>Tahun ajaran yang sedang aktif (jika ada) otomatis dinonaktifkan.</li>
                    <li>Hanya satu tahun ajaran yang dapat aktif dalam satu waktu.</li>
                </ul>

                <x-slot:actions>
                    <form method="dialog">
                        <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin.tahun-ajaran.activate', $tahunAjaran) }}">
                        @csrf
                        <x-ui.button type="submit">Aktifkan</x-ui.button>
                    </form>
                </x-slot:actions>
            </x-ui.modal>
        @endunless

        <x-ui.modal id="delete-ta-{{ $tahunAjaran->id }}" title="Hapus tahun ajaran?">
            <p>
                Menghapus <strong>{{ $tahunAjaran->nama }}</strong> akan berdampak:
            </p>
            <ul>
                <li>Penghapusan bersifat permanen; nama tahun ajaran bisa dipakai lagi.</li>
                <li>Jika masih dipakai oleh kelas atau siswa, penghapusan akan ditolak.</li>
            </ul>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.tahun-ajaran.destroy', $tahunAjaran) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Hapus tahun ajaran</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.modal>
    @endforeach
@endsection
