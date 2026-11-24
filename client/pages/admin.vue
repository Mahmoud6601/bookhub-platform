<template>
  <div>
    <h1>Admin Dashboard</h1>

    <div v-if="!authReady" class="card">Checking permissions...</div>
    <div v-else-if="!isAuthenticated" class="alert alert-info">
      Please <NuxtLink to="/login">log in</NuxtLink> with an admin account to access this page.
    </div>
    <div v-else-if="!isAdmin" class="alert alert-error">
      Access denied. Administrator privileges are required.
    </div>

    <template v-else>
      <div v-if="banner.text" :class="['alert', banner.type === 'error' ? 'alert-error' : 'alert-success']">
        {{ banner.text }}
      </div>

      <div v-if="stats" class="stats-grid">
        <div class="card stat-card"><div class="stat-num">{{ stats.books }}</div><div>Books</div></div>
        <div class="card stat-card"><div class="stat-num">{{ stats.orders }}</div><div>Orders ({{ stats.pending_orders }} pending)</div></div>
        <div class="card stat-card"><div class="stat-num">{{ stats.borrows }}</div><div>Borrows ({{ stats.active_borrows }} active)</div></div>
        <div class="card stat-card"><div class="stat-num">{{ stats.users }}</div><div>Users</div></div>
      </div>

      <div class="tabs">
        <button
          v-for="tab in ['orders', 'categories', 'authors', 'borrows']"
          :key="tab"
          type="button"
          :class="['btn', activeTab === tab ? 'btn-primary' : 'btn-secondary']"
          @click="activeTab = tab"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- Orders tab -->
      <section v-if="activeTab === 'orders'" class="card">
        <h2>Manage Orders</h2>
        <p v-if="!orders.length">No orders found.</p>
        <div v-for="order in orders" :key="order.id" class="admin-row">
          <div>
            <strong>Order #{{ order.id }}</strong> — {{ order.user?.name || 'User' }} ({{ order.user?.email || '' }})
            <div class="subtext">Total: ${{ Number(order.total).toFixed(2) }} | Status: <strong>{{ order.status }}</strong></div>
          </div>
          <div class="actions-row">
            <template v-if="allowedTransitions(order.status).length">
              <button
                v-for="nextStatus in allowedTransitions(order.status)"
                :key="nextStatus"
                type="button"
                :class="['btn btn-sm', nextStatus === 'cancelled' ? 'btn-danger' : 'btn-primary']"
                :disabled="updatingOrderId === order.id"
                @click="updateOrderStatus(order.id, nextStatus)"
              >
                Mark {{ nextStatus }}
              </button>
            </template>
            <span v-else class="badge badge-muted">Final</span>
          </div>
        </div>
      </section>

      <!-- Categories tab -->
      <section v-else-if="activeTab === 'categories'" class="card">
        <h2>Manage Categories</h2>
        <form class="inline-form" @submit.prevent="createCategory">
          <input v-model="newCategory.name" placeholder="Category name" required />
          <input v-model="newCategory.slug" placeholder="Slug (optional)" />
          <button type="submit" class="btn btn-primary" :disabled="savingCat">Add Category</button>
        </form>
        <div v-for="cat in categories" :key="cat.id" class="admin-row">
          <div>
            <strong>{{ cat.name }}</strong> <span class="subtext">({{ cat.slug }}) — {{ cat.books_count ?? 0 }} books</span>
          </div>
          <button type="button" class="btn btn-danger btn-sm" @click="deleteCategory(cat.id)">Delete</button>
        </div>
      </section>

      <!-- Authors tab -->
      <section v-else-if="activeTab === 'authors'" class="card">
        <h2>Manage Authors</h2>
        <form class="inline-form" @submit.prevent="createAuthor">
          <input v-model="newAuthor.name" placeholder="Author name" required />
          <input v-model="newAuthor.bio" placeholder="Short bio (optional)" />
          <button type="submit" class="btn btn-primary" :disabled="savingAuthor">Add Author</button>
        </form>
        <div v-for="author in authors" :key="author.id" class="admin-row">
          <div>
            <strong>{{ author.name }}</strong> <span class="subtext">— {{ author.books_count ?? 0 }} books</span>
            <div v-if="author.bio" class="subtext">{{ author.bio }}</div>
          </div>
          <button type="button" class="btn btn-danger btn-sm" @click="deleteAuthor(author.id)">Delete</button>
        </div>
      </section>

      <!-- Borrows tab -->
      <section v-else-if="activeTab === 'borrows'" class="card">
        <h2>Manage Borrows</h2>
        <p v-if="!borrows.length">No borrow records found.</p>
        <div v-for="borrow in borrows" :key="borrow.id" class="admin-row">
          <div>
            <strong>{{ borrow.book?.title || `Book #${borrow.book_id}` }}</strong>
            — {{ borrow.user?.name || 'User' }} ({{ borrow.user?.email || '' }})
            <div class="subtext">
              Due: {{ new Date(borrow.due_at).toLocaleDateString() }}
              <span v-if="borrow.returned_at"> | Returned (Fine: ${{ Number(borrow.fine || 0).toFixed(2) }})</span>
            </div>
          </div>
          <div class="actions-row">
            <button
              v-if="!borrow.returned_at"
              type="button"
              class="btn btn-primary btn-sm"
              @click="returnBorrow(borrow.id)"
            >
              Mark Returned
            </button>
            <span v-else class="badge badge-success">Returned</span>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
