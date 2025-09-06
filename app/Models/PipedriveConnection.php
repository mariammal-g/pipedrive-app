<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipedriveConnection extends Model
{
    protected $fillable = [
        'company_id','api_domain','pipedrive_user_id',
        'access_token','refresh_token','access_token_expires_at'
    ];

    protected $casts = [
        'access_token_expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return !$this->access_token_expires_at || $this->access_token_expires_at->isPast();
    }

    public function apiBase(): string
    {
        return rtrim($this->api_domain, '/') . '/v1';
    }
}
