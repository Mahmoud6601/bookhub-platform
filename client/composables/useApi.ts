export function extractErrorMessage(err: any, fallback = 'Request failed.'): string {
  const data = err?.data ?? err?.response?._data
  if (data?.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0]
    if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
      return String(data.errors[firstKey][0])
    }
  }
  if (typeof data?.message === 'string' && data.message.trim()) {
    return data.message
  }
  if (typeof err?.message === 'string' && err.message.trim()) {
    return err.message
  }
  return fallback
}

export const useApi = () => {
  const config = useRuntimeConfig()
  const { token, clearSession } = useAuth()

  async function apiFetch<T = any>(path: string, options: Record<string, any> = {}): Promise<T> {
    const baseURL = process.server
      ? config.apiInternalBase
      : config.public.apiBase || undefined

    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers || {}),
    }

    if (token.value && !headers.Authorization) {
      headers.Authorization = `Bearer ${token.value}`
    }

    try {
      return await $fetch<T>(path, {
        ...options,
        baseURL,
        headers,
      })
    } catch (err: any) {
      const status = err?.response?.status ?? err?.statusCode
      if (status === 401 && token.value && process.client) {
        clearSession()
      }
      throw err
    }
  }

  return {
    apiFetch,
    extractErrorMessage,
  }
}
