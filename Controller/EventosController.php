<?php
namespace Controller;
use Services\CongresoService;


class EventosController extends AppController{
    
    public function inscripcion(string $slug)
    {
        //El slug se crea a partir del nombre del congreso
        $evento = $this->Eventos->findBySlug($slug)->contain(['Etiquetas', 'Planes'])->first();
        //
        if (!$evento) {
            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->getSession()->check('Auth.User')) {
            $userId = $this->Auth->user('id');
        }

        if ($this->request->is('post')) {

            if ($evento->tipo == 'Congreso') {
                $response = CongresoService::register($evento, $userId, $this->request->getData());

                if ($response['errors']) {
                    $this->Flash->error(__($response['errors']['message']));
                    return $this->redirect($response['errors']['redirect']);
                }
            }
        }
    }
}