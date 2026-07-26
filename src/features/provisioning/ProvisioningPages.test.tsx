import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from '../../App'
import { api, type Agent, type Extension, type PbxQueue, type RingGroup } from '../../lib/api'

const extension: Extension = {
  id:'extension-1',user_id:null,extension_number:'1001',sip_username:'1001',caller_id_name:'John Smith',caller_id_number:'1001',
  status:'ACTIVE',webrtc_enabled:true,voicemail_enabled:false,dnd_enabled:false,ring_timeout:30,created_at:'2026-07-22T00:00:00Z',
}
const agent: Agent = {
  id:'agent-1',employee_code:'AGT-1001',display_name:'John',status:'ACTIVE',created_at:'2026-07-22T00:00:00Z',
  user:{id:'user-1',tenant_id:'tenant-1',name:'John Smith',email:'john@abcfinance.test',role:'AGENT',status:'ACTIVE',extensions:[{...extension,user_id:'user-1'}]},
}
const queue:PbxQueue={
  id:'queue-1',name:'Support',number:'6000',strategy:'longest-idle-agent',wrap_up_seconds:30,max_wait_seconds:300,max_size:100,
  music_on_hold:'local_stream://moh',status:'ACTIVE',members:[{id:'member-1',priority:1,skill:1,agent}],created_at:'2026-07-26T00:00:00Z',
}
const ringGroup:RingGroup={
  id:'ring-1',name:'Reception',number:'7000',strategy:'SIMULTANEOUS',ring_timeout:30,status:'ACTIVE',
  members:[{id:'ring-member-1',position:1,extension}],created_at:'2026-07-26T00:00:00Z',
}

describe('Tenant Admin provisioning',()=>{
  afterEach(()=>{ cleanup(); vi.restoreAllMocks() })

  it('loads extensions and opens the extension form',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'extensions').mockResolvedValue({data:[extension],current_page:1,last_page:1,total:1})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Extensions'}))
    expect(await screen.findByText(/SIP 1001/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button',{name:/Add extension/i}))
    expect(screen.getByRole('heading',{name:'Add extension'})).toBeInTheDocument()
    expect((screen.getByRole('textbox',{name:'SIP password'}) as HTMLInputElement).value.length).toBeGreaterThanOrEqual(16)
  })

  it('opens an existing extension for editing without exposing its password',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'extensions').mockResolvedValue({data:[extension],current_page:1,last_page:1,total:1})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Extensions'}))
    fireEvent.click(await screen.findByRole('button',{name:'Edit'}))
    expect(screen.getByRole('heading',{name:'Edit extension'})).toBeInTheDocument()
    expect(screen.getByLabelText('SIP password')).toHaveValue('')
    expect(screen.getByText('Password is hidden. Reveal it or generate a replacement.')).toBeInTheDocument()
  })

  it('reveals an existing SIP password only after the admin requests it',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'extensions').mockResolvedValue({data:[extension],current_page:1,last_page:1,total:1})
    vi.spyOn(api,'revealExtensionPassword').mockResolvedValue({data:{sip_password:'Strong-SIP-Secret-1001'}})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Extensions'}))
    fireEvent.click(await screen.findByRole('button',{name:'Edit'}))
    fireEvent.click(screen.getByRole('button',{name:'Reveal SIP password'}))
    expect(await screen.findByDisplayValue('Strong-SIP-Secret-1001')).toBeInTheDocument()
    expect(api.revealExtensionPassword).toHaveBeenCalledWith('extension-1')
  })

  it('loads agents and shows their assigned extension',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'agents').mockResolvedValue({data:[agent],current_page:1,last_page:1,total:1})
    vi.spyOn(api,'extensions').mockResolvedValue({data:[extension],current_page:1,last_page:1,total:1})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Agents'}))
    expect(await screen.findByText('john@abcfinance.test')).toBeInTheDocument()
    expect(screen.getByText('Ready for device setup')).toBeInTheDocument()
  })

  it('loads queues and opens the functional queue form',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'queues').mockResolvedValue({data:[queue],current_page:1,last_page:1,total:1})
    vi.spyOn(api,'agents').mockResolvedValue({data:[agent],current_page:1,last_page:1,total:1})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Queues'}))
    expect(await screen.findByText('Dial 6000')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button',{name:'Add queue'}))
    expect(screen.getByRole('heading',{name:'Add queue'})).toBeInTheDocument()
    expect(screen.getByRole('checkbox')).toBeInTheDocument()
  })

  it('loads ring groups and opens the member selector',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'ringGroups').mockResolvedValue({data:[ringGroup],current_page:1,last_page:1,total:1})
    vi.spyOn(api,'extensions').mockResolvedValue({data:[extension],current_page:1,last_page:1,total:1})
    render(<App lockedRole="tenant"/>)
    fireEvent.click(screen.getByRole('button',{name:'Ring Groups'}))
    expect(await screen.findByText('Dial 7000')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button',{name:'Add ring group'}))
    expect(screen.getByRole('heading',{name:'Add ring group'})).toBeInTheDocument()
    expect(screen.getByText('1001 · John Smith')).toBeInTheDocument()
  })
})
