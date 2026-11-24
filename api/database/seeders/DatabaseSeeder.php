<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database idempotently.
     */
    public function run(): void
    {
        $categories = [
            ['slug' => 'software-engineering', 'name' => 'Software Engineering'],
            ['slug' => 'system-architecture', 'name' => 'System Architecture'],
            ['slug' => 'devops-cloud', 'name' => 'DevOps & Cloud'],
            ['slug' => 'science-fiction', 'name' => 'Science Fiction'],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['slug']] = Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        $authors = [
            ['name' => 'Robert C. Martin', 'bio' => 'Software engineer and author focused on clean code and agile craftsmanship.'],
            ['name' => 'Martin Kleppmann', 'bio' => 'Researcher and author of Designing Data-Intensive Applications.'],
            ['name' => 'Gene Kim', 'bio' => 'DevOps researcher and co-author of The Phoenix Project.'],
            ['name' => 'Frank Herbert', 'bio' => 'American science-fiction author known for Dune.'],
        ];

        $authorMap = [];
        foreach ($authors as $author) {
            $authorMap[$author['name']] = Author::firstOrCreate(
                ['name' => $author['name']],
                ['bio' => $author['bio']]
            );
        }

        $books = [
            [
                'isbn' => '9780132350884',
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'description' => 'A handbook of agile software craftsmanship with practical patterns for readable, maintainable code.',
                'price' => 34.99,
                'stock' => 12,
                'category_id' => $categoryMap['software-engineering']->id,
                'author_id' => $authorMap['Robert C. Martin']->id,
            ],
            [
                'isbn' => '9781449373320',
                'title' => 'Designing Data-Intensive Applications',
                'author' => 'Martin Kleppmann',
                'description' => 'The big ideas behind reliable, scalable, and maintainable distributed systems.',
                'price' => 44.50,
                'stock' => 8,
                'category_id' => $categoryMap['system-architecture']->id,
                'author_id' => $authorMap['Martin Kleppmann']->id,
            ],
            [
                'isbn' => '9781942788294',
                'title' => 'The Phoenix Project',
                'author' => 'Gene Kim',
                'description' => 'A novel about IT, DevOps, and helping your business win through flow and feedback.',
                'price' => 27.00,
                'stock' => 10,
                'category_id' => $categoryMap['devops-cloud']->id,
                'author_id' => $authorMap['Gene Kim']->id,
            ],
            [
                'isbn' => '9780441172719',
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Set on the desert planet Arrakis, Dune tells the story of Paul Atreides and the struggle for spice.',
                'price' => 18.99,
                'stock' => 15,
                'category_id' => $categoryMap['science-fiction']->id,
                'author_id' => $authorMap['Frank Herbert']->id,
            ],
        ];

        foreach ($books as $book) {
            Book::firstOrCreate(['isbn' => $book['isbn']], $book);
        }

        // Seed a local-only dev admin when running locally, or use ADMIN_EMAIL / ADMIN_PASSWORD if explicitly exported.
        $adminEmail = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: null;
        $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: null;

        if (! $adminEmail && app()->environment('local')) {
            $adminEmail = 'admin@bookhub.local';
            $adminPassword = 'password123';
        }

        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrNew(['email' => $adminEmail]);
            if (! $admin->exists) {
                $admin->name = 'BookHub Admin';
                $admin->password = Hash::make($adminPassword);
            }
            $admin->is_admin = true;
            $admin->save();
        }
    }
}
