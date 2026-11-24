<template>
  <div>
    <h1>My Borrows</h1>

    <div v-if="!authReady" class="card">Checking session...</div>
    <div v-else-if="!isAuthenticated" class="alert alert-info">
      Please <NuxtLink to="/login">log in</NuxtLink> to view your borrowed books.
    </div>
    <template v-else>
      <div v-if="banner.text" :class="['alert', banner.type === 'error' ? 'alert-error' : 'alert-success']">
        {{ banner.text }}
      </div>

      <div v-if="loading" class="card">Loading borrows...</div>
      <div v-else-if="error" class="alert alert-error">{{ error }}</div>
      <div v-else-if="!borrows.length" class="card">
        You have no borrow records. <NuxtLink to="/">Browse books</NuxtLink>
      </div>

      <div v-else>
        <div v-for="borrow in borrows" :key="borrow.id" class="card">
          <div class="row-head">
            <div>
              <strong>{{ borrow.book?.title || `Book #${borrow.book_id}` }}</strong>
              <div class="subtext">
                Borrowed: {{ formatDate(borrow.borrowed_at) }} |
                Due: {{ formatDate(borrow.due_at) }}
              </div>
            </div>
            <span
              :class="[
                'badge',
                borrow.returned_at
                  ? 'badge-success'
                  : isOverdue(borrow)
                    ? 'badge-danger'
                    : 'badge-warning',
              ]"
            >
              {{ borrow.returned_at ? 'Returned' : isOverdue(borrow) ? 'Overdue' : 'Active' }}
            </span>
          </div>

          <p v-if="borrow.returned_at" class="subtext">
            Returned: {{ formatDate(borrow.returned_at) }}
            <span v-if="Number(borrow.fine) > 0"> — Fine: ${{ Number(borrow.fine).toFixed(2) }}</span>
          </p>

          <div v-if="!borrow.returned_at" class="actions-row">
            <button
              type="button"
              class="btn btn-primary btn-sm"
              :disabled="returningId === borrow.id"
              @click="returnBook(borrow.id)"
            >
              {{ returningId === borrow.id ? 'Returning...' : 'Return book' }}
            </button>
          </div>
        </div>

        <div v-if="meta && meta.last_page > 1" class="pagination">
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            :disabled="page <= 1"
            @click="loadBorrows(page - 1)"
          >
            Previous
          </button>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            :disabled="page >= meta.last_page"
            @click="loadBorrows(page + 1)"
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

const borrows = ref<any[]>([])
const meta = ref<any>(null)
const page = ref(1)
const loading = ref(false)
const error = ref('')
const returningId = ref<number | null>(null)
const banner = ref<{ type: 'success' | 'error'; text: string }>({ type: 'success', text: '' })

function formatDate(val: string) {
  return val ? new Date(val).toLocaleString() : ''
}

function isOverdue(borrow: any) {
  return !borrow.returned_at && borrow.due_at && new Date(borrow.due_at).getTime() < Date.now()
}

async function loadBorrows(targetPage = 1) {
  if (!isAuthenticated.value) return
  loading.value = true
  error.value = ''
  page.value = targetPage
  try {
    const res = await apiFetch(`/api/borrows?page=${targetPage}`)
    borrows.value = res?.data || []
    meta.value = { current_page: res.current_page, last_page: res.last_page }
  } catch (err) {
    error.value = extractErrorMessage(err, 'Could not load borrows.')
  } finally {
    loading.value = false
  }
}

async function returnBook(borrowId: number) {
  returningId.value = borrowId
  banner.value = { type: 'success', text: '' }
  try {
    const updated = await apiFetch(`/api/borrows/${borrowId}/return`, { method: 'POST' })
    const idx = borrows.value.findIndex((b) => b.id === borrowId)
    if (idx >= 0) borrows.value[idx] = updated
    const fineNum = Number(updated.fine || 0)
    banner.value = {
      type: 'success',
      text:
        fineNum > 0
          ? `Book returned. Overdue fine recorded: $${fineNum.toFixed(2)}.`
          : 'Book returned on time.',
    }
  } catch (err) {
    banner.value = { type: 'error', text: extractErrorMessage(err, 'Could not return book.') }
  } finally {
    returningId.value = null
  }
}

onMounted(async () => {
  await initAuth()
  if (isAuthenticated.value) await loadBorrows(1)
})
</script>

<style scoped>
.row-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.subtext { font-size: 0.85rem; color: #64748b; margin-top: 0.25rem; }
.actions-row { margin-top: 0.75rem; }
</style>
