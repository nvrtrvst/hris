<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Pegawai</th>
            <th>Unit</th>
            <th>Bank</th>
            <th>No. Rekening</th>
            <th>Gaji Bersih</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row['nama'] }}</td>
            <td>{{ $row['unit'] }}</td>
            <td>{{ $row['bank'] }}</td>
            <td>{{ $row['no_rekening'] }}</td>
            <td>{{ $row['nominal'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>