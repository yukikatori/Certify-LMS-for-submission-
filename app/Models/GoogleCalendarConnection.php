<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Google Calendar 連携の Model。
 * コーチが自分の Google アカウントを LMS と任意連携する。
 * 
 * 関連: User
 */
class GoogleCalendarConnection extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'coach_id',
        'google_account_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'connected_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    public function getCalendarIdAttribute(): string
    {
        return $this->google_email ?? 'primary';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
