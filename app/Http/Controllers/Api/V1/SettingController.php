<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'reminder_lead_minutes' => (int) $this->settings->get('reminder_lead_minutes', 0),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $lead = (int) $request->validated('reminder_lead_minutes');

        try {
            $this->settings->put('reminder_lead_minutes', (string) $lead);
            Log::info('Settings updated', ['reminder_lead_minutes' => $lead, 'action' => 'update']);

            return response()->json([
                'data' => ['reminder_lead_minutes' => $lead],
                'message' => 'Đã lưu cấu hình.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Settings update failed', ['action' => 'update', 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Không thể lưu cấu hình.'], 500);
        }
    }
}
