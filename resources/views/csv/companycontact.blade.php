<table>
    <thead>
        <tr>
            <th align="center">company name</th>
            <th align="center">email</th>
            <th align="center">City</th>
            <th align="center">phone_number</th>
        </tr>
    </thead>
    <tbody>

        @if (isset($contact))

            @foreach ($contact as $value)
                <tr>
                    <td>{{ $value->company_name ?? null }}</td>
                    <td>{{ $value->email ?? null }}</td>
                    <td>{{ $value->city_details->name ?? null }}</td>
                    <td>{{ $value->phone_number ?? null }} </td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
