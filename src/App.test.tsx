import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('role portals',()=>{
  it('renders the Super Admin dashboard',()=>{render(<App lockedRole="super"/>);expect(screen.getByRole('heading',{name:'Dashboard'})).toBeInTheDocument();expect(screen.getByText('Total Tenants')).toBeInTheDocument()})
  it('renders the Tenant Admin dashboard',()=>{render(<App lockedRole="tenant"/>);expect(screen.getByText('Answer Rate')).toBeInTheDocument();expect(screen.getByText('Queue Activity')).toBeInTheDocument()})
  it('renders the Agent calling workspace',()=>{render(<App lockedRole="agent"/>);expect(screen.getByText('Ananya Mehta')).toBeInTheDocument();expect(screen.getByRole('button',{name:'Dial customer'})).toBeInTheDocument()})
})
