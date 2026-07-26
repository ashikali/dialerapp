import { cloneElement, isValidElement, useEffect, useState, type ReactElement } from 'react'
import type { Role } from '../App'
import { api, type ApiUser } from '../lib/api'

const roleMap: Record<ApiUser['role'],Role> = { SUPER_ADMIN:'super', TENANT_ADMIN:'tenant', AGENT:'agent' }

export function AuthGate({children}:{children:ReactElement<{lockedRole?:Role;onLogout?:()=>void}>}) {
  const demo = import.meta.env.VITE_DEMO_MODE !== 'false'
  const [user,setUser] = useState<ApiUser|null>(null), [loading,setLoading]=useState(!demo), [error,setError]=useState('')
  useEffect(()=>{ if(!demo) api.me().then(r=>setUser(r.user)).catch(()=>{}).finally(()=>setLoading(false)) },[demo])
  if(demo) return children
  if(loading) return <div className="auth-screen"><div className="auth-card">Loading PBXPro…</div></div>
  if(user && isValidElement(children)) return cloneElement(children,{lockedRole:roleMap[user.role],onLogout:async()=>{try{await api.csrf();await api.logout()}finally{setUser(null)}}})
  return <div className="auth-screen"><form className="auth-card" onSubmit={async e=>{e.preventDefault();setError('');const form=new FormData(e.currentTarget);try{await api.csrf();const r=await api.login(String(form.get('login')),String(form.get('password')));setUser(r.user)}catch(err){setError(err instanceof Error?err.message:'Login failed')}}}><div className="auth-brand">PBX<span>Pro</span></div><h1>Welcome back</h1><p>Sign in to your contact-center workspace</p><label>Username or email<input name="login" type="text" autoComplete="username" placeholder="admin" required/></label><label>Password<input name="password" type="password" autoComplete="current-password" required/></label>{error&&<div className="auth-error">{error}</div>}<button type="submit">Sign in</button></form></div>
}
