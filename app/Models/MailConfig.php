<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailConfig extends Model
{
    protected $fillable = [
        'user_id', 'email', 'username', 'password',
        'incoming_host', 'incoming_port', 'incoming_protocol', 'incoming_security',
        'outgoing_host', 'outgoing_port', 'outgoing_security', 'linked_at',
    ];

    public function toApi(): array
    {
        return [
            'email' => $this->email,
            'username' => $this->username,
            'incoming_host' => $this->incoming_host,
            'incoming_port' => $this->incoming_port,
            'incoming_protocol' => $this->incoming_protocol,
            'incoming_security' => $this->incoming_security,
            'outgoing_host' => $this->outgoing_host,
            'outgoing_port' => $this->outgoing_port,
            'outgoing_security' => $this->outgoing_security,
            'linked_at' => $this->linked_at,
        ];
    }
}