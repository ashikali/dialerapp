import { useEffect, useMemo, useState } from 'react'
import {
  Activity, Bell, Building2, CalendarDays, ChevronDown, CircleDot, Clock3,
  ContactRound, Download, FileAudio, FileClock, Gauge, Globe2, Grid2X2,
  Headphones, HeartPulse, History, LayoutDashboard, ListChecks, Menu, MessageSquareText,
  LogOut, Mic, MicOff, Moon, Network, Pause, Phone, PhoneCall, PhoneForwarded,
  PhoneIncoming, PhoneOff, PhoneOutgoing, Plus, Radio, Search, Server,
  Settings, ShieldCheck, Sparkles, Users, UsersRound, Voicemail, X, type LucideIcon,
} from 'lucide-react'
import { TenantsPage } from './features/tenants/TenantsPage'
import { AgentsPage } from './features/provisioning/AgentsPage'
import { ExtensionsPage } from './features/provisioning/ExtensionsPage'
import { QueuesPage } from './features/provisioning/QueuesPage'
import { RingGroupsPage } from './features/provisioning/RingGroupsPage'
import { api, type DashboardSummary } from './lib/api'

export type Role = 'super' | 'tenant' | 'agent'
type NavItem = { label: string; icon: LucideIcon }

const roleMeta = {
  super: { label: 'Super Admin', subtitle: 'Platform administrator', initials: 'SA' },
  tenant: { label: 'Tenant Admin', subtitle: 'ABC Finance', initials: 'TA' },
  agent: { label: 'Agent Workspace', subtitle: 'John Smith · 1001', initials: 'JS' },
} as const

const superNav: NavItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard }, { label: 'Tenants', icon: Building2 },
  { label: 'Users', icon: Users }, { label: 'Roles & Permissions', icon: ShieldCheck },
  { label: 'PBX Servers', icon: Server }, { label: 'Platform Usage', icon: Activity },
  { label: 'Audit Logs', icon: FileClock }, { label: 'System Settings', icon: Settings },
]
const tenantNav: NavItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard }, { label: 'Agents', icon: UsersRound },
  { label: 'Extensions', icon: Phone }, { label: 'Queues', icon: Headphones },
  { label: 'Ring Groups', icon: Users }, { label: 'IVR Flows', icon: Network },
  { label: 'DIDs', icon: PhoneIncoming }, { label: 'Trunks & Routes', icon: Radio },
  { label: 'Campaigns', icon: PhoneOutgoing }, { label: 'Contacts', icon: ContactRound },
  { label: 'Dispositions', icon: ListChecks }, { label: 'Recordings', icon: FileAudio },
  { label: 'Reports', icon: Activity }, { label: 'Tenant Settings', icon: Settings },
]
const agentNav: NavItem[] = [
  { label: 'Workspace', icon: Grid2X2 }, { label: 'My Campaigns', icon: PhoneOutgoing },
  { label: 'Contacts', icon: ContactRound }, { label: 'Call History', icon: History },
  { label: 'Callbacks', icon: CalendarDays },
]

const metrics = {
  super: [
    ['Total Tenants', '24', '22 active', Building2, 'violet'], ['Total Extensions', '1,248', '1,102 registered', Phone, 'green'],
    ['Total Agents', '856', '302 logged in', Headphones, 'blue'], ['Active Calls', '156', '28 in queue', Activity, 'orange'],
    ['Calls Today', '3,892', '↑ 18.5% vs yesterday', PhoneCall, 'purple'],
  ],
  tenant: [
    ['Extensions', '128', '114 registered', Phone, 'violet'], ['Agents', '86', '42 logged in', Headphones, 'green'],
    ['Active Calls', '32', '7 waiting', Activity, 'blue'], ['Answer Rate', '91.8%', '↑ 4.2% this week', Gauge, 'orange'],
    ['Calls Today', '864', '612 inbound · 252 outbound', PhoneCall, 'purple'],
  ],
} as const

