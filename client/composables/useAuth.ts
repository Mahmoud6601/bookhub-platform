export interface AuthUser {
  id: number
  name: string
  email: string
  is_admin: boolean
}

let initPromise: Promise<void> | null = null

export const useAuth = () => {
  const token = useState<string | null>('bookhub_token', () => null)
  const user = useState<AuthUser | null>('bookhub_user', () => null)
  const authReady = useState<boolean>('bookhub_auth_ready', () => false)
  const config = useRuntimeConfig()

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const isAdmin = computed(() => Boolean(user.value?.is_admin))

  function clearSession() {
    token.value = null
    user.value = null
    if (process.client) {
      localStorage.removeItem('bookhub_token')
    }
  }

  function setSession(newToken: string, newUser: AuthUser) {
    token.value = newToken
    user.value = newUser
    authReady.value = true
    if (process.client) {
      localStorage.setItem('bookhub_token', newToken)
    }
  }

  async function initAuth() {
    if (!process.client) return
    if (authReady.value) return
    if (initPromise) return initPromise

    initPromise = (async () => {
      const storedToken = localStorage.getItem('bookhub_token')
      if (!storedToken) {
        clearSession()
        authReady.value = true
        return
      }

      token.value = storedToken
      try {
        const baseURL = config.public.apiBase || undefined
        const me = await $fetch<AuthUser>('/api/me', {
          baseURL,
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${storedToken}`,
          },
        })
        user.value = me
      } catch {
        clearSession()
      } finally {
        authReady.value = true
        initPromise = null
      }
    })()

    return initPromise
  }

  async function logout() {
    const currentToken = token.value
    try {
      if (currentToken) {
        const baseURL = config.public.apiBase || undefined
        await $fetch('/api/logout', {
          baseURL,
          method: 'POST',
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${currentToken}`,
          },
        })
      }
    } catch {
      // Clear local session regardless of server response
    } finally {
      clearSession()
      await navigateTo('/')
    }
  }

  return {
    token,
    user,
    authReady,
    isAuthenticated,
    isAdmin,
    initAuth,
    setSession,
    clearSession,
    logout,
  }
}
