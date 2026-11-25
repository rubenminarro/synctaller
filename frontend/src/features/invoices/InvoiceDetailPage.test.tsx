import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { invoicesApi, type Invoice } from '@/features/invoices/api'
import { InvoiceDetailPage } from '@/features/invoices/InvoiceDetailPage'

vi.mock('@/features/auth/useAuth', () => ({ useAuth: () => ({ can: () => true }) }))

const issued: Invoice = {
  id: 7,
  number: '001-001-0000007',
  status: { value: 'issued', label: 'Emitida' },
  work_order_id: 1,
  client_id: 1,
  stamp: { id: 1, stamp_number: '12345678', valid_until: null },
  customer: { name: 'Ana Gomez', tax_id: '1234567', address: null, phone: null, email: null, missing: ['address', 'phone', 'email'] },
  condition: { value: 'cash', label: 'Contado' },
  issue_date: '2026-10-31',
  due_date: null,
  is_cancellable: false,
  items: [],
  totals: { exempt: '0', taxed_5: '0', taxed_10: '110000', iva_5: '0', iva_10: '10000', iva_total: '10000', discount: '0', total: '110000' },
  payment_summary: { paid_total: '0.00', credited_total: '0.00', withheld_total: '0.00', balance: '110000.00', status: 'unpaid', vat_withholding_suggestion: null },
  payments: [],
  credit_notes: [],
  withholdings: [],
  issued_at: '2026-10-31T15:00:00Z',
  issued_by: 1,
  cancelled_at: null,
  cancelled_by: null,
  cancellation_reason: null,
  created_by: 1,
  created_at: '2026-10-31T14:00:00Z',
  updated_at: '2026-10-31T15:00:00Z',
}

function renderPage(invoice: Invoice) {
  vi.spyOn(invoicesApi, 'show').mockResolvedValue(invoice)
  render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter initialEntries={[`/invoices/${invoice.id}`]}>
        <Routes>
          <Route path="/invoices/:id" element={<InvoiceDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('anulación de la factura (V19)', () => {
  it('dentro del mes de emisión ofrece anular', async () => {
    renderPage({ ...issued, issue_date: '2026-11-25', is_cancellable: true })

    expect(await screen.findByRole('button', { name: 'Anular' })).toBeInTheDocument()
    expect(screen.queryByText(/Emitida en un mes anterior/)).not.toBeInTheDocument()
  })

  it('de un mes anterior no ofrece anular y remite a la nota de crédito', async () => {
    renderPage(issued)

    expect(await screen.findByText(/Emitida en un mes anterior: ya no se puede anular/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Anular' })).not.toBeInTheDocument()
  })
})
