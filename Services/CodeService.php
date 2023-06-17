<?php

class CodeService{

    public static function getInvitationalCode(int $codeNumber, int $userId)
    {
        $verification = (new self()->verify($codeNumber, $userId));
    }


    /**
     * @param int $codeNumber
     * @param int $userId
     */
    private verify(int $codeNumber, int $userId)
    {

    }

    public static redeemCode(Cupone $cupon, int $userId)
    {
        /**
         * if user is `disertante` or `invitado` or if the `Cupon` 
         * the `Cupon` can be matched with a `Empresa` so, back then also validated if $cupon->has('Empresa')
         * 
         */

         /**  we can do a try catch here with custom exceptions... */
         $verification = (new self()->verify($cupon->numero, $userId));

         if ($verification){

         }
    }


}