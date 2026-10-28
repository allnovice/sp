<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'

interface PropertyColumn {
    id: number
    key: string
    label: string
    type: string
    is_visible: boolean
    position: number
}

interface Property {
    id: number
    [key: string]: any
}

const props = defineProps<{
    properties: Property[]
    columns: PropertyColumn[]
    errors: Record<string, string>
    existingPropertyNumbers: string[]
}>()
const uploadForm = useForm({
    file: null as File | null,
})
const showCsvPreview = ref(false)
const csvHeaders = ref<string[]>([])
const csvRows = ref<string[][]>([])
const csvError = ref('')
const propertyNoIndex = computed(() =>
    props.columns.findIndex(column => column.key === 'property_no')
)

const existingNumbers = new Set(
    props.existingPropertyNumbers.map(number => String(number).trim())
)

const isDuplicateRow = (row: string[], rowIndex: number) => {
    const number = (row[propertyNoIndex.value] ?? '').trim()

    if (!number) return false

    return (
        existingNumbers.has(number) ||
        csvRows.value.slice(0, rowIndex).some(
            previous => (previous[propertyNoIndex.value] ?? '').trim() === number
        )
    )
}

const duplicateCount = computed(() =>
    csvRows.value.filter((row, index) => isDuplicateRow(row, index)).length
)

function parseCsv(text: string): string[][] {
    const rows: string[][] = []
    let row: string[] = []
    let value = ''
    let quoted = false

    for (let i = 0; i < text.length; i++) {
        const char = text[i]

        if (char === '"' && quoted && text[i + 1] === '"') {
            value += '"'
            i++
        } else if (char === '"') {
            quoted = !quoted
        } else if (char === ',' && !quoted) {
            row.push(value)
            value = ''
        } else if ((char === '\n' || char === '\r') && !quoted) {
            if (char === '\r' && text[i + 1] === '\n') i++
            row.push(value)
            if (row.some(cell => cell.trim() !== '')) rows.push(row)
            row = []
            value = ''
        } else {
            value += char
        }
    }

    row.push(value)
    if (row.some(cell => cell.trim() !== '')) rows.push(row)

    if (rows.length) {
        rows[0][0] = rows[0][0].replace(/^\uFEFF/, '')
    }

    return rows
}

async function uploadCsv() {
    csvError.value = ``
    showCsvPreview.value = false

    const file = uploadForm.file
    if (!file) {
        csvError.value = 'Please choose a CSV file.'
        return
    }

    try {
        const text = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => resolve(String(reader.result ?? ''))
            reader.onerror = () => reject(new Error('Could not read the CSV file.'))
            reader.readAsText(file)
        })

        const rows = parseCsv(text)
        console.log('CSV parsed rows:', rows)
        if (!rows.length) {
            csvError.value = 'The CSV file is empty.'
            return
        }

        const expectedHeaders = props.columns.map(column => column.label)
        const headers = rows[0]

        if (
            headers.length !== expectedHeaders.length ||
            headers.some((header, i) => header !== expectedHeaders[i])
        ) {
            csvError.value = 'CSV headers do not match the current template.'
            return
        }

        const dataRows = rows.slice(1)


        for (let i = 0; i < dataRows.length; i++) {
            const row = dataRows[i]

            if (row.length !== headers.length) {
                csvError.value = `CSV row ${i + 2} has ${row.length} values; expected ${headers.length}.`
                return
            }

            for (let j = 0; j < props.columns.length; j++) {
                const column = props.columns[j]
                const value = (row[j] ?? '').trim()

                // All fields except Property No. may be blank.
                if (value === '') continue

                let valid = true

                if (column.type === 'number') {
                    valid = value !== '' && Number.isFinite(Number(value))
                } else if (column.type === 'date') {
                    valid = !Number.isNaN(Date.parse(value))
                } else if (column.type === 'boolean') {
                    valid = ['yes', 'no', '1', '0', 'true', 'false']
                        .includes(value.toLowerCase())
                }

                if (!valid) {
                    csvError.value =
                        `Row ${i + 2}, ${column.label}: Invalid ${column.type} value "${value}".`
                    return
                }
            }
        }


        const propertyNoIndex = props.columns.findIndex(
            column => column.key === 'property_no'
        )

        if (propertyNoIndex !== -1) {
            const seen = new Set<string>()

            for (let i = 0; i < dataRows.length; i++) {
                const number = (dataRows[i][propertyNoIndex] ?? '').trim()

                if (!number) {
                    csvError.value = `CSV row ${i + 2}: Property Number cannot be blank.`
                    return
                }

                if (seen.has(number)) {
                    csvError.value = `Duplicate Property Number in CSV: ${number}`
                    return
                }

                seen.add(number)
            }
        }

        csvHeaders.value = headers
        csvRows.value = dataRows
        showCsvPreview.value = true
    } catch {
        csvError.value = 'Could not read this CSV file. Please try saving it as CSV UTF-8.'
    }
}

