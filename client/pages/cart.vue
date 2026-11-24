<template>
  <div>
    <h1>Shopping Cart</h1>

    <div v-if="message.text" :class="['alert', message.type === 'error' ? 'alert-error' : 'alert-success']">
      {{ message.text }}
      <NuxtLink v-if="message.showOrdersLink" to="/orders">View my orders</NuxtLink>
    </div>

    <div v-if="!items.length" class="card">
      <p>Your cart is empty. <NuxtLink to="/">Browse books</NuxtLink></p>
    </div>

    <div v-else class="card">
      <ul class="cart-list">
        <li v-for="item in items" :key="item.book_id" class="cart-row">
          <div>
            <NuxtLink :to="`/books/${item.book_id}`"><strong>{{ item.title }}</strong></NuxtLink>
            <div class="subtext">${{ Number(item.price).toFixed(2) }} each</div>
          </div>
          <div class="qty-controls">
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              @click="updateQty(item.book_id, item.qty - 1)"
            >
              -
            </button>
            <span>{{ item.qty }}</span>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              @click="updateQty(item.book_id, item.qty + 1)"
            >
              +
            </button>
            <strong class="line-total">${{ (Number(item.price) * item.qty).toFixed(2) }}</strong>
            <button
              type="button"
              class="btn btn-danger btn-sm"
              @click="removeItem(item.book_id)"
            >
              Remove
            </button>
          </div>
        </li>
      </ul>

      <div class="cart-footer">
        <p class="grand-total"><strong>Total: ${{ total.toFixed(2) }}</strong></p>
        <div class="actions-row">
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!isAuthenticated || submitting"
            @click="checkout"
          >
            {{ submitting ? 'Placing order...' : 'Place order' }}
          </button>
          <button type="button" class="btn btn-secondary" @click="clearCart">Clear cart</button>
        </div>
        <p v-if="!isAuthenticated" class="subtext">
          <NuxtLink to="/login">Log in</NuxtLink> to place an order.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const { isAuthenticated } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()
const { items, total, updateQty, removeItem, clearCart } = useCart()

const submitting = ref(false)
const message = ref<{ type: 'success' | 'error'; text: string; showOrdersLink?: boolean }>({
  type: 'success',
  text: '',
})

async function checkout() {
  submitting.value = true
  message.value = { type: 'success', text: '' }
  try {
    await apiFetch('/api/orders', {
      method: 'POST',
      body: { items: items.value.map((i) => ({ book_id: i.book_id, qty: i.qty })) },
    })
    clearCart()
    message.value = {
      type: 'success',
      text: 'Order placed successfully! ',
      showOrdersLink: true,
    }
  } catch (err) {
    message.value = {
      type: 'error',
      text: extractErrorMessage(err, 'Checkout failed.'),
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.cart-list { list-style: none; padding: 0; margin: 0; }
.cart-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0; }
.qty-controls { display: flex; align-items: center; gap: 0.5rem; }
.line-total { min-width: 72px; text-align: right; }
.subtext { font-size: 0.85rem; color: #64748b; }
.cart-footer { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.6rem; }
.grand-total { margin: 0; font-size: 1.15rem; }
.actions-row { display: flex; gap: 0.6rem; }
</style>
