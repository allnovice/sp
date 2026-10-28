<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyColumn;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PropertyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Properties/Index', [
            'properties' => Property::latest()->get(),
            'columns' => PropertyColumn::orderBy('position')
                ->get(),
        ]);
    }
public function template()
{
    $columns = PropertyColumn::orderBy('position')->get();

    $headers = $columns->pluck('label')->toArray();

    $callback = function () use ($headers) {
        $file = fopen('php://output', 'w');

        fputcsv($file, $headers);

        fclose($file);
    };

    return response()->streamDownload($callback, 'property-template.csv', [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}
public function import(Request $request)
{
    $request->validate([
        'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
    ]);

    $columns = PropertyColumn::orderBy('position')->get();

    $expectedHeaders = $columns->pluck('label')->toArray();

    $file = fopen($request->file('file')->getRealPath(), 'r');

    $headers = fgetcsv($file);

    if ($headers !== $expectedHeaders) {
        fclose($file);

        return back()->withErrors([
            'file' => 'Your CSV template is outdated or its headers were changed. Download the latest template and try again.',
        ]);
    }

    $rows = [];
    $rowNumber = 1;

    while (($values = fgetcsv($file)) !== false) {
        $rowNumber++;

        if (count($values) !== count($columns)) {
            fclose($file);

            return back()->withErrors([
                'file' => "Row {$rowNumber} has a different number of values than the template.",
            ]);
        }

        $row = array_combine(
            $columns->pluck('key')->toArray(),
            $values
        );

        $rows[] = $row;
    }

    fclose($file);

$standardFields = array_diff(
    \Illuminate\Support\Facades\Schema::getColumnListing('properties'),
    ['id', 'custom_fields', 'created_at', 'updated_at']
);

DB::transaction(function () use ($rows, $standardFields) {
$propertyNumbers = [];

foreach ($rows as $index => $row) {
    $number = trim((string) ($row['property_no'] ?? ''));

    if ($number === '') {
        return back()->withErrors([
            'file' => 'CSV row ' . ($index + 2) . ': Property Number cannot be blank.',
        ]);
    }

    if (in_array($number, $propertyNumbers, true)) {
        return back()->withErrors([
            'file' => "Duplicate Property Number in CSV: {$number}",
        ]);
    }

    if (Property::where('property_no', $number)->exists()) {
        return back()->withErrors([
            'file' => "Property Number already exists: {$number}",
        ]);
    }

    $propertyNumbers[] = $number;
}
    foreach ($rows as $row) {
        $propertyData = [];
        $customFields = [];

        foreach ($row as $key => $value) {
            $value = $value === '' ? null : $value;

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

    return back()->with('success', count($rows) . ' properties imported successfully.');
}
public function destroy(Property $property)
{
    $property->delete();

    return redirect()->back()->with('success', 'Property moved to trash.');
}

public function update(Request $request, Property $property)
{
    $data = $request->validate([
        'property_no' => [
            'required',
            'string',
            'max:255',
            \Illuminate\Validation\Rule::unique('properties', 'property_no')
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

    if (!$request->has($key)) {
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
public function exportPdf()
{
    $properties = Property::latest()->get();

    $columns = PropertyColumn::where('is_visible', true)
        ->orderBy('position')
        ->get();

$unitValueColumn = PropertyColumn::where('label', 'Unit Value')
    ->where('key', 'like', 'custom_%')
    ->first();

$total = $properties->sum(function ($property) use ($unitValueColumn) {
    if (!$unitValueColumn) {
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