function confirmCsvUpload() {
    if (!uploadForm.file) return

    uploadForm.post('/properties/import', {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset()
            showCsvPreview.value = false
            csvRows.value = []
            csvHeaders.value = []
        },
    })
}

function downloadTemplate() {
    window.location.href = '/properties/template'
}
function exportPdf() {
console.log(filteredProperties.value.map(property => property.id))
    window.location.href = `/properties/export-pdf?ids=${filteredProperties.value
        .map(property => property.id)
        .join(',')}`
}
const search = ref('')
const showColumnManager = ref(false)
const newColumnLabel = ref('')
const newColumnType = ref('text')

function addColumn() {
    if (!newColumnLabel.value.trim()) return

    router.post('/property-columns', {
        label: newColumnLabel.value,
        type: newColumnType.value,
    }, {
        onSuccess: () => {
            newColumnLabel.value = ''
            newColumnType.value = 'text'
        },
    })
}

function updateColumn(column: PropertyColumn, data: Record<string, unknown>) {
    router.patch(`/property-columns/${column.id}`, data)
}
function saveColumnOrder() {
    router.post('/property-columns/reorder', {
        columns: props.columns.map(column => column.id),
    })
}
function deleteColumn(column: PropertyColumn) {
    const confirmed = window.confirm(
        `DANGER: Deleting "${column.label}" will permanently delete this column and all its saved values from every property record. This cannot be undone. Continue?`
    )

    if (!confirmed) return

    router.delete(`/property-columns/${column.id}`)
}
function deleteProperty(property: { id: number; property_no: string }) {
    if (!confirm(`Move property ${property.property_no} to trash?`)) {
        return
    }

    router.delete(`/properties/${property.id}`, {
        preserveScroll: true,
    })
}
const editingId = ref<number | null>(null)
const editData = ref<Record<string, any>>({})

function startEditing(property: Property) {
    editingId.value = property.id

    editData.value = {
        ...property,
        ...(property.custom_fields ?? {}),
    }
}
function cancelEditing() {
    editingId.value = null
    editData.value = {}
}

function saveEditing() {
    if (editingId.value === null) return

    router.patch(`/properties/${editingId.value}`, editData.value, {
        preserveScroll: true,
        onSuccess: () => cancelEditing(),
    })
}

const selectedCategory = ref('')
const visibleColumns = computed(() =>
    props.columns.filter(column => column.is_visible)
)

const filters = ref<Array<{
    id: number
    key: string
    value: string
}>>(
    JSON.parse(localStorage.getItem('property-register-filters') || '[]')
)
const pendingFilterKey = ref('')

let nextFilterId = Math.max(0, ...filters.value.map(f => f.id)) + 1
function addFilter() {
    pendingFilterKey.value = props.columns[0]?.key ?? ''
}

function applyFilter() {
    if (!pendingFilterKey.value) return

    if (filters.value.some(filter => filter.key === pendingFilterKey.value)) {
        return
    }

    filters.value.push({
        id: nextFilterId++,
        key: pendingFilterKey.value,
        value: '',
    })

    pendingFilterKey.value = ''
}

function getPropertyValue(property: Property, key: string) {
    return property[key] ?? property.custom_fields?.[key] ?? ''
}

function getFilterOptions(key: string) {
    return [...new Set(
        props.properties
            .map(property => String(getPropertyValue(property, key) ?? '').trim())
            .filter(value => value !== '')
    )].sort()
}



const categories = computed(() =>
    [...new Set(
        props.properties.map(property => property.category).filter(Boolean)
    )].sort()
)

const filteredProperties = computed(() =>
    props.properties.filter(property => {
        const term = search.value.toLowerCase()

        const matchesSearch = !term || props.columns.some(column =>
            String(getPropertyValue(property, column.key))
                .toLowerCase()
                .includes(term)
        )

const matchesCustomFilters = filters.value.every(filter => {
    if (!filter.value) return true

    return String(getPropertyValue(property, filter.key) ?? '')
        .toLowerCase() === filter.value.toLowerCase()
})

        return matchesSearch && matchesCustomFilters
    })
)
watch(
    filters,
    (value) => {
        localStorage.setItem('property-register-filters', JSON.stringify(value))
    },
    { deep: true }
)
</script>

<template>
    <Head title="Property Register" />

    <div class="min-h-screen bg-gray-100 p-6 text-gray-900">
        <div class="mx-auto max-w-7xl rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Property Register</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage ICT equipment and other properties
                    </p>
                </div>

