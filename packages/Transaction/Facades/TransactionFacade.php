<?php

namespace iEXPackages\Transaction\Facades;

use iEXPackages\Transaction\Transaction;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Transaction find($id, $config = [])
 * @method static Transaction setId($id)
 * @method static Transaction call($id)
 * @method static Transaction init(\Illuminate\Database\Eloquent\Model|\App\Models\Task $task)
 * @method static string getType()
 * @method static float getCommission()
 * @method static string getPaymentName()
 * @method static bool hasNotActive()
 * @method static bool hasPreview()
 * @method static string hasAction()
 */
class TransactionFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'transaction';
    }
}
