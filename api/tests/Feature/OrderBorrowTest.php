<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBorrowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_decrements_stock_and_totals(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 10.00, 'stock' => 5]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['book_id' => $book->id, 'qty' => 2]],
        ])->assertCreated();

        $response->assertJsonPath('status', 'pending')->assertJsonPath('total', '20.00');
        $this->assertEquals(3, $book->fresh()->stock);
    }

    public function test_order_aggregates_duplicate_book_items_and_rolls_back_on_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $bookA = Book::factory()->create(['price' => 12.50, 'stock' => 5]);
        $bookB = Book::factory()->create(['price' => 20.00, 'stock' => 1]);

        $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['book_id' => $bookA->id, 'qty' => 2],
                ['book_id' => $bookB->id, 'qty' => 2],
            ],
        ])->assertStatus(422);

        $this->assertEquals(5, $bookA->fresh()->stock);
        $this->assertEquals(1, $bookB->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['book_id' => $bookA->id, 'qty' => 1],
                ['book_id' => $bookA->id, 'qty' => 2],
            ],
        ])->assertCreated()->assertJsonPath('total', '37.50');

        $this->assertEquals(2, $bookA->fresh()->stock);
    }

    public function test_order_requires_auth_and_valid_items(): void
    {
        $this->postJson('/api/orders', ['items' => []])->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/orders', ['items' => []])
            ->assertStatus(422);
    }

    public function test_order_cannot_be_viewed_by_other_non_admin_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $book = Book::factory()->create(['stock' => 5]);

        $order = $this->actingAs($owner)->postJson('/api/orders', [
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])->assertCreated()->json();

        $this->actingAs($other)->getJson('/api/orders/' . $order['id'])->assertForbidden();
        $this->actingAs($admin)->getJson('/api/orders/' . $order['id'])->assertOk();
    }

    public function test_order_status_transitions_and_cancel_restores_stock(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $book = Book::factory()->create(['stock' => 5]);

        $order = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['book_id' => $book->id, 'qty' => 2]],
        ])->assertCreated()->json();
        $this->assertEquals(3, $book->fresh()->stock);

        $this->actingAs($user)->patchJson('/api/orders/' . $order['id'] . '/status', [
            'status' => 'approved',
        ])->assertForbidden();

        // Cannot jump directly from pending to delivered
        $this->actingAs($admin)->patchJson('/api/orders/' . $order['id'] . '/status', [
            'status' => 'delivered',
        ])->assertStatus(422);

        $this->actingAs($admin)->patchJson('/api/orders/' . $order['id'] . '/status', [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('status', 'approved');

        $this->actingAs($admin)->patchJson('/api/orders/' . $order['id'] . '/status', [
            'status' => 'cancelled',
        ])->assertOk()->assertJsonPath('status', 'cancelled');

        // Cancelling restores stock
        $this->assertEquals(5, $book->fresh()->stock);

        // Cancelled order cannot transition further
        $this->actingAs($admin)->patchJson('/api/orders/' . $order['id'] . '/status', [
            'status' => 'shipped',
        ])->assertStatus(422);
    }

    public function test_borrow_and_return_with_fine(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['stock' => 2]);

        $borrow = $this->actingAs($user)->postJson('/api/borrows', ['book_id' => $book->id])
            ->assertCreated()->json();
        $this->assertEquals(1, $book->fresh()->stock);

        // Duplicate active borrow for the same book is rejected
        $this->actingAs($user)->postJson('/api/borrows', ['book_id' => $book->id])
            ->assertStatus(422);

        // Make it overdue, then return it.
        Borrow::find($borrow['id'])->update(['due_at' => now()->subDays(3)]);

        $returned = $this->actingAs($user)
            ->postJson('/api/borrows/' . $borrow['id'] . '/return')
            ->assertOk()->json();

        $this->assertEquals('3.00', $returned['fine']);
        $this->assertNotNull($returned['returned_at']);
        $this->assertEquals(2, $book->fresh()->stock);

        // Returning again is rejected
        $this->actingAs($user)
            ->postJson('/api/borrows/' . $borrow['id'] . '/return')
            ->assertStatus(422);
    }

    public function test_borrow_partial_day_overdue_and_out_of_stock(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $emptyBook = Book::factory()->create(['stock' => 0]);
        $book = Book::factory()->create(['stock' => 1]);

        $this->actingAs($user)->postJson('/api/borrows', ['book_id' => $emptyBook->id])
            ->assertStatus(422);

        $borrow = $this->actingAs($user)->postJson('/api/borrows', ['book_id' => $book->id])
            ->assertCreated()->json();

        Borrow::find($borrow['id'])->update(['due_at' => now()->subHours(2)]);

        // Other non-admin user cannot return someone else's borrow
        $this->actingAs($other)->postJson('/api/borrows/' . $borrow['id'] . '/return')
            ->assertForbidden();

        $returned = $this->actingAs($user)
            ->postJson('/api/borrows/' . $borrow['id'] . '/return')
            ->assertOk()->json();

        $this->assertEquals('1.00', $returned['fine']);
    }

    public function test_review_requires_auth_valid_rating_and_existing_book(): void
    {
        $book = Book::factory()->create();
        $this->postJson('/api/books/' . $book->id . '/reviews', ['rating' => 5])
            ->assertUnauthorized();

        $user = User::factory()->create([
            'name' => 'Reviewer',
            'email' => 'reviewer-private@example.com',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->postJson('/api/books/99999/reviews', ['rating' => 5])
            ->assertNotFound();

        $this->actingAs($user)->postJson('/api/books/' . $book->id . '/reviews', ['rating' => 6])
            ->assertStatus(422);

        $created = $this->actingAs($user)->postJson('/api/books/' . $book->id . '/reviews', [
            'rating' => 5, 'comment' => 'Excellent book.',
        ])->assertCreated()->assertJsonPath('rating', 5)->json();

        $this->assertArrayNotHasKey('email', $created['user']);

        // Updating existing review returns 200 and updates instead of duplicating
        $this->actingAs($user)->postJson('/api/books/' . $book->id . '/reviews', [
            'rating' => 4, 'comment' => 'Updated review.',
        ])->assertOk()->assertJsonPath('rating', 4);
        $this->assertEquals(1, Review::where('book_id', $book->id)->count());

        $list = $this->getJson('/api/books/' . $book->id . '/reviews')->assertOk();
        $this->assertArrayNotHasKey('email', $list->json('data.0.user'));

        $other = User::factory()->create();
        $this->actingAs($other)->deleteJson('/api/reviews/' . $created['id'])->assertForbidden();
        $this->actingAs($user)->deleteJson('/api/reviews/' . $created['id'])->assertNoContent();
    }
}
