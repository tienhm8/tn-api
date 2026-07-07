<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerStatus;
use App\Enums\LostReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ChangeStatusRequest;
use App\Http\Requests\Customer\ImportCustomersRequest;
use App\Http\Requests\Customer\ReassignCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Repositories\Customer\CustomerRepositoryInterface;
use App\Services\CustomerImportService;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private CustomerRepositoryInterface $customers,
        private CustomerImportService $importService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'assigned_to', 'service_id', 'q']);
        $perPage = (int) $request->integer('per_page', 15);

        $paginator = $this->customers->paginateForUser($request->user(), $filters, $perPage);

        return CustomerResource::collection($paginator);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        try {
            $customer = $this->customerService->create($request->validated(), $request->user());
            Log::info('Customer created', ['customer_id' => $customer->id, 'action' => 'store', 'by' => $request->user()->id]);

            return (new CustomerResource($customer))->response()->setStatusCode(201);
        } catch (\Throwable $e) {
            Log::error('Customer create failed', ['action' => 'store', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể tạo khách hàng.'], 500);
        }
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($this->customers->findWithRelations($customer->id));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        try {
            $updated = $this->customerService->update($customer, $request->validated());
            Log::info('Customer updated', ['customer_id' => $customer->id, 'action' => 'update', 'by' => $request->user()->id]);

            return (new CustomerResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Customer update failed', ['customer_id' => $customer->id, 'action' => 'update', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể cập nhật khách hàng.'], 500);
        }
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        try {
            $this->customerService->delete($customer);
            Log::info('Customer deleted', ['customer_id' => $customer->id, 'action' => 'destroy']);

            return response()->json(['message' => 'Đã xóa khách hàng.']);
        } catch (\Throwable $e) {
            Log::error('Customer delete failed', ['customer_id' => $customer->id, 'action' => 'destroy', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể xóa khách hàng.'], 500);
        }
    }

    public function reassign(ReassignCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('reassign', $customer);

        try {
            $saleId = (int) $request->validated('sale_id');
            $updated = $this->customerService->reassign($customer, $saleId, $request->user());
            Log::info('Customer reassigned', ['customer_id' => $customer->id, 'sale_id' => $saleId, 'action' => 'reassign']);

            return (new CustomerResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Customer reassign failed', ['customer_id' => $customer->id, 'action' => 'reassign', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể gán lại khách hàng.'], 500);
        }
    }

    public function changeStatus(ChangeStatusRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('changeStatus', $customer);

        try {
            $status = CustomerStatus::from($request->validated('status'));
            $reason = $request->validated('lost_reason')
                ? LostReason::from($request->validated('lost_reason'))
                : null;

            $updated = $this->customerService->changeStatus($customer, $status, $reason, $request->user());
            Log::info('Customer status changed', ['customer_id' => $customer->id, 'status' => $status->value, 'action' => 'changeStatus']);

            return (new CustomerResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Customer status change failed', ['customer_id' => $customer->id, 'action' => 'changeStatus', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể đổi trạng thái.'], 500);
        }
    }

    public function import(ImportCustomersRequest $request): JsonResponse
    {
        try {
            $summary = $this->importService->import($request->file('file'), $request->user());
            Log::info('Customers imported', [
                'imported' => $summary['imported'],
                'failed' => $summary['failed'],
                'by' => $request->user()->id,
                'action' => 'import',
            ]);

            return response()->json($summary);
        } catch (\Throwable $e) {
            Log::error('Customers import failed', ['action' => 'import', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể import file.'], 500);
        }
    }

    public function template(): Response
    {
        return response($this->importService->buildTemplate(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="mau_import_khach_hang.xlsx"',
        ]);
    }
}
