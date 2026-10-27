<?php

namespace App\Http\Controllers;

use App\Models\PropertyColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PropertyColumnController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['text', 'number', 'date', 'boolean'])],
        ]);

        $key = 'custom_' . str()->slug($data['label'], '_');

        if (PropertyColumn::where('key', $key)->exists()) {
            return back()->withErrors([
                'label' => 'A column with this name already exists.',
            ]);
        }

        PropertyColumn::create([
            'key' => $key,
            'label' => $data['label'],
            'type' => $data['type'],
            'position' => (PropertyColumn::max('position') ?? -1) + 1,
        ]);

        return back();
    }

    public function update(Request $request, PropertyColumn $propertyColumn): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'is_visible' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $propertyColumn->update($data);

        return back();
    }
public function destroy(PropertyColumn $propertyColumn): RedirectResponse
{
    if (! str_starts_with($propertyColumn->key, 'custom_')) {
        return back()->withErrors([
            'column' => 'Default columns cannot be deleted.',
        ]);
    }

    DB::transaction(function () use ($propertyColumn) {
        \App\Models\Property::query()->each(function ($property) use ($propertyColumn) {
            $customFields = $property->custom_fields ?? [];
            unset($customFields[$propertyColumn->key]);
            $property->custom_fields = $customFields;
            $property->save();
        });

        $propertyColumn->delete();
    });

    return back();
}
public function reorder(Request $request): RedirectResponse
{
    $data = $request->validate([
        'columns' => ['required', 'array'],
        'columns.*' => ['required', 'integer', 'distinct', 'exists:property_columns,id'],
    ]);

    DB::transaction(function () use ($data) {
        foreach ($data['columns'] as $position => $id) {
            PropertyColumn::where('id', $id)
                ->update(['position' => $position]);
        }
    });

    return back();
}
}
