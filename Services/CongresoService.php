<?php

namespace Services;
use Models\Entity\CongresoInscripcione;
use Models\Entity\Evento;
use Models\Entity\EventosUsuario;
use QrService;
class CongresoService{

    private $congresoTable;
    private $eventUserTable;

    public function __construct()
    {
        $this->congresoTable = TableRegistry::getTableLocator()->get('CongresoInscripciones');
        $this->eventUserTable = TableRegistry::getTableLocator()->get('EventosUsuarios');
    }

    public static function register(Evento $event, int $userId, array $formData, bool $isSocio = false)
    {
        $eventosTable = TableRegistry::getTableLocator()->get('Eventos');
        $usersTable = TableRegistry::getTableLocator()->get('Usuarios');

        $user = $usersTable->find();

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

        }else{
            $user = $user->where([
                'email' => $formData['email']
                ])
                ->first();
            
        }

        // Verificando TOPE
        if (!empty($evento->tope_inscriptos)) {
            $totalRegistered = $eventosTable->getInscriptos($evento->id)->count();

            if ($evento->tope_inscriptos <= $totalRegistered) {

                return  [
                    'errors' => [
                        'message' => 'El evento llego al tope de inscriptos. Disculpe la molestia, puede inscribirse a otros de nuestros eventos.',
                        'redirect' => ['controller' => 'Congreso', 'action' => 'getCurrentCongreso', date('Y')]
                    ],    
                ];
            }
        }

        $inscription = CongresoService::prepareToSave();

        if (!QrService::generateQr($inscription['inscription']->nro_inscripcion)){

            return  [
                'errors' => [
                    'message'  => 'No se pudo generar la inscripción.',
                    'redirect' => 'referer',
                ]
            ];
        }
        
        
        /** generate `pago` */

        /** IE = Inscripcion Evento */
        $notification = [
            'usuario_id' => $user->id,
            'remitente_id' => $user->id,
            'mensaje' => "Te inscribiste al evento " . $event->titulo . '.',
            'link' => Router::url(['controller' => 'Eventos', 'action' => 'ver', $event->slug], true),
            'motivo' => 'IE'
        ];

        $notifications = TableRegistry::getTableLocator()->get('Notificaciones');
        $notificacion = $notifications->newEntity([$notification]);
        $notifications->save($notificacion);

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

        } catch (\Throwable $th) {
            throw $th;
        }
    }

    
}