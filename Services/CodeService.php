<?php
use App\Model\Enum\PaymentFromEnum;
use App\Model\Enum\PayStatesEnum;
use App\Model\Enum\TagsByYearEnum;
use Models\Entity\Cupone;
use Services\CongresoService;
use Services\PayService;

class CodeService{

    private $table;
    private $userTable;
    public function __construct()
    {
        $this->table = TableRegistry::getTableLocator()->get('Cupones');
        $this->userTable = TableRegistry::getTableLocator()->get('Usuarios');
    }

    public static function getInvitationalCode(int $codeNumber, int $userId)
    {
        /* Busco el cupón y valido que no este asignado */
        $cupon = self::$table->find()
        ->contain(['Empresas', 'Eventos', 'Planes', 'UsuariosCupones'])
        ->where(['numero' => $codeNumber])
        ->where(['Eventos.tipo' => 'Congreso', 'YEAR(Eventos.created)' => date("Y")])
        ->first();

        if (is_null($cupon)) {
            /** vuelvo a verificar pero por tag*/
            $cupon = self::$table->find()
                ->contain(['Empresas', 'Eventos', 'Planes', 'UsuariosCupones'])
                ->where(['tag_nombre LIKE' => $codeNumber])
                ->where(['Eventos.tipo' => 'Congreso', 'YEAR(Eventos.created)' => date("Y")])
                ->first();

            /** Valido si el que ingresa el cupon es un invitado al congreso 
             * @todo validar esto con cliente, porque piden que el user no este logueado y aca se rompe todo 
             */
            if ($cupon) {
                if (!(new self())->isValidTagByYear($cupon->tag_nombre)) {
                    return 
                            [
                                'errors' => [
                                    'message' => __('El tag no es válido.'),
                                    'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date("Y")]
                                ]
                            ];
                }

                $cupon->fromTag = true;
            }
        }

      /** si no existe */
        if (is_null($cupon)){

            return 
            [
                'errors' => [
                    'message' => __('El cupón que estas intentando canjear no existe. Por favor, verifica los datos ingresados.'),
                    'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date("Y")],
                ]
            ];
        }

         return (new self())->verify($cupon, $userId);
  
    }


    /**
     * @param Cupone $cupone
     * @param int $userId
     */
    private function verify(Cupone $cupone, int $userId)
    {
        $redirection = ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date("Y")];        

        /** si fue utilizado */
        if (!is_null($cupone->usuario_id)){
            return 
            [
                'errors' => [
                    'message' => __('El cupón que estas intentando canjear ya fue utilizado. Por favor, verifica los datos ingresados.'),
                    'redirect' => $redirection,
                ]
            ];
        }

        /** si esta vencido */
        if (!is_null($cupone->fecha_vencimiento)) {
            if ($cupone->fecha_vencimiento->format('Ymd') < date('Ymd')) {
                return 
                [
                    'errors' => [
                        'message' => __('El cupón que estas intentando canjear caduco. Por favor, verifica los datos ingresados.'),
                        'redirect' => $redirection,
                    ]
                ];
            }
        }

        /** cuando es codigo de descuento */
        if ($cupone->porcentaje_descuento < 100){
            return 
            [
                'errors' => [
                    'message' => __('Ups. Esto es un código de descuento 😀. Insertalo en el carrito de compras.'),
                    'redirect' => $redirection,
                ]
            ];
        }

        /** cuando supera el tope de uso */
        if (!is_null($cupone->tope)) {

            if ($cupone->has('usuarios_cupones') && $cupone->tope <= count($cupone->usuarios_cupones)){                

                return 
                [
                    'errors' => [
                        'message' => __('Este cupón ya fue utilizado.'),
                        'redirect' => $redirection,
                    ]
                ];                
            }
        }

        /** cuando el usuario ya canjeo codigo */
        if ($hasCupon = self::$table->Usuarios->getUltimoCupon($userId)) {
            if ($hasCupon->porcentaje_descuento == 100) {

                return 
                [
                    'errors' => [
                        'message' => __('Esta cuenta ya canjeo un código.'),
                        'redirect' => $redirection,
                    ]
                ];    
            }
        }

        /** cuando finalizo la etapa de inscripcion */
        if (!empty($cupone->plane) AND (strtolower($cupone->plane->nombre) == 'Presencial')) {
            if ($cupone->plane->fecha_fin_2->format('YmdHi') < date('YmdHi')) {

                return 
                [
                    'errors' => [
                        'message' => __('Ups. La inscripción con entradas presenciales por este medio finalizó. Acercate a partir del día martes 9/08 a Metropolitano, Rosario para finalizar tu inscripción'),
                        'redirect' => $redirection,
                    ]
                ];   
            }
        }

