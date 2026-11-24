<template>
  <div class="book-app">
    <div class="page-head">
      <h1>Book Catalog</h1>
      <button
        v-if="isAdmin"
        type="button"
        class="btn btn-primary"
        @click="toggleAdminForm"
      >
        {{ showForm ? 'Close Book Form' : '+ Add Book' }}
      </button>
    </div>

    <div v-if="banner.text" :class="['alert', banner.type === 'error' ? 'alert-error' : 'alert-success']">
      {{ banner.text }}
    </div>

    <!-- Admin create/edit form -->
    <form v-if="isAdmin && showForm" class="card form-grid" @submit.prevent="saveBook">
      <h2>{{ editingBook ? `Edit: ${editingBook.title}` : 'Add New Book' }}</h2>
      <div class="form-row">
        <div class="form-field">
          <label for="title">Title *</label>
          <input id="title" v-model="form.title" required />
        </div>
        <div class="form-field">
          <label for="author">Author *</label>
          <input id="author" v-model="form.author" required />
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-field">
          <label for="isbn">ISBN</label>
          <input id="isbn" v-model="form.isbn" placeholder="Optional unique ISBN" />
        </div>
        <div class="form-field">
          <label for="price">Price ($)</label>
          <input id="price" v-model.number="form.price" type="number" step="0.01" min="0" />
        </div>
        <div class="form-field">
          <label for="stock">Stock</label>
          <input id="stock" v-model.number="form.stock" type="number" min="0" />
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-field">
          <label for="category_id">Category</label>
          <select id="category_id" v-model="form.category_id">
            <option :value="null">None</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
        <div class="form-field">
          <label for="author_id">Author Profile</label>
          <select id="author_id" v-model="form.author_id">
            <option :value="null">None</option>
            <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div class="form-field">
          <label for="cover_url">Cover URL</label>
          <input id="cover_url" v-model="form.cover_url" type="url" placeholder="https://..." />
        </div>
      </div>
      <div class="form-field">
        <label for="description">Description</label>
        <textarea id="description" v-model="form.description" />
      </div>
      <div v-if="formError" class="alert alert-error">{{ formError }}</div>
      <div class="actions-row">
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : (editingBook ? 'Update Book' : 'Create Book') }}
        </button>
        <button type="button" class="btn btn-secondary" @click="cancelEdit">Cancel</button>
      </div>
    </form>

    <!-- Search & filters -->
    <form class="card filters-bar" @submit.prevent="applySearch">
      <input
        v-model="searchInput"
        placeholder="Search by title, author, or description..."
        aria-label="Search books"
      />
      <select v-model="categoryId" aria-label="Filter by category" @change="onFilterChange">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
          {{ cat.name }}
        </option>
      </select>
      <select v-model="authorId" aria-label="Filter by author" @change="onFilterChange">
        <option value="">All Authors</option>
        <option v-for="a in authors" :key="a.id" :value="String(a.id)">
          {{ a.name }}
        </option>
      </select>
      <select v-model="sortOption" aria-label="Sort books" @change="onFilterChange">
        <option value="created_at:desc">Newest first</option>
        <option value="created_at:asc">Oldest first</option>
        <option value="title:asc">Title (A-Z)</option>
        <option value="price:asc">Price (Low-High)</option>
        <option value="price:desc">Price (High-Low)</option>
      </select>
      <button type="submit" class="btn btn-primary">Search</button>
      <button
        v-if="hasActiveFilters"
        type="button"
        class="btn btn-secondary"
        @click="clearFilters"
      >
        Reset
      </button>
    </form>

    <div v-if="pending" class="card">Loading books...</div>
    <div v-else-if="fetchError" class="alert alert-error">
      {{ extractErrorMessage(fetchError, 'Could not load books.') }}
    </div>
    <div v-else-if="!books.length" class="card">No books match your filters.</div>

    <div v-else class="book-grid">
      <article v-for="book in books" :key="book.id" class="card book-card">
        <div class="book-card-header">
          <div>
            <h2 class="book-title">
              <NuxtLink :to="`/books/${book.id}`">{{ book.title }}</NuxtLink>
            </h2>
            <p class="book-meta">by {{ book.author }}</p>
          </div>
          <span v-if="book.category" class="badge badge-muted">{{ book.category.name }}</span>
        </div>

        <p v-if="book.description" class="book-desc">{{ book.description }}</p>

        <div class="book-stats">
          <strong>${{ Number(book.price || 0).toFixed(2) }}</strong>
          <span :class="['badge', book.stock > 0 ? 'badge-success' : 'badge-danger']">
            {{ book.stock > 0 ? `${book.stock} in stock` : 'Out of stock' }}
          </span>
          <span v-if="book.average_rating !== null && book.average_rating !== undefined" class="rating-pill">
            ★ {{ book.average_rating }} ({{ book.reviews_count ?? 0 }})
          </span>
        </div>

        <div class="actions-row">
          <button
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="book.stock < 1"
            @click="handleAddToCart(book)"
          >
            Add to cart
          </button>
          <NuxtLink :to="`/books/${book.id}`" class="btn btn-secondary btn-sm">Details</NuxtLink>

          <template v-if="isAdmin">
            <button type="button" class="btn btn-secondary btn-sm" @click="startEdit(book)">
              Edit
            </button>
            <template v-if="confirmDeleteId === book.id">
              <button
                type="button"
                class="btn btn-danger btn-sm"
                :disabled="deletingId === book.id"
                @click="deleteBook(book.id)"
              >
                {{ deletingId === book.id ? 'Deleting...' : 'Confirm Delete' }}
              </button>
              <button type="button" class="btn btn-secondary btn-sm" @click="confirmDeleteId = null">
                Cancel
              </button>
            </template>
            <button
              v-else
              type="button"
              class="btn btn-danger btn-sm"
              @click="confirmDeleteId = book.id"
            >
              Delete
            </button>
          </template>
        </div>
      </article>
    </div>

    <div v-if="meta && meta.last_page > 1" class="pagination">
      <button
        type="button"
        class="btn btn-secondary btn-sm"
        :disabled="meta.current_page <= 1"
        @click="goToPage(meta.current_page - 1)"
      >
        Previous
      </button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} books)</span>
      <button
        type="button"
        class="btn btn-secondary btn-sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="goToPage(meta.current_page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const { isAdmin } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()
const { addItem } = useCart()

const page = ref(1)
const searchInput = ref('')
const appliedSearch = ref('')
const categoryId = ref('')
const authorId = ref('')
const sortOption = ref('created_at:desc')

const showForm = ref(false)
const editingBook = ref<any>(null)
const saving = ref(false)
const formError = ref('')
const confirmDeleteId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
const banner = ref<{ type: 'success' | 'error'; text: string }>({ type: 'success', text: '' })

const emptyForm = () => ({
  title: '',
  author: '',
  description: '',
  isbn: '',
  price: 0,
  stock: 0,
  cover_url: '',
  category_id: null as number | null,
  author_id: null as number | null,
})

const form = ref(emptyForm())

const hasActiveFilters = computed(
  () =>
    Boolean(appliedSearch.value || categoryId.value || authorId.value || sortOption.value !== 'created_at:desc')
)

const { data: filtersData } = await useAsyncData('catalog-filters', async () => {
  const [cats, auths] = await Promise.all([
    apiFetch('/api/categories'),
    apiFetch('/api/authors'),
  ])
  return {
    categories: cats?.data || [],
    authors: auths?.data || [],
  }
})

const categories = computed(() => filtersData.value?.categories || [])
const authors = computed(() => filtersData.value?.authors || [])

function buildBooksUrl() {
  const [sort, direction] = sortOption.value.split(':')
  const params = new URLSearchParams()
  params.set('page', String(page.value))
  params.set('sort', sort || 'created_at')
  params.set('direction', direction || 'desc')
  if (appliedSearch.value) params.set('search', appliedSearch.value)
  if (categoryId.value) params.set('category_id', categoryId.value)
  if (authorId.value) params.set('author_id', authorId.value)
  return `/api/books?${params.toString()}`
}

const {
  data: booksResponse,
  pending,
  error: fetchError,
  refresh: refreshBooks,
} = await useAsyncData('books-catalog', () => apiFetch(buildBooksUrl()))

const books = computed(() => booksResponse.value?.data || [])
const meta = computed(() =>
  booksResponse.value
    ? {
        current_page: booksResponse.value.current_page,
        last_page: booksResponse.value.last_page,
        total: booksResponse.value.total,
      }
    : null
)

function applySearch() {
  appliedSearch.value = searchInput.value.trim()
  page.value = 1
  refreshBooks()
}

function onFilterChange() {
  page.value = 1
  refreshBooks()
}

function clearFilters() {
  searchInput.value = ''
  appliedSearch.value = ''
  categoryId.value = ''
  authorId.value = ''
  sortOption.value = 'created_at:desc'
  page.value = 1
  refreshBooks()
}

function goToPage(nextPage: number) {
  page.value = nextPage
  refreshBooks()
}

function toggleAdminForm() {
  if (showForm.value) {
    cancelEdit()
  } else {
    showForm.value = true
  }
}

function startEdit(book: any) {
  editingBook.value = book
  showForm.value = true
  formError.value = ''
  form.value = {
    title: book.title || '',
    author: book.author || '',
    description: book.description || '',
    isbn: book.isbn || '',
    price: Number(book.price || 0),
    stock: Number(book.stock ?? 0),
    cover_url: book.cover_url || '',
    category_id: book.category_id ?? null,
    author_id: book.author_id ?? null,
  }
}

function cancelEdit() {
  editingBook.value = null
  showForm.value = false
  formError.value = ''
  form.value = emptyForm()
}

async function saveBook() {
  saving.value = true
  formError.value = ''
  banner.value = { type: 'success', text: '' }

  const payload = {
    title: form.value.title.trim(),
    author: form.value.author.trim(),
    description: form.value.description?.trim() || null,
    isbn: form.value.isbn?.trim() || null,
    price: form.value.price === null || (form.value.price as any) === '' ? 0 : Number(form.value.price),
    stock: form.value.stock === null || (form.value.stock as any) === '' ? 0 : Number(form.value.stock),
    cover_url: form.value.cover_url?.trim() || null,
    category_id: form.value.category_id || null,
    author_id: form.value.author_id || null,
  }

  try {
    if (editingBook.value) {
      await apiFetch(`/api/books/${editingBook.value.id}`, { method: 'PUT', body: payload })
      banner.value = { type: 'success', text: 'Book updated successfully.' }
    } else {
      await apiFetch('/api/books', { method: 'POST', body: payload })
      banner.value = { type: 'success', text: 'Book created successfully.' }
    }
    cancelEdit()
    await refreshBooks()
  } catch (err) {
    formError.value = extractErrorMessage(err, 'Failed to save book.')
  } finally {
    saving.value = false
  }
}

async function deleteBook(id: number) {
  deletingId.value = id
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch(`/api/books/${id}`, { method: 'DELETE' })
    confirmDeleteId.value = null
    banner.value = { type: 'success', text: 'Book deleted.' }
    await refreshBooks()
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not delete book.') }
  } finally {
    deletingId.value = null
  }
}

function handleAddToCart(book: any) {
  const result = addItem(book)
  banner.value = { type: result.ok ? 'success' : 'error', text: result.message }
}
</script>

<style scoped>
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.page-head h1 { margin: 0; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.85rem; }
.form-row-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.85rem; }
.filters-bar { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto auto; gap: 0.6rem; align-items: center; }
@media (max-width: 820px) { .filters-bar { grid-template-columns: 1fr; } }
.book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.book-card { display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem; margin-bottom: 0; }
.book-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.book-title { margin: 0; font-size: 1.1rem; }
.book-title a { color: #0f172a; text-decoration: none; }
.book-title a:hover { color: #2563eb; text-decoration: underline; }
.book-meta { margin: 0.2rem 0 0; font-size: 0.88rem; color: #475569; }
.book-desc { margin: 0; font-size: 0.9rem; color: #334155; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.book-stats { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; font-size: 0.92rem; }
.rating-pill { color: #b45309; font-weight: 600; font-size: 0.85rem; }
.actions-row { display: flex; flex-wrap: wrap; gap: 0.45rem; }
</style>
