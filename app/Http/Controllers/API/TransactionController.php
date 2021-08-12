<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->id;
        $limit = $request->input('limit',6);
        $food_id = $request->food_id;
        $status = $request->types;

        if($id)
        {
            $transaction = Transaction::with(['food', 'user'])->find($id);
            if($transaction)
            {
                return ResponseFormatter::success(
                    $transaction,
                    'success get transaction data'
                );
            }
            else 
            {
                return ResponseFormatter::error(
                    null,
                    'data not found',
                    404
                );
            }
        }

        $transaction = Transaction::query();
        if($food_id)
        {
            $transaction->where('food_id', $food_id);
        }

        if($status)
        {
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'success get list transaction data'
        );
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success(
            $transaction,
            'success update transaction data'
        );
    }
}
