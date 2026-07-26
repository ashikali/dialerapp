import { useEffect, useMemo, useState } from 'react'
import { CircleDot, Plus, Search, UsersRound, X } from 'lucide-react'
import { api, type Agent, type AgentPayload, type Extension } from '../../lib/api'

const emptyForm: AgentPayload = { name:'',display_name:'',employee_code:'',username:'',email:'',password:'',password_confirmation:'',extension_id:null }

export function AgentsPage() {
  const [agents,setAgents]=useState<Agent[]>([])
  const [extensions,setExtensions]=useState<Extension[]>([])
  const [loading,setLoading]=useState(true)
  const [error,setError]=useState('')
  const [search,setSearch]=useState('')
  const [showForm,setShowForm]=useState(false)
  useEffect(()=>{ void load() },[])
  async function load() {
    setLoading(true);setError('')
    try { const [agentResult,extensionResult]=await Promise.all([api.agents(),api.extensions()]);setAgents(agentResult.data);setExtensions(extensionResult.data) }
    catch (err) { setError(err instanceof Error ? err.message : 'Unable to load agents') }
    finally { setLoading(false) }
  }
  const filtered=useMemo(()=>agents.filter(item=>`${item.display_name} ${item.employee_code} ${item.user.username} ${item.user.email}`.toLowerCase().includes(search.toLowerCase())),[agents,search])
  return <div className="generic-page provisioning-page">
    <div className="generic-toolbar"><label className="search"><Search size={17}/><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search agents..."/></label><button className="primary-button" onClick={()=>setShowForm(true)}><Plus size={17}/> Add agent</button></div>
    {error && <div className="page-alert error">{error}</div>}
    <section className="card data-table agents-table"><div className="table-head"><span>Agent</span><span>Status</span><span>Login</span><span>Extension</span><span>Employee code</span></div>
      {loading ? <div className="empty-state">Loading agents...</div> : filtered.length === 0 ? <div className="empty-state"><UsersRound size={26}/><strong>No agents provisioned</strong><span>Create an agent login and assign an available extension.</span></div> : filtered.map(item=><div className="table-row" key={item.id}>
        <span><i className="table-icon"><UsersRound size={16}/></i><strong>{item.display_name}</strong><small>{item.user.name}</small></span>
        <span><b className="badge"><CircleDot size={11}/>{item.status === 'ACTIVE' ? 'Active' : 'Inactive'}</b></span>
        <span className="stacked-cell"><strong>{item.user.username}</strong><small>{item.user.email}</small></span>
        <span className="stacked-cell"><strong>{item.user.extensions?.[0]?.extension_number ?? 'Unassigned'}</strong><small>{item.user.extensions?.[0] ? 'Ready for device setup' : 'Assign later'}</small></span>
        <span>{item.employee_code}</span>
      </div>)}
    </section>
    {showForm && <AgentModal extensions={extensions.filter(item=>!item.user_id)} onClose={()=>setShowForm(false)} onCreated={item=>{setAgents(current=>[...current,item].sort((a,b)=>a.display_name.localeCompare(b.display_name)));setExtensions(current=>current.map(ext=>ext.id===item.user.extensions?.[0]?.id?{...ext,user_id:item.user.id}:ext));setShowForm(false)}}/>}
  </div>
}

function AgentModal({extensions,onClose,onCreated}:{extensions:Extension[];onClose:()=>void;onCreated:(item:Agent)=>void}) {
  const [form,setForm]=useState(emptyForm)
  const [saving,setSaving]=useState(false)
  const [error,setError]=useState('')
  async function submit(event:React.FormEvent) {
    event.preventDefault();setSaving(true);setError('')
    try { onCreated((await api.createAgent(form)).data) }
    catch (err) { setError(err instanceof Error ? err.message : 'Unable to create agent') }
    finally { setSaving(false) }
  }
  return <div className="modal-backdrop" role="presentation"><section className="modal-card compact-modal" role="dialog" aria-modal="true" aria-labelledby="agent-modal-title">
    <div className="modal-header"><div><span className="eyebrow">TENANT PROVISIONING</span><h2 id="agent-modal-title">Add agent</h2><p>Create the agent portal login and optionally assign an extension.</p></div><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20}/></button></div>
    <form onSubmit={submit}>{error && <div className="page-alert error">{error}</div>}<fieldset><legend>Agent identity</legend><div className="form-grid">
      <label>Full name<input required value={form.name} onChange={event=>setForm({...form,name:event.target.value,display_name:form.display_name||event.target.value})} placeholder="John Smith"/></label>
      <label>Display name<input required value={form.display_name} onChange={event=>setForm({...form,display_name:event.target.value})}/></label>
      <label>Employee code<input required value={form.employee_code} onChange={event=>setForm({...form,employee_code:event.target.value})} placeholder="AGT-1001"/></label>
      <label>Extension<select value={form.extension_id ?? ''} onChange={event=>setForm({...form,extension_id:event.target.value||null})}><option value="">Assign later</option>{extensions.map(item=><option value={item.id} key={item.id}>{item.extension_number} — {item.caller_id_name}</option>)}</select></label>
      <label>Login username<input required minLength={2} maxLength={64} pattern="[A-Za-z0-9][A-Za-z0-9._-]*" value={form.username} onChange={event=>setForm({...form,username:event.target.value.toLowerCase()})} placeholder="john"/></label>
      <label>Contact email<input required type="email" value={form.email} onChange={event=>setForm({...form,email:event.target.value})} placeholder="john@company.com"/></label>
      <label>Password<input required minLength={12} type="password" value={form.password} onChange={event=>setForm({...form,password:event.target.value})} placeholder="12+ characters"/></label>
      <label>Confirm password<input required minLength={12} type="password" value={form.password_confirmation} onChange={event=>setForm({...form,password_confirmation:event.target.value})}/></label>
    </div></fieldset><div className="modal-actions"><button type="button" className="secondary-button" onClick={onClose}>Cancel</button><button className="primary-button" disabled={saving}>{saving ? 'Creating...' : 'Create agent'}</button></div></form>
  </section></div>
}
