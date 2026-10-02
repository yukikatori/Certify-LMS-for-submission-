<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QaThreadNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('回答がついているスレッドは削除できません。', $previous);
    }
}
