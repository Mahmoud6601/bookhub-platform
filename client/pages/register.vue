<template>
  <div class="auth-card card">
    <h1>Register</h1>
    <form class="form-grid" @submit.prevent="register">
      <div class="form-field">
        <label for="name">Name</label>
        <input id="name" v-model="name" required autocomplete="name" />
      </div>
      <div class="form-field">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" required autocomplete="email" />
      </div>
      <div class="form-field">
        <label for="password">Password (min 8 characters)</label>
        <input id="password" v-model="password" type="password" required minlength="8" autocomplete="new-password" />
      </div>
      <div class="form-field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" v-model="passwordConfirmation" type="password" required minlength="8" autocomplete="new-password" />
      </div>
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <button type="submit" class="btn btn-primary" :disabled="submitting">
        {{ submitting ? 'Creating account...' : 'Register' }}
      </button>
    </form>
    <p class="footer-note">Already have an account? <NuxtLink to="/login">Log in</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
const { setSession } = useAuth()
const { apiFetch, extractErrorMessage } = useApi()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const submitting = ref(false)

async function register() {
  error.value = ''
  submitting.value = true
  try {
    const res = await apiFetch('/api/register', {
      method: 'POST',
      body: {
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      },
    })
    setSession(res.token, res.user)
    await navigateTo('/')
  } catch (err) {
    error.value = extractErrorMessage(err, 'Registration failed.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.auth-card { max-width: 440px; margin: 1.5rem auto; }
.footer-note { margin-top: 1rem; font-size: 0.9rem; }
</style>
