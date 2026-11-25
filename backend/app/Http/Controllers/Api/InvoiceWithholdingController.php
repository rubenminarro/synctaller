<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPaymentRequest;
use App\Http\Requests\StoreInvoiceWithholdingRequest;
use App\Http\Resources\InvoiceWithholdingResource;
use App\Models\Invoice;
use App\Models\InvoiceWithholding;
use App\Services\InvoiceWithholdingService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Retenciones de IVA o renta sobre facturas a crédito (M12), en
 * invoices/{invoice}/withholdings (scopeBindings). Se consultan con el permiso
 * de ver facturas; registrarlas y anularlas es del cajero.
 */
class InvoiceWithholdingController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected InvoiceWithholdingService $withholdingService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:invoices.show', only: ['index', 'show']),
            new Middleware('permission:invoice-withholdings.store', only: ['store']),
            new Middleware('permission:invoice-withholdings.cancel', only: ['cancel']),
        ];
    }

    public function index(Invoice $invoice)
    {
        return $this->successResponse(
            'Retenciones obtenidas correctamente.',
            InvoiceWithholdingResource::collection($invoice->withholdings()->with(['registrar', 'canceller'])->get()),
            200
        );
    }

    public function store(StoreInvoiceWithholdingRequest $request, Invoice $invoice)
    {
        return $this->successResponse(
            'Retención registrada correctamente.',
            new InvoiceWithholdingResource($this->withholdingService->register($invoice, $request->validated(), $request->user())),
            201
        );
    }

    public function show(Invoice $invoice, InvoiceWithholding $withholding)
    {
        return $this->successResponse(
            'Retención obtenida correctamente.',
            new InvoiceWithholdingResource($this->withholdingService->load($withholding)),
            200
        );
    }

    public function cancel(CancelPaymentRequest $request, Invoice $invoice, InvoiceWithholding $withholding)
    {
        return $this->successResponse(
            'Retención anulada correctamente.',
            new InvoiceWithholdingResource($this->withholdingService->cancel($withholding, $request->validated()['reason'], $request->user())),
            200
        );
    }
}
