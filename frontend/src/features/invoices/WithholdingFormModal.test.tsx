import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { withholdingInput, withholdingsApi, type Invoice, type Withholding } from '@/features/invoices/api'
import { WithholdingFormModal } from '@/features/invoices/Withholdings'

function invoice(suggestion: string | null): Invoice {
  return {
    id: 7,
    number: '001-001-0000007',
    status: { value: 'issued', label: 'Emitida' },
    work_order_id: 1,
    client_id: 1,
    stamp: { id: 1, stamp_number: '12345678', valid_until: null },
    customer: { name: 'Seguros del Sur S.A.', tax_id: '80012345-6', address: null },
    condition: { value: 'credit', label: 'Crédito' },
    issue_date: '2026-11-24',
    due_date: '2026-12-24',
    totals: { exempt: '0', taxed_5: '0', taxed_10: '1100000', iva_5: '0', iva_10: '100000', iva_total: '100000', discount: '0', total: '1100000' },
    payment_summary: {
      paid_total: '0.00',
      credited_total: '0.00',
      withheld_total: '0.00',
      balance: '1100000.00',
      status: 'unpaid',
      vat_withholding_suggestion: suggestion,
    },
    issued_at: null,
    issued_by: null,
    cancelled_at: null,
    cancelled_by: null,
    cancellation_reason: null,
    created_by: 1,
    created_at: '2026-11-24T10:00:00Z',
    updated_at: '2026-11-24T10:00:00Z',
  }
}

function renderModal(suggestion: string | null) {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <WithholdingFormModal invoice={invoice(suggestion)} onClose={() => {}} />
    </QueryClientProvider>,
  )
}

describe('retenciones', () => {
  it('el cuerpo recorta los textos y manda el importe entero', () => {
    expect(
      withholdingInput({
        tax: 'income',
        amount: ' 2000 ',
        certificate_number: ' 001-001-0000123 ',
        certificate_stamp_number: ' 17654321 ',
        withholding_date: '2026-11-24',
      }),
    ).toEqual({
      tax: 'income',
      amount: 2000,
      certificate_number: '001-001-0000123',
      certificate_stamp_number: '17654321',
      withholding_date: '2026-11-24',
    })
  })

  it('arranca con IVA y la sugerencia del backend, y registra el comprobante', async () => {
    const user = userEvent.setup()
    const create = vi.spyOn(withholdingsApi, 'create').mockResolvedValue({} as Withholding)
    renderModal('30000.00')

    expect(screen.getByLabelText('Importe retenido (Gs.)')).toHaveValue(30000)
    await user.type(screen.getByLabelText('N.º de comprobante'), '001-001-0000123')
    await user.type(screen.getByLabelText('Timbrado'), '17654321')
    await user.click(screen.getByRole('button', { name: 'Registrar' }))

    expect(create).toHaveBeenCalledWith(7, expect.objectContaining({ tax: 'vat', amount: 30000, certificate_number: '001-001-0000123' }))
  })

  it('sin sugerencia el importe queda vacío', () => {
    renderModal('0.00')
    expect(screen.getByLabelText('Importe retenido (Gs.)')).toHaveValue(null)
  })
})
