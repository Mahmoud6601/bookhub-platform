<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_index_is_public_and_paginated(): void
    {
        Book::factory()->count(20)->create();

        $response = $this->getJson('/api/books');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total', 'last_page'])
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('total', 20);
    }

    public function test_books_search_and_category_filter(): void
    {
        $category = Category::factory()->create();
        Book::factory()->create(['title' => 'Laravel in Depth', 'category_id' => $category->id]);
        Book::factory()->create(['title' => 'Cooking Basics']);

        $this->getJson('/api/books?search=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/books?category_id=' . $category->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_books_search_escapes_like_wildcards_and_preserves_pagination_query(): void
    {
        Book::factory()->create(['title' => '100% Real PHP']);
        Book::factory()->count(16)->create(['title' => 'Regular Title']);

        $this->getJson('/api/books?search=' . urlencode('100%'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100% Real PHP');

        $paginated = $this->getJson('/api/books?search=Regular')->assertOk();
        $this->assertStringContainsString('search=Regular', (string) $paginated->json('next_page_url'));
    }

    public function test_book_show_returns_404_when_missing(): void
    {
        $this->getJson('/api/books/99999')->assertNotFound();
    }

    public function test_book_show_does_not_leak_reviewer_email_or_admin_flag(): void
    {
        $reviewer = User::factory()->create([
            'name' => 'Visible Name',
            'email' => 'secret@example.com',
            'is_admin' => true,
        ]);
        $book = Book::factory()->create();
        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'Great read.',
        ]);

        $response = $this->getJson('/api/books/' . $book->id)
            ->assertOk()
            ->assertJsonPath('reviews.0.user.name', 'Visible Name')
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('average_rating', 5);

        $userPayload = $response->json('reviews.0.user');
        $this->assertArrayNotHasKey('email', $userPayload);
        $this->assertArrayNotHasKey('is_admin', $userPayload);
    }

    public function test_book_store_requires_auth(): void
    {
        $this->postJson('/api/books', ['title' => 'X', 'author' => 'Y'])
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_create_update_or_delete_book(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $book = Book::factory()->create();

        $this->actingAs($user)->postJson('/api/books', [
            'title' => 'Unauthorized', 'author' => 'User',
        ])->assertForbidden();

        $this->actingAs($user)->putJson('/api/books/' . $book->id, [
            'price' => 0,
        ])->assertForbidden();

        $this->actingAs($user)->deleteJson('/api/books/' . $book->id)
            ->assertForbidden();
    }

    public function test_book_store_validates_required_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/api/books', ['author' => 'Y'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_book_store_allows_nullable_description_price_and_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/api/books', [
            'title' => 'Minimal Book',
            'author' => 'Author One',
            'description' => null,
            'price' => null,
            'stock' => null,
        ])->assertCreated()
            ->assertJsonPath('price', '0.00')
            ->assertJsonPath('stock', 0);
    }

    public function test_book_update_allows_keeping_own_isbn(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $book1 = Book::factory()->create(['isbn' => '9780132350884', 'price' => 25.00]);
        $book2 = Book::factory()->create(['isbn' => '9781449373320']);

        $this->actingAs($admin)->putJson('/api/books/' . $book1->id, [
            'title' => 'Clean Code Updated',
            'isbn' => '9780132350884',
        ])->assertOk()->assertJsonPath('title', 'Clean Code Updated');

        $this->actingAs($admin)->putJson('/api/books/' . $book1->id, [
            'isbn' => $book2->isbn,
        ])->assertStatus(422)->assertJsonValidationErrors('isbn');
    }

    public function test_book_crud_flow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $created = $this->actingAs($admin)->postJson('/api/books', [
            'title' => 'Clean Code', 'author' => 'Robert Martin',
            'description' => 'A handbook of agile software craftsmanship.',
            'price' => 29.99, 'stock' => 5,
        ])->assertCreated()->json();

        $this->getJson('/api/books/' . $created['id'])->assertOk()->assertJsonPath('title', 'Clean Code');

        $this->actingAs($admin)->putJson('/api/books/' . $created['id'], ['price' => 19.99])
            ->assertOk()->assertJsonPath('price', '19.99');

        $this->actingAs($admin)->deleteJson('/api/books/' . $created['id'])->assertNoContent();
        $this->getJson('/api/books/' . $created['id'])->assertNotFound();
    }

    public function test_deleting_book_soft_deletes_and_preserves_order_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $buyer = User::factory()->create();
        $book = Book::factory()->create(['price' => 15.00, 'stock' => 5]);

        $order = $this->actingAs($buyer)->postJson('/api/orders', [
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])->assertCreated()->json();

        $this->actingAs($admin)->deleteJson('/api/books/' . $book->id)->assertNoContent();

        $this->assertSoftDeleted('books', ['id' => $book->id]);
        $this->actingAs($buyer)->getJson('/api/orders/' . $order['id'])
            ->assertOk()
            ->assertJsonPath('items.0.book.id', $book->id);
        $this->assertInstanceOf(Order::class, Order::find($order['id']));
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'database' => 'ok']);
    }
}
