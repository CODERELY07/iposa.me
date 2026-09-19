<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Inline "add category" from the item editor (JSON).
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'name' => $request->validated('name'),
            'color' => $request->validated('color') ?? Category::COLORS[Category::query()->count() % count(Category::COLORS)],
            'sort' => (int) Category::query()->max('sort') + 1,
        ]);

        return response()->json(['category' => $category->only(['id', 'name', 'color'])], 201);
    }
}
