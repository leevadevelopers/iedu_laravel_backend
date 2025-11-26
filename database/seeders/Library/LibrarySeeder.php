<?php

namespace Database\Seeders\Library;

use App\Models\V1\Library\Collection;
use App\Models\V1\Library\Author;
use App\Models\V1\Library\Publisher;
use App\Models\V1\Library\Book;
use App\Models\V1\Library\BookCopy;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1; // Change based on your tenant

        // Create Collections
        $collections = [
            ['name' => 'Fiction', 'description' => 'Fictional books collection', 'tenant_id' => $tenantId],
            ['name' => 'Science', 'description' => 'Science and technology books', 'tenant_id' => $tenantId],
            ['name' => 'History', 'description' => 'Historical books', 'tenant_id' => $tenantId],
        ];

        foreach ($collections as $collection) {
            Collection::create($collection);
        }

        // Create Authors
        $authors = [
            ['name' => 'J.K. Rowling', 'country' => 'UK'],
            ['name' => 'George Orwell', 'country' => 'UK'],
            ['name' => 'Stephen Hawking', 'country' => 'UK'],
            ['name' => 'Yuval Noah Harari', 'country' => 'Israel'],
        ];

        foreach ($authors as $author) {
            Author::create($author);
        }

        // Create Publishers
        $publishers = [
            ['name' => 'Bloomsbury Publishing', 'country' => 'UK'],
            ['name' => 'Penguin Books', 'country' => 'UK'],
            ['name' => 'Bantam Books', 'country' => 'USA'],
        ];

        foreach ($publishers as $publisher) {
            Publisher::create($publisher);
        }

        // Create Books
        $books = [
            [
                'tenant_id' => $tenantId,
                'collection_id' => 1,
                'publisher_id' => 1,
                'title' => 'Harry Potter and the Philosopher\'s Stone',
                'isbn' => '9780747532699',
                'language' => 'en',
                'summary' => 'The first book in the Harry Potter series.',
                'visibility' => 'tenant',
                'published_at' => '1997-06-26',
                'pages' => 223,
            ],
            [
                'tenant_id' => $tenantId,
                'collection_id' => 1,
                'publisher_id' => 2,
                'title' => '1984',
                'isbn' => '9780451524935',
                'language' => 'en',
                'summary' => 'A dystopian social science fiction novel.',
                'visibility' => 'tenant',
                'published_at' => '1949-06-08',
                'pages' => 328,
            ],
            [
                'tenant_id' => null, // Public book
                'collection_id' => 2,
                'publisher_id' => 3,
                'title' => 'A Brief History of Time',
                'isbn' => '9780553380163',
                'language' => 'en',
                'summary' => 'A landmark volume in science writing.',
                'visibility' => 'public',
                'published_at' => '1988-04-01',
                'pages' => 256,
            ],
        ];

        foreach ($books as $bookData) {
            $book = Book::create($bookData);

            // Attach authors
            if ($book->title === 'Harry Potter and the Philosopher\'s Stone') {
                $book->authors()->attach(1);
            } elseif ($book->title === '1984') {
                $book->authors()->attach(2);
            } elseif ($book->title === 'A Brief History of Time') {
                $book->authors()->attach(3);
            }

            // Create copies for each book
            for ($i = 1; $i <= 3; $i++) {
                BookCopy::create([
                    'book_id' => $book->id,
                    'barcode' => 'BC' . str_pad($book->id, 4, '0', STR_PAD_LEFT) . '-' . $i,
                    'location' => 'Shelf ' . chr(64 + $book->id) . '-' . $i,
                    'status' => 'available',
                ]);
            }
        }

        $this->command->info('Library data seeded successfully!');
    }
}
