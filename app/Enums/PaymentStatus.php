<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => '完了',
            self::Pending => '保留',
            self::Failed => '失敗',
            self::Refunded => '返金',
        };
    }
}
