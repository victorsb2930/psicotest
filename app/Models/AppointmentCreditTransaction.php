<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentCreditTransaction extends Model
{
    use HasFactory;

    protected $table = 'appointment_credit_transactions';
    protected $fillable = ['user_id', 'amount', 'meta'];

    protected $casts = [
        'meta' => 'array'
    ];

    // Get the current balance (sum of amounts) for a user
    public static function getBalanceForUser(int $userId): int
    {
        return (int) self::where('user_id', $userId)->sum('amount');
    }

    // Create a purchase (positive amount)
    public static function createPurchase(int $userId, int $amount = 1, array $meta = [])
    {
        return self::create(['user_id' => $userId, 'amount' => (int)$amount, 'meta' => $meta ?: null]);
    }

    // Consume credits (negative amount). Throws exception if insufficient funds.
    public static function consumeCredits(int $userId, int $amount = 1, array $meta = [])
    {
        $amount = (int)$amount;
        if ($amount <= 0) throw new \InvalidArgumentException('amount must be positive');
        $balance = self::getBalanceForUser($userId);
        if ($balance < $amount) throw new \RuntimeException('insufficient_credits');
        return self::create(['user_id' => $userId, 'amount' => -$amount, 'meta' => $meta ?: null]);
    }
}
