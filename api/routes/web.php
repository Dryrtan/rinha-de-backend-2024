<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    // Return ../resources/views/error.html
    return view('error');
});

$router->get('/clientes/{id_client}/extrato', function ($id_client) {
    $results = app('db')->select('SELECT limite FROM clientes WHERE id = ?', [$id_client]);

    return response()->json([
        'limite' => $results[0]->limite
    ]);
});

$router->post('/clientes/{id_client}/transacoes', function ($id_client) {
    $transaction_value = request('valor');
    $transaction_type = request('tipo');
    $transaction_descricao = request('descricao');

    $islocked = app('db')->select('SELECT acquire_lock(?)', [$id_client]);

    if ($islocked[0]->acquire_lock) {
        $client_details = app('db')->select(
            'SELECT c.id, c.limite, s.saldo FROM clientes c join saldos s ON c.id = s.cliente_id WHERE c.id = ?',
            [$id_client]
        );
        $client_limit = $client_details[0]->limite;
        $client_ammout = $client_details[0]->saldo;

        if ($transaction_type == 'c') {
            app('db')->update('UPDATE saldos SET saldo = saldo + ? WHERE cliente_id = ?', [$transaction_value, $id_client]);
        } else {
            $ammout_available = $client_limit - $transaction_value - ($client_ammout - ($client_ammout * 2));
            if ($ammout_available <= ($client_limit - ($client_limit * 2))) {
                return response()->json([
                    'mensage' => 'Este cliente não possui limite para este valor.'
                ], 422);
            }
            app('db')->update(
                'UPDATE saldos SET saldo = ? WHERE cliente_id = ?',
                [$client_ammout - $transaction_value, $id_client]
            );
        }

        app('db')->update("INSERT INTO transacoes (cliente_id, tipo, valor, descricao) VALUES ($id_client,'$transaction_type',$transaction_value,'$transaction_descricao')");
        app('db')->select('SELECT release_lock(?)', [$id_client]);

        return response()->json([
            'limite' => 0,
            'saldo' => 0,
            'teste' => $ammout_available
        ], 200);
    }

    return response()->json([
        'status' => 'error',
        'message' => $islocked[0]
    ], 512);
});
