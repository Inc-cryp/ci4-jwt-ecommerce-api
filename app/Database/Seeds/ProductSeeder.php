<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Laptop ASUS ROG',
                'slug' => 'laptop-asus-rog',
                'description' => 'Gaming laptop dengan spesifikasi tinggi',
                'price' => 15000000,
                'stock' => 10,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'iPhone 15 Pro Max',
                'slug' => 'iphone-15-pro-max',
                'description' => 'Smartphone flagship terbaru dari Apple',
                'price' => 20000000,
                'stock' => 15,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'description' => 'Smartphone flagship terbaru dari Samsung',
                'price' => 18000000,
                'stock' => 20,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'slug' => 'sony-wh-1000xm5',
                'description' => 'Headphone wireless dengan noise cancelling terbaik',
                'price' => 5000000,
                'stock' => 30,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Apple Watch Series 9',
                'slug' => 'apple-watch-series-9',
                'description' => 'Smartwatch terbaru dari Apple',
                'price' => 7000000,
                'stock' => 25,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'MacBook Pro M3',
                'slug' => 'macbook-pro-m3',
                'description' => 'Laptop profesional dengan chip M3',
                'price' => 25000000,
                'stock' => 8,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'iPad Pro 12.9 inch',
                'slug' => 'ipad-pro-12-9-inch',
                'description' => 'Tablet profesional dengan layar besar',
                'price' => 15000000,
                'stock' => 12,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Logitech MX Master 3S',
                'slug' => 'logitech-mx-master-3s',
                'description' => 'Mouse wireless untuk produktivitas',
                'price' => 1500000,
                'stock' => 50,
                'category' => 'accessories',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Mechanical Keyboard RGB',
                'slug' => 'mechanical-keyboard-rgb',
                'description' => 'Keyboard mechanical dengan RGB lighting',
                'price' => 2000000,
                'stock' => 35,
                'category' => 'accessories',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Samsung 4K Monitor 27 inch',
                'slug' => 'samsung-4k-monitor-27-inch',
                'description' => 'Monitor 4K untuk profesional',
                'price' => 5000000,
                'stock' => 15,
                'category' => 'electronics',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Insert data
        $this->db->table('products')->insertBatch($data);
    }
}