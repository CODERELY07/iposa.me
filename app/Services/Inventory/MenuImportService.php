<?php

namespace App\Services\Inventory;

use App\Enums\ItemKind;
use App\Models\Business;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * Import the Excel pricing matrix saved as CSV.
 *
 * Columns (header row required, any order): name, category, size, cost, price
 * One row per size. Rows with the same name become one menu item with several sizes.
 * Existing menu items with the same name get their sizes updated or added.
 */
class MenuImportService
{
    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(Business $business, string $path): array
    {
        $rows = $this->readRows($path);
        $errors = $rows['errors'];

        if ($rows['items'] === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors ?: ['No menu rows found. The first row must be: name, category, size, cost, price']];
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($business, $rows, &$created, &$updated): void {
            foreach ($rows['items'] as $name => $row) {
                $category = $row['category'] !== ''
                    ? Category::withoutGlobalScopes()->firstOrCreate(
                        ['business_id' => $business->id, 'name' => $row['category']],
                        ['color' => Category::COLORS[Category::withoutGlobalScopes()->where('business_id', $business->id)->count() % count(Category::COLORS)]],
                    )
                    : null;

                $item = Item::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->where('kind', ItemKind::Menu)
                    ->whereNull('archived_at')
                    ->where('name', $name)
                    ->first();

                if ($item === null) {
                    $item = Item::withoutGlobalScopes()->create([
                        'business_id' => $business->id,
                        'kind' => ItemKind::Menu,
                        'name' => $name,
                        'category_id' => $category?->id,
                    ]);
                    $created++;
                } else {
                    $item->update(['category_id' => $category?->id ?? $item->category_id]);
                    $updated++;
                }

                foreach ($row['variants'] as $sort => $variant) {
                    $item->variants()->updateOrCreate(
                        ['label' => $variant['label']],
                        ['cost' => $variant['cost'], 'price' => $variant['price'], 'sort' => $sort],
                    );
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * @return array{items: array<string, array{category: string, variants: list<array{label: string, cost: float, price: float}>}>, errors: list<string>}
     */
    private function readRows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $items = [];
        $errors = [];

        foreach ($file as $lineNumber => $columns) {
            if (! is_array($columns) || $columns === [null]) {
                continue;
            }

            $columns = array_map(fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), $columns);

            if ($header === null) {
                $header = array_map('strtolower', $columns);

                foreach (['name', 'price'] as $required) {
                    if (! in_array($required, $header, true)) {
                        return ['items' => [], 'errors' => ["The header row needs a “{$required}” column."]];
                    }
                }

                continue;
            }

            $row = array_combine($header, array_pad(array_slice($columns, 0, count($header)), count($header), ''));
            $line = $lineNumber + 1;
            $name = $row['name'] ?? '';
            $price = $this->money($row['price'] ?? '');
            $cost = $this->money($row['cost'] ?? '0') ?? 0.0;

            if ($name === '' || $price === null) {
                $errors[] = "Row {$line}: needs a name and a price.";

                continue;
            }

            $items[$name] ??= ['category' => $row['category'] ?? '', 'variants' => []];
            $items[$name]['variants'][] = [
                'label' => ($row['size'] ?? '') !== '' ? $row['size'] : 'Regular',
                'cost' => $cost,
                'price' => $price,
            ];
        }

        return ['items' => $items, 'errors' => $errors];
    }

    /**
     * "₱1,234.50" → 1234.5
     */
    private function money(string $value): ?float
    {
        $clean = str_replace(['₱', 'PHP', ',', ' '], '', $value);

        return is_numeric($clean) && (float) $clean >= 0 ? round((float) $clean, 2) : null;
    }
}
