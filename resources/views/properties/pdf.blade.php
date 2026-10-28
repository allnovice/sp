<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h2 { text-align: center; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 5px; text-align: left; }
        th { background: #eee; }
        .total { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Property Register</h2>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($properties as $property)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $value = data_get($property, $column->key)
                                ?? data_get($property->custom_fields ?? [], $column->key, '');
                        @endphp
                        <td>
                            @if ($column->label === 'Unit Value' && is_numeric($value))
                                ₱{{ number_format((float) $value, 2) }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr>
                <td colspan="{{ max(1, $columns->count() - 1) }}" class="total">
                    TOTAL UNIT VALUE
                </td>
                <td class="total">₱{{ number_format($total, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