<div
    v-if="uploadForm.errors.file || props.errors.file"
    class="my-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700"
>
    {{ uploadForm.errors.file || props.errors.file }}
</div>

<button
    class="rounded-md border border-gray-300 px-4 py-2 hover:bg-gray-50"
    @click="showColumnManager = !showColumnManager"
>
    Manage Columns
</button>

<form
    class="flex flex-wrap items-center gap-2"
    @submit.prevent="uploadCsv"
>
    <input
        type="file"
        accept=".csv,text/csv"
        class="max-w-56 rounded-md border border-gray-300 px-3 py-2 text-sm"
        @change="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null; csvError = ''; showCsvPreview = false"
        required
    />

    <button
        type="submit"
        class="rounded-md bg-green-700 px-4 py-2 text-white hover:bg-green-800"
    >
        Preview CSV
    </button>
</form>

<p v-if="csvError || props.errors.file" class="mt-2 text-sm text-red-600">
    {{ csvError || props.errors.file }}
</p>

<div
    v-if="showCsvPreview"
    class="mt-4 w-full rounded-lg border border-gray-300 p-4"
>
    <h3 class="mb-2 font-semibold">
        Preview: {{ csvRows.length - duplicateCount }} to import,
{{ duplicateCount }} duplicates to skip
    </h3>

    <div class="max-h-80 overflow-auto">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr>
                    <th
                        v-for="(header, i) in csvHeaders"
                        :key="i"
                        class="whitespace-nowrap border p-2 text-left"
                    >
                        {{ header }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
    v-for="(row, rowIndex) in csvRows"
    :key="rowIndex"
    :class="isDuplicateRow(row, rowIndex) ? 'bg-red-100 text-red-700 line-through' : ''"
>
                    <td
                        v-for="(cell, colIndex) in row"
                        :key="colIndex"
                        class="whitespace-nowrap border p-2"
                    >
                        {{ cell }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex gap-2">
        <button
            type="button"
            @click="confirmCsvUpload"
            :disabled="uploadForm.processing || csvRows.length === 0"
            class="rounded-md bg-green-700 px-4 py-2 text-white disabled:opacity-50"
        >
            {{ uploadForm.processing ? 'Uploading...' : 'Confirm Upload' }}
        </button>

        <button
            type="button"
            @click="showCsvPreview = false"
            class="rounded-md border px-4 py-2"
        >
            Cancel
        </button>
    </div>
</div>


            </div>
<div
    v-if="showColumnManager"
    class="mb-5 rounded-lg border border-gray-200 p-4"
>
    <h2 class="mb-4 text-lg font-semibold">Manage Columns</h2>

    <form class="mb-5 flex flex-wrap gap-2" @submit.prevent="addColumn">
        <input
            v-model="newColumnLabel"
            placeholder="New column name"
            class="rounded-md border border-gray-300 px-3 py-2"
            required
        />

        <select
            v-model="newColumnType"
            class="rounded-md border border-gray-300 px-3 py-2"
        >
            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="date">Date</option>
            <option value="boolean">Yes/No</option>
        </select>

        <button
            type="submit"
            class="rounded-md bg-blue-700 px-4 py-2 text-white"
        >
            Add Column
        </button>
    </form>


    
<VueDraggable
    v-model="props.columns"
    item-key="id"
    handle=".drag-handle"
    :animation="200"
    @end="saveColumnOrder"
>
<div
    v-for="column in columns"
    :key="column.id"
    class="flex flex-wrap items-center gap-3 border-t py-3"
>
<button
    type="button"
    class="drag-handle cursor-grab touch-none px-2 text-gray-500"
    title="Hold and drag to reorder"
>
    ⠿
</button>
    <input
        :value="column.label"
        class="min-w-32 flex-1 rounded-md border border-gray-300 px-3 py-2"
        @change="updateColumn(column, {
            label: ($event.target as HTMLInputElement).value
        })"
    />

    <label class="flex items-center gap-2 text-sm">
        <input
            type="checkbox"
            :checked="column.is_visible"
            @change="updateColumn(column, {
                is_visible: ($event.target as HTMLInputElement).checked
            })"
        />
        Visible
    </label>

    <button
        v-if="column.key.startsWith('custom_')"
        type="button"
        class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
        @click="deleteColumn(column)"
    >
        Delete
    </button>
</div>
</VueDraggable>


</div>
            <div class="my-5 flex flex-wrap gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search properties..."
                    class="min-w-60 flex-1 rounded-md border border-gray-300 px-3 py-2"
                />

            </div>




