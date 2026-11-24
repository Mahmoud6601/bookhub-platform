<template>
  <div>
    <h1>My Orders</h1>

    <div v-if="!authReady" class="card">Checking session...</div>
    <div v-else-if="!isAuthenticated" class="alert alert-info">
      Please <NuxtLink to="/login">log in</NuxtLink> to view your orders.
    </div>
    <template v-else>
      <div v-if="loading" class="card">Loading orders...</div>
      <div v-else-if="error" class="alert alert-error">{{ error }}</div>
      <div v-else-if="!orders.length" class="card">
        You have not placed any orders yet. <NuxtLink to="/">Browse books</NuxtLink>
      </div>
      <div v-else>
        <div v-for="order in orders" :key="order.id" class="card">
          <div class="row-head">
            <div>
              <strong>Order #{{ order.id }}</strong>
              <span class="subtext"> — {{ formatDate(order.created_at) }}</span>
            </div>
            <span :class="['badge', statusBadge(order.status)]">{{ order.status }}</span>
          </div>
          <ul class="item-list">
            <li v-for="item in order.items || []" :key="item.id">
              {{ item.book?.title || `Book #${item.book_id}` }} &times; {{ item.qty }}
              — ${{ Number(item.price).toFixed(2) }}
            </li>
          </ul>
          <p class="total-line"><strong>Total: ${{ Number(order.total).toFixed(2) }}</strong></p>
        </div>

        <div v-if="meta && meta.last_page > 1" class="pagination">
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            :disabled="page <= 1"
            @click="loadOrders(page - 1)"
          >
            Previous
          </button>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            :disabled="page >= meta.last_page"
            @click="loadOrders(page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const { isAuthenticated, authReady, initAuth } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()

const orders = ref<any[]>([])
const meta = ref<any>(null)
const page = ref(1)
const loading = ref(false)
const error = ref('')

function statusBadge(status: string) {
  if (status === 'delivered') return 'badge-success'
  if (status === 'cancelled') return 'badge-danger'
  if (status === 'pending') return 'badge-warning'
  return 'badge-muted'
}

function formatDate(val: string) {
  return val ? new Date(val).toLocaleString() : ''
}

async function loadOrders(targetPage = 1) {
  if (!isAuthenticated.value) return
  loading.value = true
  error.value = ''
  page.value = targetPage
  try {
    const res = await apiFetch(`/api/orders?page=${targetPage}`)
    orders.value = res?.data || []
    meta.value = { current_page: res.current_page, last_page: res.last_page }
  } catch (err) {
    error.value = extractErrorMessage(err, 'Could not load orders.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await initAuth()
  if (isAuthenticated.value) await loadOrders(1)
})
</script>

<style scoped>
.row-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.subtext { font-size: 0.85rem; color: #64748b; }
.item-list { margin: 0.5rem 0; padding-left: 1.25rem; }
.total-line { margin: 0.5rem 0 0; }
</style>
