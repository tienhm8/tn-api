<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CustomerAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Customer $customer,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer_assigned',
            'customer_id' => $this->customer->id,
            'customer_code' => $this->customer->code,
            'company_name' => $this->customer->company_name,
            'message' => 'Bạn được giao khách hàng mới: '.$this->customer->company_name,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