<div class="my-4 rounded-lg border border-gray-200 p-4">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-semibold">Filters</h2>
        <button
            type="button"
            class="rounded-md bg-blue-700 px-3 py-2 text-sm text-white"
            @click="addFilter"
        >
            + Add Filter
        </button>
    </div>

    <div v-if="pendingFilterKey === ''" class="mb-3">
        <p class="text-sm text-gray-500">
            Add a filter, then choose a column.
        </p>
    </div>

    <div v-if="pendingFilterKey !== ''" class="mb-3 flex flex-wrap items-center gap-2">
        <select
            v-model="pendingFilterKey"
            class="flex-1 rounded-md border border-gray-300 px-3 py-2"
        >
            <option
                v-for="column in props.columns"
                :key="column.id"
                :value="column.key"
            >
                {{ column.label }}
            </option>
        </select>

        <button
            type="button"
            class="rounded-md bg-green-700 px-3 py-2 text-white"
            @click="applyFilter"
        >
            ✓ Apply
        </button>

        <button
            type="button"
            class="rounded-md border px-3 py-2"
            @click="pendingFilterKey = ''"
        >
            Cancel
        </button>
    </div>

    <div
        v-for="filter in filters"
        :key="filter.id"
        class="mb-3 flex flex-wrap items-center gap-2"
    >
        <label class="font-medium">
            {{ props.columns.find(column => column.key === filter.key)?.label }}
        </label>

        <select
            v-model="filter.value"
            class="flex-1 rounded-md border border-gray-300 px-3 py-2"
        >
            <option value="">All values</option>
            <option
                v-for="option in getFilterOptions(filter.key)"
                :key="option"
                :value="option"
            >
                {{ option }}
            </option>
        </select>

        <button
            type="button"
            class="rounded-md border border-red-300 px-3 py-2 text-red-700"
            @click="filters = filters.filter(f => f.id !== filter.id)"
        >
            −
        </button>
    </div>
</div>



<button
    type="button"
    @click="exportPdf"
    class="rounded-md bg-red-700 px-3 py-2 text-sm text-white"
>
    Export PDF
</button>
<button
    type="button"
    @click="downloadTemplate"
    class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50"
>
    Download Template
</button>
            <p class="mb-3 text-sm text-gray-500">
                {{ filteredProperties.length }} items
            </p>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                v-for="column in visibleColumns"
                                :key="column.id"
                                class="border-b p-3"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="property in filteredProperties"
                            :key="property.id"
                            class="hover:bg-gray-50"
                        >
                            <td
                                v-for="column in visibleColumns"
                                :key="column.id"
                                class="border-b p-3"
                            >
<template v-if="editingId === property.id">
    <select
        v-if="column.type === 'boolean'"
        v-model="editData[column.key]"
        class="w-full rounded border px-2 py-1"
    >
        <option value="">Select...</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
    </select>

    <input
        v-else
        v-model="editData[column.key]"
        :type="column.type === 'number' ? 'number' : column.type === 'date' ? 'date' : 'text'"
        :step="column.type === 'number' ? 'any' : undefined"
        class="w-full min-w-24 rounded border border-blue-400 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500"
    />
</template>

<template v-else>
<span>
    
{{
    column.label === 'Unit Value'
        ? (
            property[column.key] ?? property.custom_fields?.[column.key]
        ) !== null &&
          (property[column.key] ?? property.custom_fields?.[column.key]) !== undefined &&
          (property[column.key] ?? property.custom_fields?.[column.key]) !== ''
            ? '₱' + Number(
                property[column.key] ?? property.custom_fields?.[column.key]
              ).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              })
            : ''
        : (property[column.key] ?? property.custom_fields?.[column.key] ?? '')
}}

</span>
</template>

<template v-if="column.key === 'property_no'">
    <button
        v-if="editingId !== property.id"
        type="button"
        class="ml-2 text-blue-600 hover:text-blue-800"
        title="Edit property"
        @click="startEditing(property)"
    >
        ✏️
    </button>

    <button
        v-if="editingId === property.id"
        type="button"
        class="ml-2 text-green-600 hover:text-green-800"
        title="Save changes"
        @click="saveEditing"
    >
        ✓
    </button>

    <button
        v-if="editingId === property.id"
        type="button"
        class="ml-1 text-gray-500 hover:text-gray-700"
        title="Cancel editing"
        @click="cancelEditing"
    >
        ✕
    </button>

    <button
        v-if="editingId !== property.id"
        type="button"
        class="ml-1 text-red-600 hover:text-red-800"
        title="Move property to trash"
        @click="deleteProperty(property)"
    >
        🗑
    </button>
</template>
                            </td>
                        </tr>

                        <tr v-if="filteredProperties.length === 0">
                            <td
                                :colspan="Math.max(visibleColumns.length, 1)"
                                class="p-8 text-center text-gray-500"
                            >
                                No properties found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
