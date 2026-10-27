<?php

namespace Database\Seeders;

use App\Models\PropertyColumn;
use Illuminate\Database\Seeder;

class PropertyColumnSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            ['key' => 'property_no', 'label' => 'Property No.', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['key' => 'serial_no', 'label' => 'Serial No.', 'type' => 'text'],
            ['key' => 'office', 'label' => 'Office', 'type' => 'text'],
            ['key' => 'assigned_to', 'label' => 'Assigned To', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
        ];

        foreach ($columns as $position => $column) {
            PropertyColumn::firstOrCreate(
                ['key' => $column['key']],
                [...$column, 'position' => $position]
            );
        }
    }
}
