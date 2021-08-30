<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Facade\FlareClient\Http\Response;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;

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

    public function checkout(Request $request)
    {
        $request->validate([
            'food_id' => ['required', 'exists:food,id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required'],
            'total' => ['required'],
            'status' => ['required'],
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => $request->payment_url,
        ]);

        // Konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Mebuat Transaksi Midtrans
        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        // Memanggil Midtrans
        $midtrans = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => $transaction->total,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => []
        ];

        // Ambil Halaman Payment Midtrans 
        try {
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;
            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            // Mengembalikan Data ke API
            return ResponseFormatter::success(
                $transaction, 
                'transaction success'
            );
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 'transacrion failed');
        }
    }
}
