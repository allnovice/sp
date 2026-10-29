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
    @php
        $groups = [];

        // Identify the Property # column
        $propertyColumn = $columns->firstWhere('label', 'Property #');
        $propertyKey = $propertyColumn?->key;

        // Group components by common prefix, number and suffix
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

            $propertyNo = trim((string) ($row[$propertyKey] ?? ''));

            if (preg_match(
                '/^(.+)-([A-D])-(\d+)-([A-Z]+)$/',
                $propertyNo,
                $matches
            )) {
                $groupKey = $matches[1] . '-'
                    . $matches[3] . '-'
                    . $matches[4];
            } else {
                // Keep unrelated records separate
                $groupKey = 'single_' . count($groups);
            }

            $groups[$groupKey][] = $row;
        }
    @endphp

    @foreach ($groups as $group)
        @php
            $rowCount = count($group);
            $plans = [];

            // Build independent merge plans for every column
            foreach ($columns as $column) {
                $key = $column->key;

                // Property numbers must never merge
                if ($column->label === 'Property #') {
                    continue;
                }

                $nonEmpty = [];

                foreach ($group as $i => $row) {
                    $value = $row[$key] ?? '';

                    if (
                        $value !== null &&
                        trim((string) $value) !== ''
                    ) {
                        $nonEmpty[] = $i;
                    }
                }

                // All empty: merge the entire column vertically
                if (count($nonEmpty) === 0) {
                    $plans[$key][0] = [
                        'rowspan' => $rowCount,
                        'value' => '',
                    ];

                    continue;
                }

                // Only one value: center it across the whole group
                if (count($nonEmpty) === 1) {
                    $plans[$key][0] = [
                        'rowspan' => $rowCount,
                        'value' => $group[$nonEmpty[0]][$key],
                    ];

                    continue;
                }

                // Multiple values: preserve each value.
                // Merge consecutive empty rows.
                $i = 0;

                while ($i < $rowCount) {
                    $value = $group[$i][$key] ?? '';

                    if (
                        $value !== null &&
                        trim((string) $value) !== ''
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
                            $next !== null &&
                            trim((string) $next) !== ''
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
                        $isPropertyNo =
                            $column->label === 'Property #';

                        if ($isPropertyNo) {
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
    @endforeach

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
