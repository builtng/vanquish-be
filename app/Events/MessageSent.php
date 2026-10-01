<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message->load(['fromUser', 'relatedClient', 'relatedConsultation', 'parentMessage.fromUser']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        if ($this->message->to_user_id) {
            $channels[] = new PrivateChannel('App.Models.User.' . $this->message->to_user_id);
            $channels[] = new PrivateChannel('messages.' . $this->message->to_user_id);
        }

        // If it involves a TC, broadcast to the staff group channel
        if ($this->message->to_tc_id || ($this->message->fromUser && $this->message->fromUser->training_counsellor_id)) {
            $channels[] = new PrivateChannel('messages.staff_group');
            
            // Also ensure the TC gets it on their personal channel
            if ($this->message->to_tc_id) {
                // Find TC's user record for their private channel
                $tcUser = \App\Models\User::where('training_counsellor_id', $this->message->to_tc_id)->first();
                if ($tcUser) {
                    $channels[] = new PrivateChannel('messages.' . $tcUser->id);
                }
            }
        }

        // Broadcast counsellor_to_staff messages to staff group so all staff see them
        if ($this->message->type === 'counsellor_to_staff') {
            $channels[] = new PrivateChannel('messages.staff_group');
        }
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
