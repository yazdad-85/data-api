<x-ui.table class="dashboard-family-table">
    <x-slot:thead>
        <tr>
            @if ($stats['role'] === 'super_admin')
                <th class="dashboard-family-table__text">Lembaga</th>
            @endif
            <th class="dashboard-family-table__text">Tahun ajaran</th>
            <th class="dashboard-family-table__text">Kelas</th>
            <th class="dashboard-family-table__count">Total aktif</th>
            @foreach ($familyLabels as $status)
                <th class="dashboard-family-table__count">{{ $familyShortLabels[$status] }}</th>
            @endforeach
        </tr>
    </x-slot:thead>
    @foreach ($rows as $row)
        <tr>
            @if ($stats['role'] === 'super_admin')
                <td class="dashboard-family-table__text">{{ $row['lembaga_nama'] }}</td>
            @endif
            <td class="dashboard-family-table__text">{{ $row['tahun_ajaran_nama'] }}</td>
            <td class="dashboard-family-table__text">{{ $row['kelas_nama'] }}</td>
            <td class="dashboard-family-table__count">{{ $row['total'] }}</td>
            @foreach ($familyLabels as $status)
                <td class="dashboard-family-table__count">{{ $row['statuses'][$status] ?? 0 }}</td>
            @endforeach
        </tr>
    @endforeach
</x-ui.table>
