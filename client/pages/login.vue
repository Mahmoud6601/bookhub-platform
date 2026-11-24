<template>
  <div class="auth-card card">
    <h1>Log in</h1>
    <form class="form-grid" @submit.prevent="login">
      <div class="form-field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" />
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <input id="password" v-model="password" type="password" required autocomplete="current-password" />
      </div>
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <button type="submit" class="btn btn-primary" :disabled="submitting">
        {{ submitting ? 'Logging in...' : 'Log in' }}
      </button>
    </form>
    <p class="footer-note">No account? <NuxtLink to="/register">Register</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
const { setSession } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

async function login() {
  error.value = ''
  submitting.value = true
  try {
    const res = await apiFetch('/api/login', {
      method: 'POST',
      body: { email: email.value, password: password.value },
    })
    setSession(res.token, res.user)
    await navigateTo('/')
  } catch (err) {
    error.value = extractErrorMessage(err, 'Login failed.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.auth-card { max-width: 420px; margin: 1.5rem auto; }
.footer-note { margin-top: 1rem; font-size: 0.9rem; }
</style>
