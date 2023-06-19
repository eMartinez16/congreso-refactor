<?php 

namespace App\Model\Enum;

class TagsByYearEnum {
    

    public static function getOptions()
    {
        return 
            [
                '2022' => [ 
                    'disertantesaapresid2022', 
                    'invitadoespecial2022', 
                    'prensavirtual2022', 
                    'prensapresencial2022',
                    'autoridades2022',
                    'invitadoespecialvirtual2022'
                ],
                '2023' => [
                    'disertantes2023',
                    'autoridades2023',
                    'invitadoespecial2023',
                    'invitadoespecialvirtual2023',
                    'prensapresencial2023',
                    'prensavirtual2023'
                ], 
            ];
    }
    
}