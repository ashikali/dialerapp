import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from '../../App'
import { api, type Tenant } from '../../lib/api'

const tenant: Tenant = {
  id:'tenant-1',name:'ABC Finance',code:'abc-finance',sip_domain:'abcfinance.pbxpro.test',status:'ACTIVE',timezone:'Asia/Kolkata',
  extension_start:1000,extension_end:1999,max_extensions:100,max_agents:50,max_queues:10,max_concurrent_calls:25,
  recording_retention_days:90,users_count:1,users:[{id:'user-1',tenant_id:'tenant-1',name:'ABC Administrator',username:'admin',email:'admin@abcfinance.test',status:'ACTIVE'}],
  created_at:'2026-07-17T00:00:00Z',
}

describe('Super Admin tenant management',()=>{
  afterEach(()=>vi.restoreAllMocks())

  it('loads real tenants and opens the onboarding form',async()=>{
    vi.spyOn(api,'dashboard').mockRejectedValue(new Error('not needed'))
    vi.spyOn(api,'tenants').mockResolvedValue({data:[tenant],current_page:1,last_page:1,total:1})
    render(<App lockedRole="super"/>)

    fireEvent.click(screen.getByRole('button',{name:'Tenants'}))
    expect(await screen.findByText('admin@abcfinance.pbxpro.test')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button',{name:/Onboard tenant/i}))
    expect(screen.getByRole('heading',{name:'Onboard tenant'})).toBeInTheDocument()
    await waitFor(()=>expect(api.tenants).toHaveBeenCalledOnce())
  })
})
