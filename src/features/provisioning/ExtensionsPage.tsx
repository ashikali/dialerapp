import { useEffect, useMemo, useState } from 'react'
import { CircleDot, Copy, KeyRound, Pencil, Phone, Plus, RefreshCw, Search, X } from 'lucide-react'
import { api, type Extension, type ExtensionPayload, type ExtensionUpdatePayload } from '../../lib/api'

const passwordAlphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%_-'
function generateSipPassword(length=24): string {
  const values=new Uint32Array(length)
  crypto.getRandomValues(values)
  return Array.from(values,value=>passwordAlphabet[value%passwordAlphabet.length]).join('')
}
function newExtensionForm(): ExtensionUpdatePayload {
  return { extension_number:1001,sip_username:'1001',sip_password:generateSipPassword(),caller_id_name:'',caller_id_number:'1001',status:'ACTIVE',webrtc_enabled:true,voicemail_enabled:false,ring_timeout:30 }
}

export function ExtensionsPage() {
  const [extensions,setExtensions]=useState<Extension[]>([])
  const [loading,setLoading]=useState(true)
  const [error,setError]=useState('')
  const [search,setSearch]=useState('')
  const [editing,setEditing]=useState<Extension|null|undefined>(undefined)

  useEffect(()=>{ void load() },[])
  async function load() {
    setLoading(true); setError('')
    try { setExtensions((await api.extensions()).data) }
    catch (err) { setError(err instanceof Error ? err.message : 'Unable to load extensions') }
    finally { setLoading(false) }
  }
  const filtered=useMemo(()=>extensions.filter(item=>`${item.extension_number} ${item.caller_id_name} ${item.user?.name ?? ''}`.toLowerCase().includes(search.toLowerCase())),[extensions,search])

  return <div className="generic-page provisioning-page">
    <div className="generic-toolbar"><label className="search"><Search size={17}/><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search extensions..."/></label><button className="primary-button" onClick={()=>setEditing(null)}><Plus size={17}/> Add extension</button></div>
    {error && <div className="page-alert error">{error}</div>}
    <section className="card data-table extensions-table"><div className="table-head"><span>Extension</span><span>Status</span><span>Assigned user</span><span>Features</span><span>Ring timeout</span><span>Actions</span></div>
      {loading ? <div className="empty-state">Loading extensions...</div> : filtered.length === 0 ? <div className="empty-state"><Phone size={26}/><strong>No extensions provisioned</strong><span>Create extension 1001 to begin SIP registration.</span></div> : filtered.map(item=><div className="table-row" key={item.id}>
        <span><i className="table-icon"><Phone size={16}/></i><strong>{item.extension_number}</strong><small>{item.caller_id_name} · SIP {item.sip_username}</small></span>
        <span><b className={item.status==='ACTIVE'?'badge':'badge paused'}><CircleDot size={11}/>{item.status === 'ACTIVE' ? 'Active' : 'Inactive'}</b></span>
        <span className="stacked-cell"><strong>{item.user?.name ?? 'Unassigned'}</strong><small>{item.user?.email ?? 'Available for an agent'}</small></span>
        <span className="feature-pills"><b>{item.webrtc_enabled ? 'WebRTC' : 'SIP'}</b>{item.voicemail_enabled && <b>Voicemail</b>}</span>
        <span>{item.ring_timeout} seconds</span>
        <span><button className="table-action" onClick={()=>setEditing(item)}><Pencil size={13}/> Edit</button></span>
      </div>)}
    </section>
    {editing !== undefined && <ExtensionModal extension={editing} onClose={()=>setEditing(undefined)} onSaved={item=>{setExtensions(current=>{const exists=current.some(entry=>entry.id===item.id);return (exists?current.map(entry=>entry.id===item.id?item:entry):[...current,item]).sort((a,b)=>a.extension_number.localeCompare(b.extension_number,undefined,{numeric:true}))});setEditing(undefined)}}/>}
  </div>
}

