<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        h2 {
            text-align: center;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #888;
            padding: 5px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .total {
            text-align: right;
            font-weight: bold;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <h2>Property Register</h2>

    @php
        // Change this number to test pagination.
        $maxGroupsPerPage = 6;

        $groups = [];
        $propertyKey = 'property_no';

        foreach ($properties as $property) {
            $row = [];

            foreach ($columns as $column) {
                $row[$column->key] =
                    data_get($property, $column->key)
                    ?? data_get(
                        $property->custom_fields ?? [],
                        $column->key,
                        ''
                    );
            }

            $propertyNo = trim(
                (string) ($row[$propertyKey] ?? '')
            );

            if (preg_match(
                '/^(.+)-([A-D])-(\d+)-([A-Z]+)$/',
                $propertyNo,
                $matches
            )) {
                $groupKey = $matches[1] . '-'
                    . $matches[3] . '-'
                    . $matches[4];
            } else {
                $groupKey = 'single_' . count($groups);
            }

            $groups[$groupKey][] = $row;
        }

        $groups = array_values($groups);
        $groupIndex = 0;
    @endphp

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>

        @foreach ($groups as $group)
            @if (
                $groupIndex > 0
                && $groupIndex % $maxGroupsPerPage === 0
            )
                </tbody>
            </table>

            <div class="page-break"></div>

            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column->label }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody>
                @php
                    $rowCount = count($group);
                    $plans = [];

                    foreach ($columns as $column) {
                        $key = $column->key;

                        if ($key === 'property_no') {
                            continue;
                        }

                        $nonEmpty = [];

                        foreach ($group as $i => $row) {
                            $value = $row[$key] ?? '';

                            if (
                                $value !== null
                                && trim((string) $value) !== ''
                            ) {
                                $nonEmpty[] = $i;
                            }
                        }

                        if (count($nonEmpty) === 0) {
                            $plans[$key][0] = [
                                'rowspan' => $rowCount,
                                'value' => '',
                            ];
                            continue;
                        }

                        if (count($nonEmpty) === 1) {
                            $plans[$key][0] = [
                                'rowspan' => $rowCount,
                                'value' => $group[$nonEmpty[0]][$key],
                            ];
                            continue;
                        }

                        $i = 0;

                        while ($i < $rowCount) {
                            $value = $group[$i][$key] ?? '';

                            if (
                                $value !== null
                                && trim((string) $value) !== ''
                            ) {
                                $plans[$key][$i] = [
                                    'rowspan' => 1,
                                    'value' => $value,
                                ];

                                $i++;
                                continue;
                            }

                            $start = $i;

                            while ($i < $rowCount) {
                                $next = $group[$i][$key] ?? '';

                                if (
                                    $next !== null
                                    && trim((string) $next) !== ''
                                ) {
                                    break;
                                }

                                $i++;
                            }

                            $plans[$key][$start] = [
                                'rowspan' => $i - $start,
                                'value' => '',
                            ];
                        }
                    }
                @endphp

                @foreach ($group as $i => $row)
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                $key = $column->key;

                                if ($key === 'property_no') {
                                    $cell = [
                                        'rowspan' => 1,
                                        'value' => $row[$key] ?? '',
                                    ];
                                } else {
                                    $cell = $plans[$key][$i] ?? null;
                                }
                            @endphp

                            @if ($cell !== null)
                                <td
                                    @if ($cell['rowspan'] > 1)
                                        rowspan="{{ $cell['rowspan'] }}"
                                    @endif
                                    style="vertical-align: middle;"
                                >
                                    @if (
                                        $column->label === 'Unit Value'
                                        && is_numeric($cell['value'])
                                        && $cell['value'] !== ''
                                    )
                                        ₱{{ number_format(
                                            (float) $cell['value'],
                                            2
                                        ) }}
                                    @else
                                        {{ $cell['value'] }}
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>

            @php $groupIndex++; @endphp
        @endforeach
    </table>

    <table>
        <tbody>
            <tr>
                <td
                    colspan="{{ max(1, $columns->count() - 1) }}"
                    class="total"
                >
                    TOTAL UNIT VALUE
                </td>
                <td class="total">
                    ₱{{ number_format($total, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
