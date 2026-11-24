// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  devtools: { enabled: process.env.NODE_ENV !== 'production' },
  runtimeConfig: {
    apiInternalBase:
      process.env.NUXT_API_INTERNAL_BASE ||
      process.env.NUXT_PUBLIC_API_BASE ||
      'http://localhost:8000',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '',
    },
  },
})
