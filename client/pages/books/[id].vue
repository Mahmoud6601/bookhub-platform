<template>
  <div>
    <p><NuxtLink to="/">&larr; Back to catalog</NuxtLink></p>

    <div v-if="pending" class="card">Loading book details...</div>
    <div v-else-if="error || !book" class="alert alert-error">Book not found.</div>

    <template v-else>
      <div class="card detail-card">
        <img v-if="book.cover_url" :src="book.cover_url" :alt="book.title" class="cover-img" />
        <div class="detail-body">
          <h1>{{ book.title }}</h1>
          <p class="meta"><strong>Author:</strong> {{ book.author }}</p>
          <p v-if="book.category" class="meta">
            <strong>Category:</strong> {{ book.category.name }}
          </p>
          <p v-if="book.isbn" class="meta"><strong>ISBN:</strong> {{ book.isbn }}</p>
          <p class="meta">
            <strong>Price:</strong> ${{ Number(book.price || 0).toFixed(2) }}
            &nbsp;|&nbsp;
            <strong>Stock:</strong>
            <span :class="['badge', book.stock > 0 ? 'badge-success' : 'badge-danger']">
              {{ book.stock > 0 ? `${book.stock} available` : 'Out of stock' }}
            </span>
          </p>
          <p v-if="book.average_rating !== null && book.average_rating !== undefined" class="meta">
            <strong>Rating:</strong> ★ {{ book.average_rating }}/5 ({{ book.reviews_count }} reviews)
          </p>
          <p v-if="book.description" class="desc">{{ book.description }}</p>

          <div v-if="actionMsg.text" :class="['alert', actionMsg.type === 'error' ? 'alert-error' : 'alert-success']">
            {{ actionMsg.text }}
            <template v-if="actionMsg.showBorrowsLink">
              <NuxtLink to="/borrows">View my borrows</NuxtLink>
            </template>
          </div>

          <div class="actions-row">
            <button
              type="button"
              class="btn btn-primary"
              :disabled="book.stock < 1"
              @click="handleAddToCart"
            >
              Add to cart
            </button>
            <button
              v-if="isAuthenticated"
              type="button"
              class="btn btn-secondary"
              :disabled="book.stock < 1 || borrowing"
              @click="borrowBook"
            >
              {{ borrowing ? 'Borrowing...' : 'Borrow (14 days)' }}
            </button>
            <NuxtLink v-else to="/login" class="btn btn-secondary">Log in to borrow</NuxtLink>
          </div>
        </div>
      </div>

      <section class="card">
        <h2>Reviews ({{ reviews.length }})</h2>
        <p v-if="!reviews.length">No reviews yet. Be the first to review this book.</p>
        <ul v-else class="review-list">
          <li v-for="review in reviews" :key="review.id" class="review-item">
            <div class="review-head">
              <div>
                <strong>{{ review.user?.name || 'Reader' }}</strong>
                <span class="rating-pill"> — {{ review.rating }}/5</span>
              </div>
              <button
                v-if="isAdmin || user?.id === review.user_id"
                type="button"
                class="btn btn-danger btn-sm"
                :disabled="deletingReviewId === review.id"
                @click="deleteReview(review.id)"
              >
                Delete
              </button>
            </div>
            <p v-if="review.comment" class="review-comment">{{ review.comment }}</p>
          </li>
        </ul>

        <form v-if="isAuthenticated" class="form-grid review-form" @submit.prevent="submitReview">
          <h3>Write or Update Your Review</h3>
          <div class="form-field">
            <label for="rating">Rating (1–5)</label>
            <input id="rating" v-model.number="rating" type="number" min="1" max="5" required />
          </div>
          <div class="form-field">
            <label for="comment">Comment</label>
            <textarea id="comment" v-model="comment" placeholder="Share your thoughts..." />
          </div>
          <div v-if="reviewError" class="alert alert-error">{{ reviewError }}</div>
          <div v-if="reviewSuccess" class="alert alert-success">{{ reviewSuccess }}</div>
          <div>
            <button type="submit" class="btn btn-primary" :disabled="submittingReview">
              {{ submittingReview ? 'Submitting...' : 'Submit review' }}
            </button>
          </div>
        </form>
        <p v-else><NuxtLink to="/login">Log in</NuxtLink> to write a review.</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const { user, isAuthenticated, isAdmin } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()
const { addItem } = useCart()

const rating = ref(5)
const comment = ref('')
const submittingReview = ref(false)
const deletingReviewId = ref<number | null>(null)
const reviewError = ref('')
const reviewSuccess = ref('')
const borrowing = ref(false)
const actionMsg = ref<{ type: 'success' | 'error'; text: string; showBorrowsLink?: boolean }>({
  type: 'success',
  text: '',
})

const {
  data: book,
  pending,
  error,
  refresh,
} = await useAsyncData(`book-${route.params.id}`, () =>
  apiFetch(`/api/books/${route.params.id}`)
)

const reviews = computed(() => book.value?.reviews || [])

function handleAddToCart() {
  if (!book.value) return
  const res = addItem(book.value)
  actionMsg.value = { type: res.ok ? 'success' : 'error', text: res.message }
}

async function borrowBook() {
  if (!book.value) return
  borrowing.value = true
  actionMsg.value = { type: 'success', text: '' }
  try {
    await apiFetch('/api/borrows', {
      method: 'POST',
      body: { book_id: book.value.id },
    })
    actionMsg.value = {
      type: 'success',
      text: 'Book borrowed for 14 days. ',
      showBorrowsLink: true,
    }
    await refresh()
  } catch (err) {
    actionMsg.value = {
      type: 'error',
      text: extractErrorMessage(err, 'Could not borrow this book.'),
    }
  } finally {
    borrowing.value = false
  }
}

async function submitReview() {
  submittingReview.value = true
  reviewError.value = ''
  reviewSuccess.value = ''
  try {
    const saved = await apiFetch(`/api/books/${route.params.id}/reviews`, {
      method: 'POST',
      body: { rating: rating.value, comment: comment.value.trim() || null },
    })

    if (book.value && Array.isArray(book.value.reviews)) {
      const idx = book.value.reviews.findIndex((r: any) => r.id === saved.id)
      if (idx >= 0) {
        book.value.reviews[idx] = saved
      } else {
        book.value.reviews.unshift(saved)
      }
    }

    reviewSuccess.value = 'Review saved.'
    await refresh()
  } catch (err) {
    reviewError.value = extractErrorMessage(err, 'Could not save review.')
  } finally {
    submittingReview.value = false
  }
}

async function deleteReview(reviewId: number) {
  deletingReviewId.value = reviewId
  reviewError.value = ''
  try {
    await apiFetch(`/api/reviews/${reviewId}`, { method: 'DELETE' })
    await refresh()
  } catch (err) {
    reviewError.value = extractErrorMessage(err, 'Could not delete review.')
  } finally {
    deletingReviewId.value = null
  }
}
</script>

<style scoped>
.detail-card { display: flex; flex-wrap: wrap; gap: 1.5rem; }
.cover-img { width: 180px; height: 260px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
.detail-body { flex: 1; min-width: 240px; }
.detail-body h1 { margin-top: 0; }
.meta { margin: 0.4rem 0; color: #334155; }
.desc { margin: 1rem 0; }
.actions-row { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 1rem; }
.review-list { list-style: none; padding: 0; margin: 0 0 1.25rem; display: grid; gap: 0.75rem; }
.review-item { padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; }
.review-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.review-comment { margin: 0.4rem 0 0; }
.rating-pill { color: #b45309; font-weight: 600; }
.review-form { border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: 1rem; }
</style>
