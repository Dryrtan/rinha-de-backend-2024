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

date_default_timezone_set('America/Bahia'); //define timezone como da Bahia, pois esse estado não é afetado por horários de verao

function getClientDetails($id_client)
{
    return app('db')->select(
        'SELECT c.id, c.limite, s.saldo, DATE_FORMAT(realizada_em, "%d/%m/%Y %H:%i:%s") as dataAtual FROM clientes c join saldos s ON c.id = s.cliente_id WHERE c.id = ?',
        [$id_client]
    );
}

function getTransactionValue($id_client)
{
    return app('db')->select(
        'SELECT valor FROM transacoes WHERE cliente_id = ?',
        [$id_client]
    );
}

function updateSaldo($id_client, $new_saldo)
{
    app('db')->update(
        'UPDATE saldos SET saldo = ? WHERE cliente_id = ?',
        [$new_saldo, $id_client]
    );
}

function insertTransaction($id_client, $transaction_type, $transaction_value, $transaction_descricao)
{
    app('db')->insert(
        "INSERT INTO transacoes (cliente_id, tipo, valor, descricao) VALUES ($id_client,'$transaction_type',$transaction_value,'$transaction_descricao')"
    );
}

function returnNegative($value)
{
    return -abs($value);
}

$router->get('/', function () use ($router) {
    return view('error');
});

$router->get('/clientes/{id_client}/extrato', function ($id_client) {
    $transactions = app('db')->select('SELECT valor, tipo, descricao, realizada_em FROM transacoes WHERE cliente_id = ? order by realizada_em desc limit 10', [$id_client]);
    $founds = getClientDetails($id_client);
    return response()->json([
        // TODO: Get current date from database
        'saldo' => [
            'total' => $founds[0]->saldo,
            'data_extrato' => date('d/m/Y H:i:s'),
            'limite' => $founds[0]->limite,            
        ],
        'ultimas_transacoes' => $transactions
    ]);
});

$router->post('/clientes/{id_client}/transacoes', function ($id_client) {
    $transaction_value = request('valor');
    $transaction_type = request('tipo');
    $transaction_descricao = request('descricao');

    $islocked = app('db')->select('SELECT acquire_lock(?)', [$id_client]);

    if ($islocked[0]->acquire_lock) {
        $client_details = getClientDetails($id_client);
        $client_limit = $client_details[0]->limite;
        $client_ammout = $client_details[0]->saldo;

        if ($transaction_type == 'c') {
            updateSaldo($id_client, $client_ammout + $transaction_value);
        } else {
            $ammout_available = $client_ammout - $transaction_value;
            if ($ammout_available < returnNegative($client_limit)) {
                app('db')->select('SELECT release_lock(?)', [$id_client]);
                return response()->json([
                    'mensage' => 'Insufficient funds'
                ], 422);
            }
            updateSaldo($id_client, $client_ammout - $transaction_value);
        }

        insertTransaction($id_client, $transaction_type, $transaction_value, $transaction_descricao);
        app('db')->select('SELECT release_lock(?)', [$id_client]);

        return response()->json([
            'limite' => $client_limit,
            'saldo' => getClientDetails($id_client)[0]->saldo
        ], 200);
    }

    return response()->json([
        'status' => 'error',
        'message' => $islocked[0]
    ], 512);
});
