import { useEffect, useMemo, useState } from 'react'
import { CircleDot, Pencil, PhoneCall, Plus, Search, Users, X } from 'lucide-react'
import { api, type Extension, type RingGroup, type RingGroupPayload } from '../../lib/api'

const emptyRingGroup:RingGroupPayload={name:'',number:'7000',strategy:'SIMULTANEOUS',ring_timeout:30,status:'ACTIVE',member_extension_ids:[]}

export function RingGroupsPage(){
  const [groups,setGroups]=useState<RingGroup[]>([])
  const [extensions,setExtensions]=useState<Extension[]>([])
  const [loading,setLoading]=useState(true)
  const [error,setError]=useState('')
  const [search,setSearch]=useState('')
  const [editing,setEditing]=useState<RingGroup|null|undefined>(undefined)
  useEffect(()=>{void load()},[])
  async function load(){setLoading(true);setError('');try{const [groupResult,extensionResult]=await Promise.all([api.ringGroups(),api.extensions()]);setGroups(groupResult.data);setExtensions(extensionResult.data)}catch(err){setError(err instanceof Error?err.message:'Unable to load ring groups')}finally{setLoading(false)}}
  const filtered=useMemo(()=>groups.filter(group=>`${group.number} ${group.name}`.toLowerCase().includes(search.toLowerCase())),[groups,search])
  return <div className="generic-page provisioning-page">
    <div className="generic-toolbar"><label className="search"><Search size={17}/><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search ring groups..."/></label><button className="primary-button" onClick={()=>setEditing(null)}><Plus size={17}/> Add ring group</button></div>
    {error&&<div className="page-alert error">{error}</div>}
    <section className="card data-table routing-table"><div className="table-head"><span>Ring group</span><span>Status</span><span>Strategy</span><span>Members</span><span>Timeout</span><span>Actions</span></div>
      {loading?<div className="empty-state">Loading ring groups...</div>:filtered.length===0?<div className="empty-state"><Users size={26}/><strong>No ring groups configured</strong><span>Create a group to ring multiple extensions from one number.</span></div>:filtered.map(group=><div className="table-row" key={group.id}>
        <span><i className="table-icon"><PhoneCall size={16}/></i><strong>{group.name}</strong><small>Dial {group.number}</small></span>
        <span><b className={group.status==='ACTIVE'?'badge':'badge paused'}><CircleDot size={11}/>{group.status==='ACTIVE'?'Active':'Inactive'}</b></span>
        <span>{group.strategy==='SIMULTANEOUS'?'Ring together':'Ring in order'}</span>
        <span className="stacked-cell"><strong>{group.members.length} extension{group.members.length===1?'':'s'}</strong><small>{group.members.map(member=>member.extension.extension_number).join(', ')}</small></span>
        <span>{group.ring_timeout} seconds</span>
        <span><button className="table-action" onClick={()=>setEditing(group)}><Pencil size={13}/> Edit</button></span>
      </div>)}
    </section>
    {editing!==undefined&&<RingGroupModal group={editing} extensions={extensions} onClose={()=>setEditing(undefined)} onSaved={group=>{setGroups(current=>upsert(current,group));setEditing(undefined)}}/>}
  </div>
}

function RingGroupModal({group,extensions,onClose,onSaved}:{group:RingGroup|null;extensions:Extension[];onClose:()=>void;onSaved:(group:RingGroup)=>void}){
  const [form,setForm]=useState<RingGroupPayload>(()=>group?{name:group.name,number:group.number,strategy:group.strategy,ring_timeout:group.ring_timeout,status:group.status,member_extension_ids:group.members.map(member=>member.extension.id)}:{...emptyRingGroup})
  const [saving,setSaving]=useState(false)
  const [error,setError]=useState('')
  const eligible=extensions.filter(extension=>extension.status==='ACTIVE')
  function toggle(extensionId:string){setForm(current=>({...current,member_extension_ids:current.member_extension_ids.includes(extensionId)?current.member_extension_ids.filter(id=>id!==extensionId):[...current.member_extension_ids,extensionId]}))}
  async function submit(event:React.FormEvent){event.preventDefault();setSaving(true);setError('');try{const result=group?await api.updateRingGroup(group.id,form):await api.createRingGroup(form);onSaved(result.data)}catch(err){setError(err instanceof Error?err.message:`Unable to ${group?'update':'create'} ring group`)}finally{setSaving(false)}}
  return <div className="modal-backdrop" role="presentation"><section className="modal-card compact-modal" role="dialog" aria-modal="true" aria-labelledby="ring-group-modal-title">
    <div className="modal-header"><div><span className="eyebrow">CALL DISTRIBUTION</span><h2 id="ring-group-modal-title">{group?'Edit ring group':'Add ring group'}</h2><p>Route one dial number to several tenant extensions.</p></div><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20}/></button></div>
    <form onSubmit={submit}>{error&&<div className="page-alert error">{error}</div>}<fieldset><legend>Ring group settings</legend><div className="form-grid">
      <label>Group name<input required value={form.name} onChange={event=>setForm({...form,name:event.target.value})} placeholder="Reception"/></label>
      <label>Dial number<input required pattern="\d{2,16}" value={form.number} onChange={event=>setForm({...form,number:event.target.value})}/></label>
      <label>Ring strategy<select value={form.strategy} onChange={event=>setForm({...form,strategy:event.target.value as RingGroupPayload['strategy']})}><option value="SIMULTANEOUS">Ring all together</option><option value="SEQUENTIAL">Ring in order</option></select></label>
      <label>Ring timeout<input required type="number" min="5" max="120" value={form.ring_timeout} onChange={event=>setForm({...form,ring_timeout:Number(event.target.value)})}/></label>
      <label>Status<select value={form.status} onChange={event=>setForm({...form,status:event.target.value as RingGroupPayload['status']})}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></label>
    </div></fieldset><fieldset><legend>Member extensions</legend><div className="member-picker">{eligible.length===0?<div className="member-empty"><Users size={18}/> Create active extensions first.</div>:eligible.map(extension=><label key={extension.id} className={form.member_extension_ids.includes(extension.id)?'selected':''}><input type="checkbox" checked={form.member_extension_ids.includes(extension.id)} onChange={()=>toggle(extension.id)}/><span><strong>{extension.extension_number} · {extension.caller_id_name}</strong><small>{extension.user?.name??'Unassigned extension'}</small></span></label>)}</div></fieldset>
    <div className="modal-actions"><button type="button" className="secondary-button" onClick={onClose}>Cancel</button><button className="primary-button" disabled={saving||form.member_extension_ids.length===0}>{saving?'Saving...':group?'Save changes':'Create ring group'}</button></div></form>
  </section></div>
}
function upsert(items:RingGroup[],item:RingGroup){const next=items.some(current=>current.id===item.id)?items.map(current=>current.id===item.id?item:current):[...items,item];return next.sort((a,b)=>a.number.localeCompare(b.number,undefined,{numeric:true}))}
