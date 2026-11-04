<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyColumnController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['text', 'number', 'date', 'boolean'])],
        ]);

        $user = $request->user();
        $key = 'custom_' . str()->slug($data['label'], '_');

        if ($user->propertyColumns()->where('key', $key)->exists()) {
            return back()->withErrors([
                'label' => 'A column with this name already exists.',
            ]);
        }

        $user->propertyColumns()->create([
            'key' => $key,
            'label' => $data['label'],
            'type' => $data['type'],
            'position' => ($user->propertyColumns()->max('position') ?? -1) + 1,
        ]);

        return back();
    }

public function update(Request $request, PropertyColumn $propertyColumn): RedirectResponse
{
    abort_unless(
        (int) $propertyColumn->user_id === (int) $request->user()->id,
        404
    );

    if (in_array($propertyColumn->key, ['created_at', 'updated_at'], true)) {
        $data = $request->validate([
            'is_visible' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);
    } else {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'is_visible' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    $propertyColumn->update($data);

    return back();
}

    public function destroy(Request $request, PropertyColumn $propertyColumn): RedirectResponse
    {
        abort_unless(
            $propertyColumn->user_id === $request->user()->id,
            404
        );

        if (! str_starts_with($propertyColumn->key, 'custom_')) {
            return back()->withErrors([
                'column' => 'Default columns cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($request, $propertyColumn) {
            $request->user()->properties()->each(function (Property $property) use ($propertyColumn) {
                $customFields = is_array($property->custom_fields)
                    ? $property->custom_fields
                    : [];

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
            'columns.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('property_columns', 'id')
                    ->where('user_id', $request->user()->id),
            ],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['columns'] as $position => $id) {
                $request->user()->propertyColumns()
                    ->where('id', $id)
                    ->update(['position' => $position]);
            }
        });

        return back();
    }
}

