<?php

namespace Services;
use App\Model\Enum\PaymentFromEnum;
use App\Model\Enum\PayStatesEnum;
use App\Model\Enum\PlanesEnum;
use Models\Entity\CongresoInscripcione;
use Models\Entity\Evento;
use Models\Entity\EventosUsuario;
use QrService;
class CongresoService{

    private $congresoTable;
    private $eventUserTable;

    private $notifications;
    private $movTable;

    public function __construct()
    {
        $this->congresoTable = TableRegistry::getTableLocator()->get('CongresoInscripciones');
        $this->eventUserTable = TableRegistry::getTableLocator()->get('EventosUsuarios');
        $this->notifications = TableRegistry::getTableLocator()->get('Notificaciones');
        $this->movTable = TableRegistry::getTableLocator()->get('movimientos_charlas');
    }

    public static function register(Evento $event, int $userId, array $formData, bool $isSocio = false, bool $fromCode = false)
    {
        $eventsTable = TableRegistry::getTableLocator()->get('Eventos');
        $usersTable = TableRegistry::getTableLocator()->get('Usuarios');

        $user = $usersTable->find();
        /**
         * this can be: `Presencial`, `Virtual Full` or `Virtual Basic`
         * @var null|PlanesEnum $typeTicket
         */
        $typeTicket = null;

        /**
         * @var null|PaymentFromEnum
         */
        $from = null;

        /**
         * @var null|PayStatesEnum
         */
        $status = null;

        $total = 0;

        /** if is a `socio`, we check if has debit  */
        if ($isSocio){
            $user = $user->contain(['Socios'])
                    ->where([
                        'OR' => [
                            ['Socios.nro_socio' => $formData['nro_socio']], 
                            ['Socios.nro_ident' => $formData['nro_ident']]
                        ]
                    ])
                    ->first();

            if ($user && $user->has_debit) {
                return  [
                    'errors' => [
                        'message' => 'Tu inscripción no pudo realizarse. Por favor comunicarse con atencionsocios@aapresid.org.ar o al 341 3540276.',
                        'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                    ],    
                    
                ];
            }

            $typeTicket = PlanesEnum::PRESENCIAL;
            $from = PaymentFromEnum::SOCIO;
            $status = PayStatesEnum::APPROVED;
        }else{
            $user = $user->where([
                'email' => $formData['email']
                ])
                ->first();
            
        }

        // Verificando TOPE
        if (!empty($event->tope_inscriptos)) {
            $totalRegistered = $eventsTable->getInscriptos($event->id)->count();

            if ($event->tope_inscriptos <= $totalRegistered) {

                return  [
                    'errors' => [
                        'message' => 'El evento llego al tope de inscriptos. Disculpe la molestia, puede inscribirse a otros de nuestros eventos.',
                        'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                    ],    
                ];
            }
        }

        $inscription = self::prepareToSave();

        if ($fromCode) {
            /** the code has the `plan_id` field, so in order to do this, we need to pass it when call this fn */
            $typeTicket = $formData['planId'] ?: PlanesEnum::PRESENCIAL;        
            $from = PaymentFromEnum::CUPON;
            $status = PayStatesEnum::APPROVED;
        }

        /** we need to send the inscriptionType or if is from code or socio, we get the type there 
         * @var int $planId
        */
        $planId = $typeTicket ?: $formData['inscriptionType'];

        /** @todo implement MP and PayPal logic here */

        $pay = PayService::saveNewPay(
                $userId, 
                $planId,
                $from,
                $total,
                $status
                );

        if (!QrService::generateQr($inscription['inscription']->nro_inscripcion)){

            return  [
                'errors' => [
                    'message'  => 'No se pudo generar la inscripción.',
                    'redirect' => 'referer',
                ]
            ];
        }
        
    

        return [
            'success' => [
                'message'  => 'Su inscripción se realizó con éxito.',
                'redirect' => ['controller' => 'Eventos', 'action' => 'estasInscripto']
            ]
        ];
    }


    /**
     * must return a CongresoInscripcion entity...
     */
    public static function prepareToSave()
    {
       $inscription = self::$congresoTable->newEntity();

       $inscription->nro_inscripcion = uniqid();

       $eventUser = self::$eventUserTable->newEntity();

       return [
        'inscription' => $inscription,
        'event_user'  => $eventUser,
       ];
    }


    private function save(CongresoInscripcione $inscriptionEntity, EventosUsuario $eventUserEntity)
    {
        /** we need 
         * EventosUsuarios [evento_id, user_id]
         * CongresoInscripciones [ data de alojamiento (noches, comentarios), user_id, evento_id]
         */

        try {
            $inscriptionEntity->save();
            $eventUserEntity->save();

            $currentEvent = $this->eventUserTable->get($inscriptionEntity->evento_id);

             /** IE = Inscripcion Evento */
            $notification = [
                'usuario_id' => $inscriptionEntity->usuario_id,
                'remitente_id' => $inscriptionEntity->usuario_id,
                'mensaje' => "Te inscribiste al evento " . $currentEvent->titulo . '.',
                'link' => Router::url(['controller' => 'Eventos', 'action' => 'ver', $currentEvent->slug], true),
                'motivo' => 'IE'
            ];

            /** check this.. */
            $notificacion = $this->notifications->newEntity([$notification]);
            $this->notifications->save($notificacion);


            $movement = $this->movTable->newEntity();
            $newMovement = [ 'evento_id' => $inscriptionEntity->evento_id,
                'usuario_id' => $inscriptionEntity->usuario_id,
                'entidad_id' => $inscriptionEntity->id,
                'tabla' => 'CongresoInscripciones',
                'alta' => 0,
                'descripcion' => 'Inscr a evento'
            ];

            $movement = $this->movTable->patchEntity($movement, $newMovement);

            $this->movTable->save($movement);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    
}