const agents = [
  ['John Smith', '1001', 'On Call', '00:15:32', 'JS', 'green'], ['Emma Johnson', '1002', 'On Call', '00:07:11', 'EJ', 'green'],
  ['Michael Brown', '1003', 'After Call Work', '00:02:48', 'MB', 'orange'], ['Sophia Davis', '1004', 'Available', '', 'SD', 'green'],
  ['James Wilson', '1005', 'Break', '00:15:00', 'JW', 'orange'], ['Olivia Martinez', '1006', 'Offline', '', 'OM', 'gray'],
]
const recentCalls = [
  ['+1 202-555-0187', 'ABC Finance', '00:02:16', 'in'], ['1001', 'John Smith', '00:05:42', 'out'],
  ['+91 98765 43210', 'Tech Solutions', 'Missed', 'missed'], ['1002', 'Emma Johnson', '00:01:08', 'in'],
  ['+44 20 7946 0958', 'Global Mart', '00:03:11', 'out'],
]

function Brand() {
  return <div className="brand"><div className="brand-wave"><i/><i/><i/><i/><i/></div><strong>PBX<span>Pro</span></strong></div>
}

function Sidebar({ role, active, onNavigate, collapsed, onClose }: { role: Role; active: string; onNavigate: (v: string) => void; collapsed: boolean; onClose: () => void }) {
  const nav = role === 'super' ? superNav : role === 'tenant' ? tenantNav : agentNav
  return <aside className={`sidebar ${collapsed ? '' : 'open'}`}>
    <div className="side-top"><Brand/><button className="icon-btn mobile-close" onClick={onClose}><X size={20}/></button></div>
    <div className="side-label">{role === 'super' ? 'Platform control' : role === 'tenant' ? 'Tenant management' : 'Agent console'}</div>
    <nav>{nav.map(({ label, icon: Icon }) => <button key={label} className={active === label ? 'active' : ''} onClick={() => { onNavigate(label); onClose() }}><Icon size={17}/><span>{label}</span>{label === 'Callbacks' && <b>3</b>}</button>)}</nav>
    <div className="profile-card"><div className="profile-avatar">{roleMeta[role].initials}<i/></div><div><strong>{roleMeta[role].label}</strong><small>{roleMeta[role].subtitle}</small></div></div>
  </aside>
}

function Topbar({ role, setRole, onMenu, canSwitchRole, onLogout }: { role: Role; setRole: (role: Role) => void; onMenu: () => void; canSwitchRole: boolean; onLogout?: () => void }) {
  const [open, setOpen] = useState(false)
  return <header className="topbar">
    <button className="icon-btn menu-toggle" onClick={onMenu}><Menu size={20}/></button>
    <button className="tenant-select"><Building2 size={16}/> {role === 'super' ? 'All Tenants' : 'ABC Finance'} <ChevronDown size={15}/></button>
    <label className="search"><Search size={18}/><input aria-label="Search" placeholder="Search agents, calls, tenants..."/><kbd>/</kbd></label>
    <div className="top-actions"><button className="icon-btn"><Moon size={19}/></button><button className="icon-btn notification"><Bell size={19}/><b>12</b></button><button className="icon-btn globe"><Globe2 size={19}/></button>{onLogout && <button className="icon-btn" aria-label="Sign out" title="Sign out" onClick={onLogout}><LogOut size={18}/></button>}
      <div className="role-wrap"><button className="role-picker" onClick={() => canSwitchRole && setOpen(!open)}><span>{roleMeta[role].initials}</span><div><strong>{roleMeta[role].label}</strong><small>{roleMeta[role].subtitle}</small></div>{canSwitchRole && <ChevronDown size={15}/>}</button>
        {canSwitchRole && open && <div className="role-menu">{(['super', 'tenant', 'agent'] as Role[]).map(r => <button key={r} className={role === r ? 'selected' : ''} onClick={() => { setRole(r); setOpen(false) }}><span>{roleMeta[r].initials}</span><div><strong>{roleMeta[r].label}</strong><small>{roleMeta[r].subtitle}</small></div></button>)}</div>}
      </div>
    </div>
  </header>
}

function MetricCard({ item }: { item: readonly [string, string, string, LucideIcon, string] }) {
  const [label, value, hint, Icon, color] = item
  return <article className="metric card"><div className={`metric-icon ${color}`}><Icon size={23}/></div><div><small>{label}</small><strong>{value}</strong><span className={hint.includes('↑') || hint.includes('active') || hint.includes('registered') || hint.includes('logged') ? 'positive' : ''}>{hint}</span></div></article>
}