const { isAuthenticated, isAdmin, authReady, initAuth } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()

const ORDER_TRANSITIONS: Record<string, string[]> = {
  pending: ['approved', 'cancelled'],
  approved: ['shipped', 'cancelled'],
  shipped: ['delivered'],
  delivered: [],
  cancelled: [],
}

const activeTab = ref('orders')
const stats = ref<any>(null)
const orders = ref<any[]>([])
const categories = ref<any[]>([])
const authors = ref<any[]>([])
const borrows = ref<any[]>([])
const updatingOrderId = ref<number | null>(null)
const savingCat = ref(false)
const savingAuthor = ref(false)
const newCategory = ref({ name: '', slug: '' })
const newAuthor = ref({ name: '', bio: '' })
const banner = ref<{ type: 'success' | 'error'; text: string }>({ type: 'success', text: '' })

function allowedTransitions(status: string) {
  return ORDER_TRANSITIONS[status] || []
}

async function loadAllAdminData() {
  if (!isAdmin.value) return
  try {
    const [statsRes, ordersRes, catsRes, authorsRes, borrowsRes] = await Promise.all([
      apiFetch('/api/admin/stats'),
      apiFetch('/api/orders'),
      apiFetch('/api/categories'),
      apiFetch('/api/authors'),
      apiFetch('/api/borrows'),
    ])
    stats.value = statsRes
    orders.value = ordersRes?.data || []
    categories.value = catsRes?.data || []
    authors.value = authorsRes?.data || []
    borrows.value = borrowsRes?.data || []
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Failed to load admin data.') }
  }
}

async function updateOrderStatus(orderId: number, status: string) {
  updatingOrderId.value = orderId
  banner.value = { type: 'success', text: '' }
  try {
    const updated = await apiFetch(`/api/orders/${orderId}/status`, {
      method: 'PATCH',
      body: { status },
    })
    const idx = orders.value.findIndex((o) => o.id === orderId)
    if (idx >= 0) orders.value[idx] = updated
    stats.value = await apiFetch('/api/admin/stats')
    banner.value = { type: 'success', text: `Order #${orderId} updated to ${status}.` }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Failed to update order.') }
  } finally {
    updatingOrderId.value = null
  }
}

async function createCategory() {
  savingCat.value = true
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch('/api/categories', {
      method: 'POST',
      body: {
        name: newCategory.value.name.trim(),
        slug: newCategory.value.slug.trim() || null,
      },
    })
    newCategory.value = { name: '', slug: '' }
    await loadAllAdminData()
    banner.value = { type: 'success', text: 'Category created.' }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not create category.') }
  } finally {
    savingCat.value = false
  }
}

async function deleteCategory(id: number) {
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch(`/api/categories/${id}`, { method: 'DELETE' })
    await loadAllAdminData()
    banner.value = { type: 'success', text: 'Category deleted.' }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not delete category.') }
  }
}

async function createAuthor() {
  savingAuthor.value = true
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch('/api/authors', {
      method: 'POST',
      body: { name: newAuthor.value.name.trim(), bio: newAuthor.value.bio.trim() || null },
    })
    newAuthor.value = { name: '', bio: '' }
    await loadAllAdminData()
    banner.value = { type: 'success', text: 'Author created.' }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not create author.') }
  } finally {
    savingAuthor.value = false
  }
}

async function deleteAuthor(id: number) {
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch(`/api/authors/${id}`, { method: 'DELETE' })
    await loadAllAdminData()
    banner.value = { type: 'success', text: 'Author deleted.' }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not delete author.') }
  }
}

async function returnBorrow(borrowId: number) {
  banner.value = { type: 'success', text: '' }
  try {
    await apiFetch(`/api/borrows/${borrowId}/return`, { method: 'POST' })
    await loadAllAdminData()
    banner.value = { type: 'success', text: `Borrow #${borrowId} marked returned.` }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not return borrow.') }
  }
}

onMounted(async () => {
  await initAuth()
  if (isAdmin.value) await loadAllAdminData()
})
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.stat-card { text-align: center; margin-bottom: 0; }
.stat-num { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
.tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.inline-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.6rem; margin-bottom: 1rem; }
@media (max-width: 700px) { .inline-form { grid-template-columns: 1fr; } }
.admin-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.75rem 0; border-top: 1px solid #e2e8f0; }
.subtext { font-size: 0.85rem; color: #64748b; }
.actions-row { display: flex; flex-wrap: wrap; gap: 0.45rem; }
</style>
