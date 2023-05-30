<table>
    <thead>
        <tr>
            <th>List Name</th>
            <th>List Updated</th>
            <th>Creator</th>
            <!-- <th>List Size</th> -->
            <th>Type</th>
        </tr>
    </thead>
    <tbody>
        @foreach($contactlist as $key=>$value)
        <tr>
            <td>{{ $value->name }}</td>
            <td>{{ date('d.m.Y',strtotime($value->created_at)) }}</td>
            <td>{{ $value->users->full_name }}</td>
            <!-- <td>{{ $value->size }}</td> -->
            <td>{{ $value->listtype }}</td>
        </tr>
        @endforeach
    </tbody>
</table>