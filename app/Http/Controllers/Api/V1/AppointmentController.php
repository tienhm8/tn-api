<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\CompleteAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Customer;
use App\Repositories\Appointment\AppointmentRepositoryInterface;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService,
        private AppointmentRepositoryInterface $appointments,
    ) {}

    /**
     * Lịch chăm sóc đang chờ, chia rổ: quá hạn / hôm nay / ngày mai / sắp tới.
     */
    public function index(): JsonResponse
    {
        $buckets = $this->appointments->bucketsForUser(request()->user());

        return response()->json([
            'data' => [
                'overdue' => AppointmentResource::collection($buckets['overdue']),
                'today' => AppointmentResource::collection($buckets['today']),
                'tomorrow' => AppointmentResource::collection($buckets['tomorrow']),
                'upcoming' => AppointmentResource::collection($buckets['upcoming']),
            ],
            'counts' => [
                'overdue' => $buckets['overdue']->count(),
                'today' => $buckets['today']->count(),
                'tomorrow' => $buckets['tomorrow']->count(),
                'upcoming' => $buckets['upcoming']->count(),
            ],
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));
        $this->authorize('update', $customer);

        try {
            $appointment = $this->appointmentService->create($customer, $request->validated(), $request->user());
            Log::info('Appointment created', ['appointment_id' => $appointment->id, 'customer_id' => $customer->id, 'action' => 'store']);

            return (new AppointmentResource($appointment))->response()->setStatusCode(201);
        } catch (\Throwable $e) {
            Log::error('Appointment create failed', ['customer_id' => $customer->id, 'action' => 'store', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể đặt lịch.'], 500);
        }
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        try {
            $updated = $this->appointmentService->update($appointment, $request->validated(), $request->user());
            Log::info('Appointment updated', ['appointment_id' => $appointment->id, 'action' => 'update']);

            return (new AppointmentResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Appointment update failed', ['appointment_id' => $appointment->id, 'action' => 'update', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể cập nhật lịch.'], 500);
        }
    }

    public function complete(CompleteAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('complete', $appointment);

        try {
            $updated = $this->appointmentService->complete($appointment, $request->validated('outcome'), $request->user());
            Log::info('Appointment completed', ['appointment_id' => $appointment->id, 'action' => 'complete']);

            return (new AppointmentResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Appointment complete failed', ['appointment_id' => $appointment->id, 'action' => 'complete', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể hoàn thành lịch.'], 500);
        }
    }

    public function cancel(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        try {
            $updated = $this->appointmentService->cancel($appointment, request()->user());
            Log::info('Appointment cancelled', ['appointment_id' => $appointment->id, 'action' => 'cancel']);

            return (new AppointmentResource($updated))->response()->setStatusCode(200);
        } catch (\Throwable $e) {
            Log::error('Appointment cancel failed', ['appointment_id' => $appointment->id, 'action' => 'cancel', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể hủy lịch.'], 500);
        }
    }
}
