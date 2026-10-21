<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Gaji Pokok', 'type' => 'income', 'color' => '#10B981', 'icon' => 'briefcase'],
            ['name' => 'Bonus & Tunjangan', 'type' => 'income', 'color' => '#059669', 'icon' => 'gift'],
            ['name' => 'Freelance & Sampingan', 'type' => 'income', 'color' => '#3B82F6', 'icon' => 'laptop'],
            ['name' => 'Investasi & Bunga', 'type' => 'income', 'color' => '#6366F1', 'icon' => 'trending-up'],
            ['name' => 'Pemasukan Lain-lain', 'type' => 'income', 'color' => '#8B5CF6', 'icon' => 'plus-circle'],
            ['name' => 'Makanan & Minuman', 'type' => 'expense', 'color' => '#EF4444', 'icon' => 'utensils'],
            ['name' => 'Transportasi', 'type' => 'expense', 'color' => '#F97316', 'icon' => 'car'],
            ['name' => 'Belanja Kebutuhan', 'type' => 'expense', 'color' => '#EC4899', 'icon' => 'shopping-bag'],
            ['name' => 'Tagihan & Utilitas', 'type' => 'expense', 'color' => '#F59E0B', 'icon' => 'zap'],
            ['name' => 'Tempat Tinggal', 'type' => 'expense', 'color' => '#84CC16', 'icon' => 'home'],
            ['name' => 'Hiburan & Liburan', 'type' => 'expense', 'color' => '#06B6D4', 'icon' => 'film'],
            ['name' => 'Kesehatan', 'type' => 'expense', 'color' => '#14B8A8', 'icon' => 'heart-pulse'],
            ['name' => 'Pendidikan', 'type' => 'expense', 'color' => '#3B82F6', 'icon' => 'book-open'],
            ['name' => 'Donasi & Sedekah', 'type' => 'expense', 'color' => '#A855F7', 'icon' => 'heart'],
            ['name' => 'Pengeluaran Lain-lain', 'type' => 'expense', 'color' => '#6B7280', 'icon' => 'more-horizontal'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'type' => $category['type'], 'user_id' => null],
                ['color' => $category['color'], 'icon' => $category['icon']],
            );
        }
    }
}
