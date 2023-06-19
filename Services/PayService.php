<?php

namespace Services;
class PayService{

    private $table;
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('Pagos');
    }

    /**
     * @param int $userId
     * @param int $planId
     * @param string $from 
     * @param float $total ->default 0
     */
    public static function saveNewPay(int $userId, int $planId, string $from, float $total = 0)
    {
        try {
            $newPay = self::$table->newEntity();
    
            $newPay->usuario_id = $userId;
            $newPay->plan_id = $planId;
            $newPay->monto = $total;
            $newPay->estado = 'aprobado';
            $newPay->origen = $from;
    
            return self::$table->save($newPay);        
        } catch (\PaymentException $th) {
            throw $th;
        }
    }
}