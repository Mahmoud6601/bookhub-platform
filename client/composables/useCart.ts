export interface CartItem {
  book_id: number
  title: string
  author?: string
  price: number
  stock?: number
  qty: number
}

export const useCart = () => {
  const items = useState<CartItem[]>('bookhub_cart', () => [])
  const loaded = useState<boolean>('bookhub_cart_loaded', () => false)

  function saveCart() {
    if (process.client) {
      localStorage.setItem('bookhub_cart', JSON.stringify(items.value))
    }
  }

  function initCart() {
    if (!process.client || loaded.value) return
    try {
      const raw = localStorage.getItem('bookhub_cart')
      const parsed = raw ? JSON.parse(raw) : []
      items.value = Array.isArray(parsed) ? parsed : []
    } catch {
      items.value = []
    } finally {
      loaded.value = true
    }
  }

  function addItem(book: { id: number; title: string; author?: string; price?: number | string; stock?: number }) {
    initCart()
    const stock = Number(book.stock ?? 0)
    if (stock < 1) {
      return { ok: false, message: 'This book is currently out of stock.' }
    }

    const existing = items.value.find((item) => item.book_id === book.id)
    if (existing) {
      if (existing.qty >= stock) {
        return { ok: false, message: `Only ${stock} copy/copies available in stock.` }
      }
      existing.qty += 1
      existing.stock = stock
      existing.price = Number(book.price || 0)
    } else {
      items.value.push({
        book_id: book.id,
        title: book.title,
        author: book.author,
        price: Number(book.price || 0),
        stock,
        qty: 1,
      })
    }

    saveCart()
    return { ok: true, message: `Added "${book.title}" to your cart.` }
  }

  function updateQty(bookId: number, nextQty: number) {
    if (nextQty <= 0) {
      removeItem(bookId)
      return
    }
    const target = items.value.find((item) => item.book_id === bookId)
    if (!target) return
    const maxQty = target.stock && target.stock > 0 ? target.stock : nextQty
    target.qty = Math.min(Math.max(1, nextQty), maxQty)
    saveCart()
  }

  function removeItem(bookId: number) {
    items.value = items.value.filter((item) => item.book_id !== bookId)
    saveCart()
  }

  function clearCart() {
    items.value = []
    saveCart()
  }

  const itemCount = computed(() => items.value.reduce((sum, item) => sum + item.qty, 0))
  const total = computed(() =>
    items.value.reduce((sum, item) => sum + Number(item.price) * item.qty, 0)
  )

  return {
    items,
    itemCount,
    total,
    initCart,
    addItem,
    updateQty,
    removeItem,
    clearCart,
  }
}
