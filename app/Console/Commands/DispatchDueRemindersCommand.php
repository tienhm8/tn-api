<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class DispatchDueRemindersCommand extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Gửi nhắc lịch cho các cuộc hẹn chăm sóc đến hạn (theo reminder_lead_minutes)';

    public function handle(ReminderService $reminders): int
    {
        $count = $reminders->dispatchDue();
        $this->info("Đã nhắc {$count} lịch đến hạn.");

        return self::SUCCESS;
    }
}
