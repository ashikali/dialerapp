import { useEffect, useMemo, useState } from 'react'
import { CircleDot, Headphones, Pencil, Plus, Search, UsersRound, X } from 'lucide-react'
import { api, type Agent, type PbxQueue, type QueuePayload } from '../../lib/api'

const emptyQueue: QueuePayload={
  name:'',number:'6000',strategy:'longest-idle-agent',wrap_up_seconds:30,max_wait_seconds:300,
  max_size:100,music_on_hold:'local_stream://moh',status:'ACTIVE',member_agent_ids:[],
}

export function QueuesPage() {
  const [queues,setQueues]=useState<PbxQueue[]>([])
  const [agents,setAgents]=useState<Agent[]>([])
  const [loading,setLoading]=useState(true)
  const [error,setError]=useState('')
  const [search,setSearch]=useState('')
  const [editing,setEditing]=useState<PbxQueue|null|undefined>(undefined)

  useEffect(()=>{void load()},[])
  async function load(){
    setLoading(true);setError('')
    try{
      const [queueResult,agentResult]=await Promise.all([api.queues(),api.agents()])
      setQueues(queueResult.data);setAgents(agentResult.data)
    }catch(err){setError(err instanceof Error?err.message:'Unable to load queues')}
    finally{setLoading(false)}
  }
  const filtered=useMemo(()=>queues.filter(queue=>`${queue.number} ${queue.name}`.toLowerCase().includes(search.toLowerCase())),[queues,search])
  return <div className="generic-page provisioning-page">
    <div className="generic-toolbar"><label className="search"><Search size={17}/><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="Search queues..."/></label><button className="primary-button" onClick={()=>setEditing(null)}><Plus size={17}/> Add queue</button></div>
    {error&&<div className="page-alert error">{error}</div>}
    <section className="card data-table routing-table"><div className="table-head"><span>Queue</span><span>Status</span><span>Strategy</span><span>Agents</span><span>Wait / Wrap-up</span><span>Actions</span></div>
      {loading?<div className="empty-state">Loading queues...</div>:filtered.length===0?<div className="empty-state"><Headphones size={26}/><strong>No queues configured</strong><span>Create a queue to begin distributing waiting callers.</span></div>:filtered.map(queue=><div className="table-row" key={queue.id}>
        <span><i className="table-icon"><Headphones size={16}/></i><strong>{queue.name}</strong><small>Dial {queue.number}</small></span>
        <span><b className={queue.status==='ACTIVE'?'badge':'badge paused'}><CircleDot size={11}/>{queue.status==='ACTIVE'?'Active':'Inactive'}</b></span>
        <span className="stacked-cell"><strong>{strategyLabel(queue.strategy)}</strong><small>{queue.music_on_hold}</small></span>
        <span className="stacked-cell"><strong>{queue.members.length} agent{queue.members.length===1?'':'s'}</strong><small>{queue.members.map(member=>member.agent.display_name).join(', ')||'No members'}</small></span>
        <span className="stacked-cell"><strong>{queue.max_wait_seconds}s max</strong><small>{queue.wrap_up_seconds}s wrap-up</small></span>
        <span><button className="table-action" onClick={()=>setEditing(queue)}><Pencil size={13}/> Edit</button></span>
      </div>)}
    </section>
    {editing!==undefined&&<QueueModal queue={editing} agents={agents} onClose={()=>setEditing(undefined)} onSaved={queue=>{setQueues(current=>upsert(current,queue));setEditing(undefined)}}/>}
  </div>
}

