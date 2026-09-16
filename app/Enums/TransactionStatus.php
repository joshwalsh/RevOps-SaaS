<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Success = 'success';
    case Fail = 'fail';
    case Refund = 'refund';
}
