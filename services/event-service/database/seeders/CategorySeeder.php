<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Концерт', 'slug' => 'concert'],
            ['name' => 'Театр', 'slug' => 'theater'],
            ['name' => 'Стендап', 'slug' => 'standup'],
            ['name' => 'Выставка', 'slug' => 'exhibition'],
            ['name' => 'Фестиваль', 'slug' => 'festival'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']]
            );
        }
    }
}
