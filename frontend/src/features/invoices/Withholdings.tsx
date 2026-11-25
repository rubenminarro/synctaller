import { useState } from 'react'
import { IconBan, IconReceiptTax } from '@tabler/icons-react'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { SelectField, TextField } from '@/components/ui/FormField'
import { FormModal } from '@/components/ui/FormModal'
import { StatusBadge } from '@/components/ui/StatusBadge'
import { useAuth } from '@/features/auth/useAuth'
import {
  WITHHOLDING_STATUS,
  WITHHOLDING_TAXES,
  withholdingInput,
  withholdingsApi,
  type Invoice,
  type Withholding,
  type WithholdingTax,
} from '@/features/invoices/api'
import { formatDate, formatDateTime, formatGuarani, todayInput } from '@/lib/format'
import { useApiMutation } from '@/lib/useApiMutation'
import { validationErrors } from '@/services/api'

/**
 * Retenciones de IVA o renta que el cliente practicó al pagar una factura a crédito (M12). Se registra el comprobante
 * de retención tal como se emitió; cancela saldo sin movimiento de caja. Un registro mal cargado se anula con motivo.
 */
export function WithholdingsCard({ invoice }: { invoice: Invoice }) {
  const { can } = useAuth()
  const [registering, setRegistering] = useState(false)
  const [cancelling, setCancelling] = useState<Withholding | null>(null)
  const withholdings = invoice.withholdings ?? []
  const withholdable = invoice.status.value === 'issued' && invoice.condition.value === 'credit'
  const canRegister = withholdable && Number(invoice.payment_summary.balance) > 0 && can('invoice-withholdings.store')

  const cancel = useApiMutation((reason: string) => withholdingsApi.cancel(invoice.id, cancelling?.id ?? 0, reason), {
    invalidate: [['invoices'], ['work-orders'], ['payments']],
    successMessage: 'Retención anulada.',
    onSuccess: () => setCancelling(null),
  })

  if (withholdings.length === 0 && !canRegister) return null

  return (
    <div className="card mt-3">
      <div className="card-header">
        <h3 className="card-title">Retenciones</h3>
        {canRegister && (
          <div className="card-actions">
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setRegistering(true)}>
              <IconReceiptTax size={16} className="me-1" aria-hidden="true" />
              Registrar retención
            </button>
          </div>
        )}
      </div>
      {withholdings.length === 0 ? (
        <div className="card-body text-secondary small">
          Sin retenciones. Si el cliente retuvo IVA o renta al pagar, registre su comprobante de retención.
        </div>
      ) : (
        <div className="list-group list-group-flush">
          {withholdings.map((withholding) => (
            <div key={withholding.id} className="list-group-item">
              <div className="d-flex flex-wrap align-items-center gap-2">
                <strong>{withholding.tax.label}</strong>
                <span>{withholding.certificate_number}</span>
                <span className="small text-secondary">timbrado {withholding.certificate_stamp_number}</span>
                <span className="small text-secondary">{formatDate(withholding.withholding_date)}</span>
                <StatusBadge value={withholding.status.value} map={WITHHOLDING_STATUS} />
                <span className="ms-auto fw-bold">{formatGuarani(withholding.amount)}</span>
                {withholding.status.value === 'registered' && can('invoice-withholdings.cancel') && (
                  <button type="button" className="btn btn-sm btn-ghost-danger" onClick={() => setCancelling(withholding)}>
                    <IconBan size={16} className="me-1" aria-hidden="true" />
                    Anular
                  </button>
                )}
              </div>
              <div className="small text-secondary mt-1">
                Registrada {formatDateTime(withholding.created_at)}
                {withholding.registered_by && ` por ${withholding.registered_by.name}`}
              </div>
              {withholding.status.value === 'cancelled' && (
                <div className="small text-red">
                  Anulada {formatDateTime(withholding.cancelled_at)}
                  {withholding.cancelled_by && ` por ${withholding.cancelled_by.name}`}: {withholding.cancellation_reason}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {registering && <WithholdingFormModal invoice={invoice} onClose={() => setRegistering(false)} />}
      <ConfirmDialog
        open={cancelling !== null}
        title="Anular retención"
        message={
          <>
            ¿Anular la retención de {cancelling?.tax.label} del comprobante <strong>{cancelling?.certificate_number}</strong>?
            El saldo de la factura vuelve a incluir lo retenido. Es definitivo.
          </>
        }
        confirmLabel="Anular"
        variant="danger"
        reason={{ label: 'Motivo de la anulación' }}
        isLoading={cancel.isPending}
        error={cancel.error}
        onConfirm={(reason) => cancel.mutate(reason ?? '')}
        onClose={() => setCancelling(null)}
      />
    </div>
  )
}

export function WithholdingFormModal({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const suggestion = invoice.payment_summary.vat_withholding_suggestion
  const suggested = suggestion !== null && Number(suggestion) > 0 ? String(Math.round(Number(suggestion))) : ''
  const [values, setValues] = useState({
    tax: 'vat' as WithholdingTax,
    amount: suggested,
    certificate_number: '',
    certificate_stamp_number: '',
    withholding_date: todayInput(),
  })
  const balance = Number(invoice.payment_summary.balance)
  const amount = Number(values.amount)

  const mutation = useApiMutation(() => withholdingsApi.create(invoice.id, withholdingInput(values)), {
    invalidate: [['invoices'], ['work-orders'], ['payments']],
    successMessage: 'Retención registrada.',
    onSuccess: onClose,
  })
  const errors = validationErrors(mutation.error)
  const set = (field: keyof typeof values) => (event: { target: { value: string } }) =>
    setValues((current) => ({ ...current, [field]: event.target.value }))

  return (
    <FormModal
      title={`Registrar retención · ${invoice.number}`}
      onClose={onClose}
      onSubmit={() => mutation.mutate()}
      isSubmitting={mutation.isPending}
      submitLabel="Registrar"
    >
      <p className="text-secondary">
        Cargue el comprobante de retención tal como lo emitió el cliente, hasta el saldo de la factura ({formatGuarani(balance)}).
        Si un mismo comprobante cubre varias facturas, regístrelo en cada una con su importe. No es un cobro de caja.
      </p>
      <div className="row">
        <div className="col-12 col-md-6">
          <SelectField label="Impuesto" name="tax" required value={values.tax} onChange={set('tax')} errors={errors}>
            {(Object.keys(WITHHOLDING_TAXES) as WithholdingTax[]).map((tax) => (
              <option key={tax} value={tax}>
                {WITHHOLDING_TAXES[tax]}
              </option>
            ))}
          </SelectField>
        </div>
        <div className="col-12 col-md-6">
          <TextField
            label="Importe retenido (Gs.)"
            name="amount"
            type="number"
            required
            min={1}
            max={balance}
            step={1}
            value={values.amount}
            onChange={set('amount')}
            errors={errors}
            hint={values.tax === 'vat' && suggested !== '' ? `Sugerido según el IVA de la factura: ${formatGuarani(suggested)}. Cargue el del comprobante.` : undefined}
          />
        </div>
        <div className="col-12 col-md-6">
          <TextField label="N.º de comprobante" name="certificate_number" required maxLength={30} value={values.certificate_number} onChange={set('certificate_number')} errors={errors} />
        </div>
        <div className="col-12 col-md-6">
          <TextField label="Timbrado" name="certificate_stamp_number" required maxLength={30} value={values.certificate_stamp_number} onChange={set('certificate_stamp_number')} errors={errors} />
        </div>
        <div className="col-12 col-md-6">
          <TextField label="Fecha" name="withholding_date" type="date" required max={todayInput()} value={values.withholding_date} onChange={set('withholding_date')} errors={errors} />
        </div>
      </div>
      {amount > balance && <div className="text-red small">Supera el saldo de la factura.</div>}
    </FormModal>
  )
}
