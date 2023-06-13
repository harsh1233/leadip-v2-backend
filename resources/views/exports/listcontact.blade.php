<table>
    <thead>
        <tr>
            @if($listname->sub_type == 'C')
            <th>Company</th>
            @else
            <th>First Name</th>
            <th>Last Name</th>
            @endif
            <th>Email</th>
            <th>Location</th>
            <th>List Type</th>
        </tr>
    </thead>
    <tbody>
        @foreach($contacts as $value)
        <tr>
            @if($listname->sub_type == 'C')
            <td>{{ $value->company_name ?? '-'}}</td>
            @else
            <td>{{ $value->first_name }}</td>
            <td>{{ $value->last_name }}</td>
            @endif
            <td>{{ $value->email}}</td>
            <td>{{ $value->city_details->name ?? '-'}},{{$value->country_details->name ?? '-'}}</td>
            <td>{{ $listname->listType}}</td>
        </tr>
        @endforeach
    </tbody>
</table>
