import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { invoicesApi, type Invoice } from '@/features/invoices/api'
import { EditInvoiceModal } from '@/features/invoices/InvoiceModals'

const draft: Invoice = {
  id: 9,
  number: null,
  status: { value: 'draft', label: 'Borrador' },
  work_order_id: 1,
  client_id: 1,
  stamp: { id: null, stamp_number: null, valid_until: null },
  customer: { name: 'Ana Gomez', tax_id: '1234567', address: 'Mcal. López 456', phone: '595981123456', email: null, missing: ['email'] },
  condition: { value: 'cash', label: 'Contado' },
  issue_date: null,
  due_date: null,
  is_cancellable: false,
  totals: { exempt: '0', taxed_5: '0', taxed_10: '110000', iva_5: '0', iva_10: '10000', iva_total: '10000', discount: '0', total: '110000' },
  payment_summary: { paid_total: '0.00', credited_total: '0.00', withheld_total: '0.00', balance: '110000.00', status: null, vat_withholding_suggestion: null },
  issued_at: null,
  issued_by: null,
  cancelled_at: null,
  cancelled_by: null,
  cancellation_reason: null,
  created_by: 1,
  created_at: '2026-11-24T10:00:00Z',
  updated_at: '2026-11-24T10:00:00Z',
}

describe('datos de la factura (V10)', () => {
  it('precarga teléfono y correo y los manda con el resto, vacíos como null', async () => {
    const user = userEvent.setup()
    const update = vi.spyOn(invoicesApi, 'update').mockResolvedValue(draft)
    const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    render(
      <QueryClientProvider client={queryClient}>
        <EditInvoiceModal invoice={draft} onClose={() => {}} />
      </QueryClientProvider>,
    )

    expect(screen.getByLabelText('Teléfono')).toHaveValue('595981123456')
    expect(screen.getByLabelText('Correo')).toHaveValue('')

    await user.clear(screen.getByLabelText('Teléfono'))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(update).toHaveBeenCalledWith(
      9,
      expect.objectContaining({ customer_address: 'Mcal. López 456', customer_phone: null, customer_email: null }),
    )
  })
})
