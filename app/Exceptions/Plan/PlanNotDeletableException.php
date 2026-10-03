<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランマスタを削除しようとした際の例外（HTTP 409）。
 */
class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('下書き状態かつ受講生が紐づいていないプランのみ削除できます。', $previous);
    }
}
