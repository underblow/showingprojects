<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PushSendEvent extends Event
{
    use SerializesModels;

    public $user_ids;
    public $push_group_id;
    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user_ids, $push_group_id, $data = [])
    {
        $this->user_ids = $user_ids;
        $this->push_group_id = $push_group_id;
        $this->data = $data;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
