<template>
  <div class="app-shell">
    <header class="site-header">
      <div class="container header-inner">
        <NuxtLink to="/" class="brand">BookHub</NuxtLink>
        <nav class="nav-links">
          <NuxtLink to="/">Catalog</NuxtLink>
          <NuxtLink to="/cart">
            Cart <span v-if="itemCount > 0" class="badge">{{ itemCount }}</span>
          </NuxtLink>
          <template v-if="isAuthenticated">
            <NuxtLink to="/orders">Orders</NuxtLink>
            <NuxtLink to="/borrows">Borrows</NuxtLink>
            <NuxtLink v-if="isAdmin" to="/admin">Admin</NuxtLink>
          </template>
        </nav>
        <div class="auth-actions">
          <template v-if="isAuthenticated">
            <span class="user-chip">
              {{ user?.name }}
              <span v-if="isAdmin" class="badge badge-admin">Admin</span>
            </span>
            <button type="button" class="btn btn-secondary btn-sm" @click="logout">
              Logout
            </button>
          </template>
          <template v-else-if="authReady">
            <NuxtLink to="/login" class="nav-link-btn">Login</NuxtLink>
            <NuxtLink to="/register" class="btn btn-primary btn-sm">Register</NuxtLink>
          </template>
        </div>
      </div>
    </header>
    <main class="container main-content">
      <NuxtPage />
    </main>
  </div>
</template>

<script setup lang="ts">
const { user, isAuthenticated, isAdmin, authReady, initAuth, logout } = useAuth()
const { itemCount, initCart } = useCart()

onMounted(() => {
  initAuth()
  initCart()
})
</script>

<style>
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, -apple-system, sans-serif; color: #111827; background: #f8fafc; line-height: 1.5; }
.container { max-width: 1100px; margin: 0 auto; padding: 0 1rem; }
.site-header { background: #0f172a; color: #fff; border-bottom: 1px solid #1e293b; }
.header-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.85rem 1rem; }
.brand { font-size: 1.25rem; font-weight: 700; color: #fff; text-decoration: none; }
.nav-links { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
.nav-links a, .nav-link-btn { color: #e2e8f0; text-decoration: none; font-size: 0.95rem; }
.nav-links a:hover, .nav-links a.router-link-active { color: #fff; text-decoration: underline; }
.auth-actions { display: flex; align-items: center; gap: 0.75rem; }
.user-chip { font-size: 0.9rem; color: #cbd5e1; display: inline-flex; align-items: center; gap: 0.4rem; }
.main-content { padding: 1.5rem 1rem 3rem; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.5rem 0.9rem; border-radius: 6px; border: 1px solid transparent; font-size: 0.9rem; font-weight: 500; cursor: pointer; text-decoration: none; }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-sm { padding: 0.35rem 0.65rem; font-size: 0.82rem; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #1d4ed8; }
.btn-secondary { background: #e2e8f0; color: #0f172a; }
.btn-secondary:hover:not(:disabled) { background: #cbd5e1; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }

.badge { display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #2563eb; color: #fff; }
.badge-admin { background: #7c3aed; }
.badge-muted { background: #e2e8f0; color: #334155; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }

.alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.92rem; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

.form-grid { display: grid; gap: 0.85rem; }
.form-field { display: flex; flex-direction: column; gap: 0.3rem; }
.form-field label { font-size: 0.88rem; font-weight: 600; color: #334155; }
input, textarea, select { width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; background: #fff; }
textarea { min-height: 80px; resize: vertical; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
</style>
