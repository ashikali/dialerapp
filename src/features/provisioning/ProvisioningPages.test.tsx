import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from '../../App'
import { api, type Agent, type Extension } from '../../lib/api'

const extension: Extension = {
  id:'extension-1',user_id:null,extension_number:'1001',sip_username:'1001',caller_id_name:'John Smith',caller_id_number:'1001',
  status:'ACTIVE',webrtc_enabled:true,voicemail_enabled:false,dnd_enabled:false,ring_timeout:30,created_at:'2026-07-22T00:00:00Z',
}
const agent: Agent = {
  id:'agent-1',employee_code:'AGT-1001',display_name:'John',status:'ACTIVE',created_at:'2026-07-22T00:00:00Z',
  user:{id:'user-1',tenant_id:'tenant-1',name:'John Smith',email:'john@abcfinance.test',role:'AGENT',status:'ACTIVE',extensions:[{...extension,user_id:'user-1'}]},
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
    expect(screen.getByRole('textbox',{name:'SIP password'})).toHaveValue('')
    expect(screen.getByText('Existing password will not change.')).toBeInTheDocument()
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
})