function QueueModal({queue,agents,onClose,onSaved}:{queue:PbxQueue|null;agents:Agent[];onClose:()=>void;onSaved:(queue:PbxQueue)=>void}) {
  const [form,setForm]=useState<QueuePayload>(()=>queue?{
    name:queue.name,number:queue.number,strategy:queue.strategy,wrap_up_seconds:queue.wrap_up_seconds,max_wait_seconds:queue.max_wait_seconds,
    max_size:queue.max_size,music_on_hold:queue.music_on_hold,status:queue.status,member_agent_ids:queue.members.map(member=>member.agent.id),
  }:{...emptyQueue})
  const [saving,setSaving]=useState(false)
  const [error,setError]=useState('')
  const eligible=agents.filter(agent=>agent.status==='ACTIVE'&&agent.user.extensions?.some(extension=>extension.status==='ACTIVE'))
  function toggle(agentId:string){setForm(current=>({...current,member_agent_ids:current.member_agent_ids.includes(agentId)?current.member_agent_ids.filter(id=>id!==agentId):[...current.member_agent_ids,agentId]}))}
  async function submit(event:React.FormEvent){
    event.preventDefault();setSaving(true);setError('')
    try{const result=queue?await api.updateQueue(queue.id,form):await api.createQueue(form);onSaved(result.data)}
    catch(err){setError(err instanceof Error?err.message:`Unable to ${queue?'update':'create'} queue`)}
    finally{setSaving(false)}
  }
  return <div className="modal-backdrop" role="presentation"><section className="modal-card" role="dialog" aria-modal="true" aria-labelledby="queue-modal-title">
    <div className="modal-header"><div><span className="eyebrow">CALL DISTRIBUTION</span><h2 id="queue-modal-title">{queue?'Edit queue':'Add queue'}</h2><p>Configure caller waiting and agent selection through FreeSWITCH mod_callcenter.</p></div><button className="icon-btn" onClick={onClose} aria-label="Close"><X size={20}/></button></div>
    <form onSubmit={submit}>{error&&<div className="page-alert error">{error}</div>}<fieldset><legend>Queue settings</legend><div className="form-grid">
      <label>Queue name<input required value={form.name} onChange={event=>setForm({...form,name:event.target.value})} placeholder="Support"/></label>
      <label>Dial number<input required pattern="\d{2,16}" value={form.number} onChange={event=>setForm({...form,number:event.target.value})}/></label>
      <label>Distribution strategy<select value={form.strategy} onChange={event=>setForm({...form,strategy:event.target.value as QueuePayload['strategy']})}>{queueStrategies.map(([value,label])=><option value={value} key={value}>{label}</option>)}</select></label>
      <label>Status<select value={form.status} onChange={event=>setForm({...form,status:event.target.value as QueuePayload['status']})}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></label>
      <label>Maximum wait (seconds)<input required type="number" min="5" max="3600" value={form.max_wait_seconds} onChange={event=>setForm({...form,max_wait_seconds:Number(event.target.value)})}/></label>
      <label>Wrap-up time (seconds)<input required type="number" min="0" max="600" value={form.wrap_up_seconds} onChange={event=>setForm({...form,wrap_up_seconds:Number(event.target.value)})}/></label>
      <label>Maximum callers<input required type="number" min="1" max="1000" value={form.max_size} onChange={event=>setForm({...form,max_size:Number(event.target.value)})}/></label>
      <label>Music on hold<input required value={form.music_on_hold} onChange={event=>setForm({...form,music_on_hold:event.target.value})}/></label>
    </div></fieldset><fieldset><legend>Queue agents</legend><div className="member-picker">{eligible.length===0?<div className="member-empty"><UsersRound size={18}/> Create agents with assigned extensions first.</div>:eligible.map(agent=><label key={agent.id} className={form.member_agent_ids.includes(agent.id)?'selected':''}><input type="checkbox" checked={form.member_agent_ids.includes(agent.id)} onChange={()=>toggle(agent.id)}/><span><strong>{agent.display_name}</strong><small>{agent.user.extensions?.[0]?.extension_number??'No extension'} · {agent.employee_code}</small></span></label>)}</div></fieldset>
    <div className="modal-actions"><button type="button" className="secondary-button" onClick={onClose}>Cancel</button><button className="primary-button" disabled={saving||form.member_agent_ids.length===0}>{saving?'Saving...':queue?'Save changes':'Create queue'}</button></div></form>
  </section></div>
}

const queueStrategies:[PbxQueue['strategy'],string][]=[
  ['longest-idle-agent','Longest idle'],['round-robin','Round robin'],['ring-all','Ring all'],['top-down','Top down'],
  ['agent-with-least-talk-time','Least talk time'],['agent-with-fewest-calls','Fewest calls'],['sequentially-by-agent-order','Sequential agent order'],['ring-progressively','Ring progressively'],
]
function strategyLabel(value:PbxQueue['strategy']){return queueStrategies.find(item=>item[0]===value)?.[1]??value}
function upsert(items:PbxQueue[],item:PbxQueue){const next=items.some(current=>current.id===item.id)?items.map(current=>current.id===item.id?item:current):[...items,item];return next.sort((a,b)=>a.number.localeCompare(b.number,undefined,{numeric:true}))}
