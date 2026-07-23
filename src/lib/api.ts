const API_URL = import.meta.env.VITE_API_URL ?? ''

export type ApiUser = { id: string; tenant_id: string | null; name: string; email: string; role: 'SUPER_ADMIN'|'TENANT_ADMIN'|'AGENT' }
export type TenantAdmin = { id: string; tenant_id: string; name: string; email: string; status: 'ACTIVE'|'INACTIVE' }
export type Tenant = {
  id: string
  name: string
  code: string
  sip_domain: string
  status: 'ACTIVE'|'SUSPENDED'
  timezone: string
  extension_start: number
  extension_end: number
  max_extensions: number
  max_agents: number
  max_queues: number
  max_concurrent_calls: number
  recording_retention_days: number
  users_count: number
  users: TenantAdmin[]
  created_at: string
}
export type TenantPayload = {
  name: string
  code: string
  sip_domain: string
  timezone: string
  extension_start: number
  extension_end: number
  max_extensions: number
  max_agents: number
  max_queues: number
  max_concurrent_calls: number
  recording_retention_days: number
  features: string[]
  admin: { name: string; email: string; password: string; password_confirmation: string }
}
export type DashboardSummary = { tenants: number; extensions: number; agents: number; active_calls: number; calls_today: number }
export type Extension = {
  id: string
  user_id: string | null
  extension_number: string
  sip_username: string
  caller_id_name: string
  caller_id_number: string
  status: 'ACTIVE'|'INACTIVE'
  webrtc_enabled: boolean
  voicemail_enabled: boolean
  dnd_enabled: boolean
  ring_timeout: number
  user?: Pick<ApiUser,'id'|'name'|'email'> | null
  created_at: string
}
export type ExtensionPayload = {
  extension_number: number
  sip_username: string
  sip_password: string
  caller_id_name: string
  caller_id_number: string
  webrtc_enabled: boolean
  voicemail_enabled: boolean
  ring_timeout: number
}
export type ExtensionUpdatePayload = ExtensionPayload & { status: 'ACTIVE'|'INACTIVE' }
export type Agent = {
  id: string
  employee_code: string
  display_name: string
  status: 'ACTIVE'|'INACTIVE'
  user: ApiUser & { status: 'ACTIVE'|'INACTIVE'; extensions: Extension[] }
  created_at: string
}
export type AgentPayload = {
  name: string
  display_name: string
  employee_code: string
  email: string
  password: string
  password_confirmation: string
  extension_id: string | null
}
type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number }

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
  if (!response.ok) {
    const body = await response.json().catch(() => ({})) as { message?: string; errors?: Record<string,string[]> }
    const validationMessage = body.errors ? Object.values(body.errors).flat()[0] : undefined
    throw new Error(validationMessage ?? body.message ?? `Request failed (${response.status})`)
  }
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
  dashboard: () => request<{data: DashboardSummary}>('/api/v1/dashboard'),
  tenants: () => request<Paginated<Tenant>>('/api/v1/tenants'),
  createTenant: (payload: TenantPayload) => request<{data: Tenant}>('/api/v1/tenants', { method: 'POST', body: JSON.stringify(payload) }),
  updateTenant: (id: string, payload: Pick<Tenant,'status'>) => request<{data: Tenant}>(`/api/v1/tenants/${id}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  extensions: () => request<Paginated<Extension>>('/api/v1/extensions'),
  createExtension: (payload: ExtensionPayload) => request<{data: Extension}>('/api/v1/extensions', { method: 'POST', body: JSON.stringify(payload) }),
  updateExtension: (id: string, payload: ExtensionUpdatePayload) => request<{data: Extension}>(`/api/v1/extensions/${id}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  agents: () => request<Paginated<Agent>>('/api/v1/agents'),
  createAgent: (payload: AgentPayload) => request<{data: Agent}>('/api/v1/agents', { method: 'POST', body: JSON.stringify(payload) }),
}
