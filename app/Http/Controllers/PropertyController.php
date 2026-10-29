<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyColumn;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Properties/Index', [
            'properties' => Property::latest()->get(),
            'columns' => PropertyColumn::orderBy('position')->get(),
            'existingPropertyNumbers' => Property::pluck('property_no'),
        ]);
    }

    public function template(): StreamedResponse
    {
        $columns = PropertyColumn::orderBy('position')->get();

        $headers = $columns->pluck('label')->toArray();

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }
            fputcsv($file, $headers);

            fclose($file);
        };

        return response()->streamDownload($callback, 'property-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $columns = PropertyColumn::orderBy('position')->get();
        $expectedHeaders = $columns->pluck('label')->toArray();
        $keys = $columns->pluck('key')->toArray();

        $file = fopen($request->file('file')->getRealPath(), 'r');
        if ($file === false) {
            return back()->withErrors([
                'file' => 'Unable to read the uploaded CSV file.',
            ]);
        }
        $headers = fgetcsv($file);

        if ($headers !== $expectedHeaders) {
            fclose($file);

            return back()->withErrors([
                'file' => 'Your CSV template is outdated or its headers were changed. Download the latest template and try again.',
            ]);
        }

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        $propertyNumbers = [];
        $standardFields = array_diff(
            Schema::getColumnListing('properties'),
            ['id', 'custom_fields', 'created_at', 'updated_at']
        );

        while (($values = fgetcsv($file)) !== false) {
            $rowNumber++;

            if (count($values) !== count($columns)) {
                $errors[] = "Row {$rowNumber}: Incorrect number of columns.";

                continue;
            }

            $row = array_combine($keys, $values);

            foreach ($row as $key => $value) {
                $value = trim((string) $value);

                if ($value === '') {
                    $row[$key] = null;

                    continue;
                }

                $column = $columns->firstWhere('key', $key);
                $type = $column->type ?? 'text';

                $valid = match ($type) {
                    'number' => is_numeric($value),
                    'date' => strtotime($value) !== false,
                    'boolean' => in_array(
                        strtolower($value),
                        ['yes', 'no', '1', '0', 'true', 'false'],
                        true
                    ),
                    default => true,
                };

                if (! $valid) {
                    $errors[] = "Row {$rowNumber}, {$column->label}: Invalid {$type} value.";
                }

                $row[$key] = $value;
            }

            $number = trim((string) ($row['property_no'] ?? ''));

            if ($number === '') {
                $errors[] = "Row {$rowNumber}: Property No. is required.";

                continue;
            }

            if (
                in_array($number, $propertyNumbers, true) ||
                Property::where('property_no', $number)->exists()
            ) {
                // Skip duplicate rows entirely.
                continue;
            }

            $propertyNumbers[] = $number;
            $rows[] = $row;

        }

        fclose($file);

        if ($errors) {
            return back()->withErrors([
                'file' => implode("\n", array_slice($errors, 0, 20))
                    .(count($errors) > 20 ? "\nMore errors omitted." : ''),
            ]);
        }

        DB::transaction(function () use ($rows, $standardFields) {
            foreach ($rows as $row) {
                $propertyData = [];
                $customFields = [];

                foreach ($row as $key => $value) {
                    if (in_array($key, $standardFields, true)) {
                        $propertyData[$key] = $value;
                    } elseif (str_starts_with($key, 'custom_')) {
                        $customFields[$key] = $value;
                    }
                }

                if (empty($propertyData['status'])) {
                    $propertyData['status'] = 'Active';
                }

                $propertyData['custom_fields'] = $customFields;
                Property::create($propertyData);
            }
        });

        return back()->with('success', count($rows).' properties imported successfully.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()->back()->with('success', 'Property moved to trash.');
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'property_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('properties', 'property_no')
                    ->ignore($property->id),
            ],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'serial_no' => ['nullable', 'string'],
            'office' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        if (empty($data['status'])) {
            $data['status'] = 'Active';
        }

        // Validate custom fields according to their configured column types.
        $customRules = [];

        $customColumns = PropertyColumn::where('key', 'like', 'custom_%')->get();

        foreach ($customColumns as $column) {
            $key = $column->key;

            if (! $request->has($key)) {
                continue;
            }

            $rules = match ($column->type) {
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date'],
                'boolean' => ['nullable', 'in:Yes,No,1,0,true,false'],
                default => ['nullable', 'string'],
            };

            $customRules[$key] = $rules;
        }

        $request->validate($customRules);

        $customFields = $property->custom_fields ?? [];

        // Flatten any accidental nesting from earlier updates.
        while (isset($customFields['custom_fields'])
            && is_array($customFields['custom_fields'])) {
            $nested = $customFields['custom_fields'];
            unset($customFields['custom_fields']);
            $customFields = array_merge($nested, $customFields);
        }

        // Save submitted custom column values.
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'custom_')) {
                $customFields[$key] = $value;
            }
        }

        $data['custom_fields'] = $customFields;

        $property->update($data);

        return back()->with('success', 'Property updated successfully.');
    }

    public function exportPdf(): HttpResponse
    {
        $ids = array_filter(explode(',', request()->query('ids', '')));

        $properties = Property::whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($property) => array_search($property->id, $ids))
            ->values();

        $columns = PropertyColumn::where('is_visible', true)
            ->orderBy('position')
            ->get();

        $unitValueColumn = PropertyColumn::where('label', 'Unit Value')
            ->where('key', 'like', 'custom_%')
            ->first();

        $total = $properties->sum(function ($property) use ($unitValueColumn) {
            if (! $unitValueColumn) {
                return 0;
            }

            $value = $property->custom_fields[$unitValueColumn->key] ?? 0;

            return is_numeric($value) ? (float) $value : 0;
        });

        $pdf = Pdf::loadView('properties.pdf', [
            'properties' => $properties,
            'columns' => $columns,
            'total' => $total,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('property-register.pdf');
    }
}
