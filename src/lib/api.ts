const API_URL = import.meta.env.VITE_API_URL ?? ''

export type ApiUser = { id: string; tenant_id: string | null; name: string; email: string; role: 'SUPER_ADMIN'|'TENANT_ADMIN'|'AGENT' }

function cookie(name: string): string | null {
  const prefix = `${encodeURIComponent(name)}=`
  const value = document.cookie.split('; ').find(item => item.startsWith(prefix))?.slice(prefix.length)
  return value ? decodeURIComponent(value) : null
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('Content-Type', 'application/json')

  const xsrfToken = cookie('XSRF-TOKEN')
  if (xsrfToken) headers.set('X-XSRF-TOKEN', xsrfToken)

  const response = await fetch(`${API_URL}${path}`, { ...init, credentials: 'include', headers })
  if (!response.ok) { const body = await response.json().catch(() => ({})); throw new Error(body.message ?? `Request failed (${response.status})`) }
  return response.json() as Promise<T>
}

export const api = {
  csrf: async () => {
    const response = await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error(`Unable to initialize secure session (${response.status})`)
  },
  me: () => request<{user: ApiUser}>('/api/v1/me'),
  login: (email: string, password: string) => request<{user: ApiUser}>('/api/v1/auth/login', { method: 'POST', body: JSON.stringify({email,password}) }),
  logout: () => request('/api/v1/auth/logout', { method: 'POST' }),
}