function ExtensionModal({extension,onClose,onSaved}:{extension:Extension|null;onClose:()=>void;onSaved:(item:Extension)=>void}) {
  const [form,setForm]=useState<ExtensionUpdatePayload>(()=>extension?{
    extension_number:Number(extension.extension_number),sip_username:extension.sip_username,sip_password:'',caller_id_name:extension.caller_id_name,caller_id_number:extension.caller_id_number,status:extension.status,
    webrtc_enabled:extension.webrtc_enabled,voicemail_enabled:extension.voicemail_enabled,ring_timeout:extension.ring_timeout,
  }:newExtensionForm())
  const [saving,setSaving]=useState(false)
  const [copied,setCopied]=useState(false)
  const [error,setError]=useState('')
  function setNumber(value:number) { setForm(current=>({...current,extension_number:value,sip_username:String(value),caller_id_number:String(value)})) }
  function regenerate() { setForm(current=>({...current,sip_password:generateSipPassword()}));setCopied(false) }
  async function copyPassword() { if(!form.sip_password)return;await navigator.clipboard.writeText(form.sip_password);setCopied(true) }
  async function submit(event:React.FormEvent) {
    event.preventDefault(); setSaving(true); setError('')
    try {
      const result=extension ? await api.updateExtension(extension.id,form) : await api.createExtension(form as ExtensionPayload)
      onSaved(result.data)
    } catch (err) { setError(err instanceof Error ? err.message : `Unable to ${extension?'update':'create'} extension`) }
    finally { setSaving(false) }
  }
  return <div className="modal-backdrop" role="presentation"><section className="modal-card compact-modal" role="dialog" aria-modal="true" aria-labelledby="extension-modal-title">
    <div className="modal-header"><div><span className="eyebrow">TENANT PROVISIONING</span><h2 id="extension-modal-title">{extension?'Edit extension':'Add extension'}</h2><p>{extension?'Update routing and device settings. The current SIP password remains unchanged unless replaced.':'Create encrypted SIP credentials for a phone or agent.'}</p></div><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20}/></button></div>
    <form onSubmit={submit}>{error && <div className="page-alert error">{error}</div>}<fieldset><legend>Extension identity</legend><div className="form-grid">
      <label>Extension number<input required min="1" type="number" value={form.extension_number} onChange={event=>setNumber(Number(event.target.value))}/></label>
      <label>Caller ID name<input required value={form.caller_id_name} onChange={event=>setForm({...form,caller_id_name:event.target.value})} placeholder="John Smith"/></label>
      <label>SIP username<input required value={form.sip_username} onChange={event=>setForm({...form,sip_username:event.target.value})}/></label>
      <label>Caller ID number<input required value={form.caller_id_number} onChange={event=>setForm({...form,caller_id_number:event.target.value})}/></label>
      <label className="wide">SIP password<div className="generated-secret"><KeyRound size={15}/><input aria-label="SIP password" required={!extension} minLength={form.sip_password?16:undefined} type="text" value={form.sip_password} onChange={event=>{setForm({...form,sip_password:event.target.value});setCopied(false)}} placeholder={extension?'Leave blank to keep the current password':'Generated secure password'}/><button type="button" onClick={regenerate} title="Generate new password" aria-label="Generate new SIP password"><RefreshCw size={14}/></button><button type="button" onClick={()=>void copyPassword()} disabled={!form.sip_password} aria-label="Copy SIP password"><Copy size={14}/></button></div><small className="field-help">{extension&&!form.sip_password?'Existing password will not change.':copied?'Password copied. Store it securely before saving.':'Automatically generated. Copy and store it securely before saving.'}</small></label>
      <label>Status<select value={form.status} onChange={event=>setForm({...form,status:event.target.value as ExtensionUpdatePayload['status']})}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></label>
      <label>Ring timeout<input required min="5" max="120" type="number" value={form.ring_timeout} onChange={event=>setForm({...form,ring_timeout:Number(event.target.value)})}/></label>
      <div className="check-options wide"><label><input type="checkbox" checked={form.webrtc_enabled} onChange={event=>setForm({...form,webrtc_enabled:event.target.checked})}/> WebRTC enabled</label><label><input type="checkbox" checked={form.voicemail_enabled} onChange={event=>setForm({...form,voicemail_enabled:event.target.checked})}/> Voicemail enabled</label></div>
    </div></fieldset><div className="modal-actions"><button type="button" className="secondary-button" onClick={onClose}>Cancel</button><button className="primary-button" disabled={saving}>{saving ? 'Saving...' : extension?'Save changes':'Create extension'}</button></div></form>
  </section></div>
}
