<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pakaian & Fashion', 'icon' => 'bi-bag', 'color' => 'primary'],
            ['name' => 'Elektronik', 'icon' => 'bi-laptop', 'color' => 'info'],
            ['name' => 'Makanan & Minuman', 'icon' => 'bi-cup-straw', 'color' => 'warning'],
            ['name' => 'Peralatan Rumah', 'icon' => 'bi-house', 'color' => 'success'],
            ['name' => 'Kendaraan', 'icon' => 'bi-car-front', 'color' => 'danger'],
            ['name' => 'Properti', 'icon' => 'bi-building', 'color' => 'secondary'],
            ['name' => 'Jasa & Layanan', 'icon' => 'bi-tools', 'color' => 'primary'],
            ['name' => 'Kesehatan & Kecantikan', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
            ['name' => 'Hobi & Olahraga', 'icon' => 'bi-trophy', 'color' => 'warning'],
            ['name' => 'Hewan Peliharaan', 'icon' => 'bi-emoji-smile', 'color' => 'success'],
            ['name' => 'Buku & Pendidikan', 'icon' => 'bi-book', 'color' => 'info'],
            ['name' => 'Lainnya', 'icon' => 'bi-three-dots', 'color' => 'secondary'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                array_merge($cat, ['slug' => Str::slug($cat['name'])])
            );
        }
    }
}