        return $cupone;
    }

    public static function redeemCode(Cupone $cupon, int $userId, array $formInformation)
    {
        $user = self::$userTable->get($userId);

         /**  we can do a try catch here with custom exceptions... */
         $verification = (new self())->verify($cupon->numero, $userId);

         if ($verification){

             $user->disertante = self::$userTable->Usuarios->Disertantes->findByUsuarioId($userId)
                    ->innerJoinWith('Congresos')
                    ->where(['Congresos.tipo' => 'Congreso', 'YEAR(Congresos.fecha_inicio)' => date('Y')])->order(['Disertantes.created' => 'DESC'])
                    ->first();
    
            $user->invitado = self::$userTable->Usuarios->Invitados->findByUsuarioId($userId)
                    ->innerJoinWith('Eventos')
                    ->where(['Eventos.tipo' => 'Congreso', 'YEAR(Eventos.fecha_inicio)' => date('Y')])->order(['Invitados.created' => 'DESC'])
                    ->first();
    
            if (!empty($user->disertante) OR !empty($user->invitado) OR !empty($cupon)) {
               
                $inscription = CongresoService::prepareToSave();

                if (!QrService::generateQr($inscription['inscription']->nro_inscripcion)){
    
                    return [
                        'errors' => [
                            'message' => __('No se pudo generar la inscripción.'),
                            'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                        ]
                    ];
                }
    
                $exchange = PayService::saveNewPay($userId, $cupon->plan_id, PaymentFromEnum::CUPON, 0);
    
                if (!$exchange) {
    
                    return [
                            'errors' => [
                                'message' => __('No se pudo canjear el ticket, consulte con la plataforma.'),
                                'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                            ]
                        ];
                }
    
                $newMovement = [
                    'evento_id' => $cupon->evento_id,
                    'usuario_id' => $userId,
                    'entidad_id' => $cupon->id,
                    'tabla' => 'Cupones',
                    'alta' => $cupon->plane->cantidad_charlas,
                    'descripcion' => 'Canje de cupon',
                ];
    
                // $response = $this->movTable->nuevo($newMovement);
    
                // if ($response){
                    /** se valida si viene el cupon por tag */
                    // if ($tagName) {
                    //     $this->cuponesTable->UsuariosCupones->link($cupon, [$user]);
                    // }else{
                    //     $cupon->usuario_id = $userId;
                    //     $this->cuponesTable->save($cupon);
                    // }
    
                    // Si el cupón lo canjea desde una empresa, lo asigno como empleado de esa empresa.
                    // if ($cupon->has('empresa')) {                   
                    //     $relacion = $this->userCompaniesTable->newEntity();
                    //     $relacion->empresa_id = $cupon->empresa->id;
                    //     $relacion->usuario_id = $userId;
                    //     $relacion->rol ='empleado';
                    //     $relacion->created = date('Y-m-d H:i:s');
    
                    //     if (!$this->userCompaniesTable->save($relacion)){
                    //         return [
                    //             'errors' => [
                    //                 'message' => __('No pudimos cargar tu cupón. Por favor, contacta con un administrador.'),
                    //                 'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                    //             ],
                    //         ];   
                    //     }
    
                    // }
    
                    /**
                     * return with the variable $onlyView = true
                     */
                //     return [
                //         'success' => [
                //             'message'     => __('Canjeaste con éxito tu cupón.'),
                //             'redirect'    => ['controller' => 'Eventos', 'action' => 'inscripcionConfirmada', true]
                //         ],                    
                //     ];
    
                // }else{
                //     return [
                //         'errors' => [
                //             'message' => __('No pudimos cargar tu cupón. Por favor, contacta con un administrador.'),
                //             'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                //         ],
                //     ];              
                // }
            } else {
    
                return [
                    'errors' => [
                        'message' => __('No se puede usar el cupón. Por favor, verifica los datos ingresados.'),
                        'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                    ],
                ]; 
            }
         }
    }

    /** return the tags by the current year
     * @param string $tag
    */
    private function isValidTagByYear(string $tag)
    {
        $currentYearTags = TagsByYearEnum::getOptions()[date("Y")];

        if (empty($currentYearTags)) return false;

        return (in_array($tag,$currentYearTags));
    }
}