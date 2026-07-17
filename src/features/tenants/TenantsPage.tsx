import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Building2, CircleDot, Plus, Search, X } from 'lucide-react'
import { api, type Tenant, type TenantPayload } from '../../lib/api'

const defaults: TenantPayload = {
  name: '', code: '', sip_domain: '', timezone: 'Asia/Kolkata', extension_start: 1000, extension_end: 1999,
  max_extensions: 100, max_agents: 50, max_queues: 10, max_concurrent_calls: 25,
  recording_retention_days: 90, features: [],
  admin: { name: '', email: '', password: '', password_confirmation: '' },
}

export function TenantsPage() {
  const [tenants,setTenants]=useState<Tenant[]>([])
  const [loading,setLoading]=useState(true)
  const [error,setError]=useState('')
  const [search,setSearch]=useState('')
  const [showForm,setShowForm]=useState(false)

  async function load() {
    setLoading(true); setError('')
    try { setTenants((await api.tenants()).data) }
    catch (err) { setError(err instanceof Error ? err.message : 'Unable to load tenants') }
    finally { setLoading(false) }
  }

  useEffect(()=>{ void load() },[])
  const filtered=useMemo(()=>tenants.filter(tenant=>`${tenant.name} ${tenant.code} ${tenant.sip_domain} ${tenant.users?.[0]?.email ?? ''}`.toLowerCase().includes(search.toLowerCase())),[tenants,search])

  async function toggleStatus(tenant: Tenant) {
    const action=tenant.status === 'ACTIVE' ? 'suspend' : 'reactivate'
    if (!window.confirm(`Are you sure you want to ${action} ${tenant.name}?`)) return
    setError('')
    try {
      await api.csrf()
      const {data}=await api.updateTenant(tenant.id,{status:tenant.status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE'})
      setTenants(current=>current.map(item=>item.id === tenant.id ? {...item,...data} : item))
    } catch (err) { setError(err instanceof Error ? err.message : 'Unable to update tenant') }
  }

  return <div className="generic-page tenant-page">
    <div className="generic-toolbar"><label className="search"><Search size={17}/><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search tenants..."/></label><button className="primary-button" onClick={()=>setShowForm(true)}><Plus size={17}/> Onboard tenant</button></div>
    {error && <div className="page-alert error">{error}</div>}
    <section className="card data-table tenant-table">
      <div className="table-head"><span>Tenant</span><span>Status</span><span>Administrator</span><span>Capacity</span><span>Action</span></div>
      {loading ? <div className="empty-state">Loading tenants...</div> : filtered.length === 0 ? <div className="empty-state"><Building2 size={26}/><strong>No tenants found</strong><span>Onboard your first tenant to begin.</span></div> : filtered.map((tenant,i)=><div className="table-row" key={tenant.id}>
        <span><i className={`table-icon t${i%5}`}><Building2 size={16}/></i><strong>{tenant.name}</strong><small>{tenant.sip_domain}</small></span>
        <span><b className={tenant.status === 'ACTIVE' ? 'badge' : 'badge paused'}><CircleDot size={11}/>{tenant.status === 'ACTIVE' ? 'Active' : 'Suspended'}</b></span>
        <span className="stacked-cell"><strong>{tenant.users?.[0]?.name ?? 'Not assigned'}</strong><small>{tenant.users?.[0]?.email ?? '—'}</small></span>
        <span className="stacked-cell"><strong>{tenant.max_agents} agents</strong><small>{tenant.extension_start}–{tenant.extension_end}</small></span>
        <span><button className={tenant.status === 'ACTIVE' ? 'table-action danger' : 'table-action'} onClick={()=>void toggleStatus(tenant)}>{tenant.status === 'ACTIVE' ? 'Suspend' : 'Reactivate'}</button></span>
      </div>)}
    </section>
    {showForm && <TenantOnboardingModal onClose={()=>setShowForm(false)} onCreated={tenant=>{setTenants(current=>[tenant,...current]);setShowForm(false)}}/>}
  </div>
}

function TenantOnboardingModal({onClose,onCreated}:{onClose:()=>void;onCreated:(tenant:Tenant)=>void}) {
  const [form,setForm]=useState<TenantPayload>(defaults)
  const [error,setError]=useState('')
  const [saving,setSaving]=useState(false)
  const setField=(field:keyof TenantPayload,value:string|number)=>setForm(current=>({...current,[field]:value}))
  const setAdmin=(field:keyof TenantPayload['admin'],value:string)=>setForm(current=>({...current,admin:{...current.admin,[field]:value}}))

  async function submit(event: FormEvent) {
    event.preventDefault(); setError(''); setSaving(true)
    try { await api.csrf(); onCreated((await api.createTenant(form)).data) }
    catch (err) { setError(err instanceof Error ? err.message : 'Unable to onboard tenant') }
    finally { setSaving(false) }
  }

  return <div className="modal-backdrop" role="presentation"><section className="modal-card" role="dialog" aria-modal="true" aria-labelledby="tenant-modal-title">
    <div className="modal-header"><div><span className="eyebrow">NEW CUSTOMER</span><h2 id="tenant-modal-title">Onboard tenant</h2><p>Create the tenant workspace and its first administrator.</p></div><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20}/></button></div>
    <form onSubmit={submit}>
      <fieldset><legend>Tenant details</legend><div className="form-grid">
        <label>Tenant name<input required value={form.name} onChange={event=>setField('name',event.target.value)}/></label>
        <label>Tenant code<input required pattern="[A-Za-z0-9_-]+" value={form.code} onChange={event=>setField('code',event.target.value.toLowerCase())} placeholder="abc-finance"/></label>
        <label className="wide">SIP domain<input required value={form.sip_domain} onChange={event=>setField('sip_domain',event.target.value.toLowerCase())} placeholder="abcfinance.pbxpro.test"/></label>
        <label>Timezone<input required value={form.timezone} onChange={event=>setField('timezone',event.target.value)}/></label>
        <label>Concurrent calls<input required min="1" type="number" value={form.max_concurrent_calls} onChange={event=>setField('max_concurrent_calls',Number(event.target.value))}/></label>
        <label>Extension start<input required min="1" type="number" value={form.extension_start} onChange={event=>setField('extension_start',Number(event.target.value))}/></label>
        <label>Extension end<input required min={form.extension_start+1} type="number" value={form.extension_end} onChange={event=>setField('extension_end',Number(event.target.value))}/></label>
        <label>Maximum extensions<input required min="1" type="number" value={form.max_extensions} onChange={event=>setField('max_extensions',Number(event.target.value))}/></label>
        <label>Maximum agents<input required min="1" type="number" value={form.max_agents} onChange={event=>setField('max_agents',Number(event.target.value))}/></label>
        <label>Maximum queues<input required min="1" type="number" value={form.max_queues} onChange={event=>setField('max_queues',Number(event.target.value))}/></label>
        <label>Recording retention (days)<input required min="1" max="3650" type="number" value={form.recording_retention_days} onChange={event=>setField('recording_retention_days',Number(event.target.value))}/></label>
      </div></fieldset>
      <fieldset><legend>Initial Tenant Admin</legend><div className="form-grid">
        <label>Administrator name<input required value={form.admin.name} onChange={event=>setAdmin('name',event.target.value)}/></label>
        <label>Email address<input required type="email" autoComplete="off" value={form.admin.email} onChange={event=>setAdmin('email',event.target.value)}/></label>
        <label>Password<input required minLength={12} type="password" autoComplete="new-password" value={form.admin.password} onChange={event=>setAdmin('password',event.target.value)}/></label>
        <label>Confirm password<input required minLength={12} type="password" autoComplete="new-password" value={form.admin.password_confirmation} onChange={event=>setAdmin('password_confirmation',event.target.value)}/></label>
      </div></fieldset>
      {error && <div className="page-alert error">{error}</div>}
      <div className="modal-actions"><button type="button" className="secondary-button" onClick={onClose}>Cancel</button><button className="primary-button" disabled={saving}>{saving ? 'Creating tenant...' : 'Create tenant & admin'}</button></div>
    </form>
  </section></div>
}