function LineChart() {
  return <div className="chart-wrap"><div className="chart-legend"><span className="blue-dot"/> Inbound <span className="green-dot"/> Outbound</div><svg viewBox="0 0 650 240" role="img" aria-label="Calls throughout the week">
    {[35,85,135,185].map(y => <line key={y} x1="50" y1={y} x2="630" y2={y} className="gridline"/>)}
    <defs><linearGradient id="blueFade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor="#2788f8" stopOpacity=".22"/><stop offset="1" stopColor="#2788f8" stopOpacity="0"/></linearGradient><linearGradient id="greenFade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor="#22b573" stopOpacity=".18"/><stop offset="1" stopColor="#22b573" stopOpacity="0"/></linearGradient></defs>
    <path d="M50 165 C95 158 125 130 165 113 S240 145 285 83 S355 40 400 91 S485 144 530 100 S585 80 630 113 L630 210 L50 210Z" fill="url(#blueFade)"/>
    <path d="M50 193 C100 170 135 144 173 158 S235 196 285 136 S350 94 400 146 S475 187 530 151 S585 154 630 175 L630 210 L50 210Z" fill="url(#greenFade)"/>
    <path d="M50 165 C95 158 125 130 165 113 S240 145 285 83 S355 40 400 91 S485 144 530 100 S585 80 630 113" className="line-blue"/>
    <path d="M50 193 C100 170 135 144 173 158 S235 196 285 136 S350 94 400 146 S475 187 530 151 S585 154 630 175" className="line-green"/>
    {['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map((d, i) => <text key={d} x={50+i*96.5} y="232" className="axis">{d}</text>)}
  </svg></div>
}

function Distribution() {
  return <div className="distribution"><div className="donut"><div><strong>3,892</strong><small>Total calls</small></div></div><div className="dist-list">
    <p><i className="green"/><span>Inbound Answered</span><strong>2,350 <small>60.4%</small></strong></p><p><i className="blue"/><span>Outbound Answered</span><strong>950 <small>24.4%</small></strong></p><p><i className="orange"/><span>Missed Calls</span><strong>320 <small>8.2%</small></strong></p><p><i className="violet"/><span>Voicemail</span><strong>272 <small>7.0%</small></strong></p>
  </div></div>
}

function LiveAgents() {
  return <section className="card live-agents"><div className="card-title"><h3>Live Agents</h3><button>View all</button></div><div className="tabs"><button className="active">All (856)</button><button>Logged In (302)</button><button>On Call (142)</button></div>
    <div className="agent-list">{agents.map(([name, ext, status, time, initials, state]) => <div className="agent-row" key={ext}><span className={`avatar avatar-${state}`}>{initials}<i/></span><div><strong>{name}</strong><small>{ext}</small></div><div className={`agent-state ${state}`}><span>●</span> {status}<small>{time}</small></div></div>)}</div>
    <div className="logged-total"><span>Total logged in</span><strong>302 / 856</strong></div>
  </section>
}

function RecentCalls() {
  return <section className="card list-card"><div className="card-title"><h3>Recent Calls</h3><button>View all</button></div>{recentCalls.map(([number, name, time, type]) => <div className="call-row" key={number+name}><span className={`call-type ${type}`}><PhoneIncoming size={16}/></span><div><strong>{number}</strong><small>{name}</small></div><div><strong>{time}</strong><small>10:{20 + recentCalls.indexOf([number,name,time,type])} AM</small></div></div>)}</section>
}

function SystemHealth() {
  return <section className="card list-card"><div className="card-title"><h3>System Health</h3><span className="healthy">All healthy</span></div>{[['FreeSWITCH Servers','Operational',100],['Database','Healthy',100],['Redis','Healthy',100],['Disk Usage','45%',45],['CPU Load','32%',32],['Memory Usage','58%',58]].map(([name, value, percent], i) => <div className="health-row" key={name}><span className={i < 3 ? 'health-icon' : 'resource-icon'}>{i < 3 ? <Server size={15}/> : <Activity size={15}/>}</span><span>{name}</span><strong>{value}</strong>{i > 2 && <i><b style={{width: `${percent}%`}}/></i>}</div>)}</section>
}

function MiniTenants({ role }: { role: Role }) {
  const rows = role === 'super' ? [['ABC Finance','42 calls'],['Tech Solutions','38 calls'],['Global Mart','28 calls'],['Health Plus','18 calls'],['Edu Center','15 calls']] : [['Sales Queue','12 waiting'],['Support Queue','7 waiting'],['Collections','4 waiting'],['VIP Desk','0 waiting'],['After Hours','Closed']]
  return <section className="card list-card"><div className="card-title"><h3>{role === 'super' ? 'Top Tenants by Active Calls' : 'Queue Activity'}</h3><button>View all</button></div>{rows.map(([name,value],i) => <div className="tenant-row" key={name}><span className={`tenant-icon c${i}`}><Users size={16}/></span><strong>{name}</strong><small>{value}</small></div>)}</section>
}

function ActiveCalls() {
  return <section className="card active-calls"><div className="card-title"><h3>Active Calls</h3><button>View all</button></div>{agents.slice(0,5).map((a,i) => <div className="active-row" key={a[1]}><span className="call-type in"><PhoneCall size={16}/></span><div><strong>{i % 2 ? '+1 202-555-0187' : a[1]}</strong><small>{a[0]}</small></div><span className="duration">● 00:{i*2+3}:1{i}</span></div>)}<button className="monitor"><Headphones size={16}/> Go to Live Monitor</button></section>
}

function Dashboard({ role, onNavigate }: { role: 'super' | 'tenant'; onNavigate: (page:string)=>void }) {
  const [summary,setSummary]=useState<DashboardSummary|null>(null)
  useEffect(()=>{ let active=true; api.dashboard().then(response=>{if(active)setSummary(response.data)}).catch(()=>{}); return()=>{active=false} },[role])
  const dashboardMetrics = summary ? role === 'super' ? [
    ['Total Tenants',summary.tenants.toLocaleString(),`${summary.tenants} onboarded`,Building2,'violet'],
    ['Total Extensions',summary.extensions.toLocaleString(),'Provisioned extensions',Phone,'green'],
    ['Total Agents',summary.agents.toLocaleString(),'Provisioned agents',Headphones,'blue'],
    ['Active Calls',summary.active_calls.toLocaleString(),'Live platform calls',Activity,'orange'],
    ['Calls Today',summary.calls_today.toLocaleString(),'Since midnight',PhoneCall,'purple'],
  ] as const : [
    ['Extensions',summary.extensions.toLocaleString(),'Provisioned extensions',Phone,'violet'],
    ['Agents',summary.agents.toLocaleString(),'Provisioned agents',Headphones,'green'],
    ['Active Calls',summary.active_calls.toLocaleString(),'Live tenant calls',Activity,'blue'],
    ['Calls Today',summary.calls_today.toLocaleString(),'Since midnight',PhoneCall,'orange'],
    ['Tenants','1','Current workspace',Building2,'purple'],
  ] as const : metrics[role]
  return <div className="dashboard-grid"><div className="metric-grid">{dashboardMetrics.map((m) => <MetricCard key={m[0]} item={m}/>)}</div>
    <section className="card chart-card"><div className="card-title"><h3>Call Statistics</h3><button className="select-btn">This Week <ChevronDown size={14}/></button></div><LineChart/></section>
    <section className="card distribution-card"><div className="card-title"><h3>Call Distribution</h3></div><Distribution/></section>
    <LiveAgents/><MiniTenants role={role}/><RecentCalls/><SystemHealth/><ActiveCalls/>
    <section className="quick-access"><h3>Quick Access</h3><div>{(role === 'super' ? [[Plus,'Add Tenant'],[Users,'Add Admin'],[Server,'PBX Server'],[Activity,'Usage Report'],[ShieldCheck,'Audit Logs'],[Settings,'Settings']] : [[Phone,'Add Extension'],[Users,'Add Agent'],[Headphones,'Create Queue'],[Network,'Create IVR'],[PhoneIncoming,'Add DID'],[PhoneOutgoing,'Campaign'],[Activity,'Reports'],[Settings,'Settings']]).map(([Icon,label]) => { const I = Icon as LucideIcon; return <button key={label as string} onClick={()=>label === 'Add Tenant' ? onNavigate('Tenants') : label === 'Add Extension' ? onNavigate('Extensions') : label === 'Add Agent' ? onNavigate('Agents') : label === 'Create Queue' ? onNavigate('Queues') : undefined}><span><I size={21}/></span>{label as string}</button>})}</div></section>
  </div>
}

function AgentWorkspace() {
  const [mode, setMode] = useState<'Inbound'|'Outbound'>('Outbound')
  const [status, setStatus] = useState('Ready')
  const [callState, setCallState] = useState<'idle'|'ringing'|'connected'|'hold'|'wrap'>('idle')
  const [seconds, setSeconds] = useState(0)
  const [muted, setMuted] = useState(false)
  const [notes, setNotes] = useState('')
  useEffect(() => { if (callState !== 'connected') return; const timer = window.setInterval(() => setSeconds(s => s + 1), 1000); return () => clearInterval(timer) }, [callState])
  const time = `${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`
  function startCall() { setCallState('ringing'); setStatus('Reserved'); window.setTimeout(() => { setCallState('connected'); setStatus('On Call'); setSeconds(0) }, 1200) }
  function hangup() { setCallState('wrap'); setStatus('After Call Work') }
  return <div className="agent-workspace">
    <section className="workspace-main"><div className="agent-greeting"><div><span className="eyebrow">OUTBOUND · PREVIEW CAMPAIGN</span><h2>Good morning, John</h2><p>Here is your next customer. Review the details before dialing.</p></div><div className="campaign-progress"><span>Daily progress</span><strong>38 / 60 calls</strong><i><b/></i></div></div>
      <div className="customer-card card"><div className="customer-heading"><span className="large-avatar">AM</span><div><small>LEAD #AF-10492</small><h2>Ananya Mehta</h2><p><Building2 size={15}/> Acme Industries · Priority lead</p></div><span className="lead-score"><Sparkles size={15}/> 92 score</span></div>
        <div className="customer-details"><div><small>PRIMARY PHONE</small><strong>+91 98765 43210</strong></div><div><small>EMAIL</small><strong>ananya@acme.in</strong></div><div><small>LOCATION</small><strong>Mumbai, Maharashtra</strong></div><div><small>LOCAL TIME</small><strong>11:42 AM (IST)</strong></div></div>
      </div>
      <div className="workspace-columns"><section className="card script-card"><div className="card-title"><h3>Agent Script</h3><span className="script-tag">Renewal Campaign</span></div><p>Hi <mark>Ananya</mark>, this is John calling from ABC Finance. I’m reaching out regarding your business account renewal due this month.</p><p>Do you have a few minutes to review your current plan and the benefits available to you?</p><div className="script-tip"><Sparkles size={17}/><span><strong>Conversation tip</strong>Customer viewed the premium plan twice this week.</span></div></section>
        <section className="card history-card"><div className="card-title"><h3>Contact History</h3><button>View all</button></div><div><i className="history-dot blue"/><span><strong>Outbound call · No answer</strong><small>July 11, 4:25 PM · John Smith</small></span></div><div><i className="history-dot green"/><span><strong>Email opened</strong><small>July 10, 10:18 AM · Renewal offer</small></span></div><div><i className="history-dot violet"/><span><strong>Callback requested</strong><small>July 8, 2:30 PM · Emma Johnson</small></span></div></section>
      </div>
      <section className="card notes-card"><div className="card-title"><h3>Call Notes</h3><small>{notes.length}/500</small></div><textarea value={notes} onChange={e => setNotes(e.target.value.slice(0,500))} placeholder="Add notes about this conversation..."/></section>
    </section>
    <aside className="call-panel"><div className="registration"><span><i/> WebRTC registered</span><small>Extension 1001</small></div>
      <div className="control-field"><label>Work mode</label><div className="segmented"><button onClick={() => setMode('Inbound')} className={mode === 'Inbound' ? 'active' : ''}>Inbound</button><button onClick={() => setMode('Outbound')} className={mode === 'Outbound' ? 'active' : ''}>Outbound</button></div></div>
      <div className="control-field"><label>Agent status</label><button className="status-select"><span className={`status-dot ${status.toLowerCase().replaceAll(' ','-')}`}/>{status}<ChevronDown size={15}/></button></div>
      <div className={`call-display ${callState}`}><div className="pulse-phone"><PhoneCall size={25}/></div><small>{callState === 'idle' ? 'READY TO CALL' : callState === 'ringing' ? 'CONNECTING' : callState === 'connected' ? 'CALL CONNECTED' : callState === 'hold' ? 'ON HOLD' : 'AFTER CALL WORK'}</small><h3>{callState === 'idle' ? 'No active call' : 'Ananya Mehta'}</h3><p>{callState === 'idle' ? 'Select a contact or enter a number' : '+91 98765 43210'}</p>{callState !== 'idle' && callState !== 'wrap' && <strong>{callState === 'ringing' ? 'Customer ringing…' : time}</strong>}</div>
      {callState === 'idle' ? <button className="dial-primary" onClick={startCall}><PhoneCall size={19}/> Dial customer</button> : callState === 'wrap' ? <div className="wrap-form"><label>Disposition</label><select defaultValue=""><option value="" disabled>Select outcome</option><option>Interested</option><option>Callback requested</option><option>Not interested</option><option>No answer</option></select><button onClick={() => {setCallState('idle');setStatus('Ready')}}>Save & next lead</button></div> : <div className="call-controls"><button onClick={() => setMuted(!muted)} className={muted ? 'selected' : ''}>{muted ? <MicOff/> : <Mic/>}<span>{muted ? 'Unmute' : 'Mute'}</span></button><button onClick={() => setCallState(callState === 'hold' ? 'connected' : 'hold')}><Pause/><span>{callState === 'hold' ? 'Resume' : 'Hold'}</span></button><button><PhoneForwarded/><span>Transfer</span></button><button className="hangup" onClick={hangup}><PhoneOff/><span>End</span></button></div>}
      <div className="dialpad"><h4>Dial pad</h4><div>{['1','2','3','4','5','6','7','8','9','*','0','#'].map(n => <button key={n}>{n}</button>)}</div></div>
    </aside>
  </div>
}

function GenericPage({ title, role }: { title: string; role: Role }) {
  const rows = useMemo(() => ['Primary Operations', 'Sales Team', 'Support Desk', 'North Region', 'After Hours'], [])
  return <div className="generic-page"><div className="generic-toolbar"><label className="search"><Search size={17}/><input placeholder={`Search ${title.toLowerCase()}...`}/></label><button className="primary-button"><Plus size={17}/> Add {title.replace(/s$/, '')}</button></div><section className="card data-table"><div className="table-head"><span>Name</span><span>Status</span><span>Tenant</span><span>Updated</span><span/></div>{rows.map((name,i) => <div className="table-row" key={name}><span><i className={`table-icon t${i}`}><CircleDot size={16}/></i><strong>{name}</strong><small>{title} #{1040+i}</small></span><span><b className={i === 4 ? 'badge paused' : 'badge'}>{i === 4 ? 'Paused' : 'Active'}</b></span><span>{role === 'super' ? ['ABC Finance','Tech Solutions','Global Mart','Health Plus','Edu Center'][i] : 'ABC Finance'}</span><span>{i+1} hour{i ? 's' : ''} ago</span><span>•••</span></div>)}</section></div>
}

export default function App({ lockedRole, onLogout }: { lockedRole?: Role; onLogout?: () => void }) {
  const [role, setRoleState] = useState<Role>(() => lockedRole || (localStorage.getItem('pbx-role') as Role) || 'super')
  const [active, setActive] = useState(role === 'agent' ? 'Workspace' : 'Dashboard')
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const setRole = (r: Role) => { setRoleState(r); localStorage.setItem('pbx-role', r); setActive(r === 'agent' ? 'Workspace' : 'Dashboard') }
  const isDashboard = active === 'Dashboard'
  return <div className="app-shell"><Sidebar role={role} active={active} onNavigate={setActive} collapsed={sidebarOpen} onClose={() => setSidebarOpen(false)}/><div className="main-shell"><Topbar role={role} setRole={setRole} onMenu={() => setSidebarOpen(true)} canSwitchRole={!lockedRole} onLogout={onLogout}/><main>
    <div className="page-heading"><div><h1>{active}</h1><p>{isDashboard ? role === 'super' ? 'Platform-wide performance and system overview' : 'Today’s contact-center performance at a glance' : active === 'Workspace' ? 'Manage conversations and customer outcomes' : `Manage ${active.toLowerCase()} for ${role === 'super' ? 'the platform' : 'ABC Finance'}`}</p></div>{(isDashboard || active === 'Workspace') && <div className="heading-actions"><button className="date-button"><CalendarDays size={16}/> Jul 7, 2026 – Jul 13, 2026</button><button className="export-button"><Download size={16}/> Export</button></div>}</div>
    {role === 'super' && active === 'Tenants' ? <TenantsPage/> : role === 'tenant' && active === 'Agents' ? <AgentsPage/> : role === 'tenant' && active === 'Extensions' ? <ExtensionsPage/> : role === 'tenant' && active === 'Queues' ? <QueuesPage/> : role === 'tenant' && active === 'Ring Groups' ? <RingGroupsPage/> : role === 'agent' && active === 'Workspace' ? <AgentWorkspace/> : isDashboard && role !== 'agent' ? <Dashboard role={role} onNavigate={setActive}/> : <GenericPage title={active} role={role}/>}</main></div>{sidebarOpen && <div className="scrim" onClick={() => setSidebarOpen(false)}/>}</div>
}
