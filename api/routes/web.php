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
define('HTTP_STATUS_NOT_FOUND', 404);
define('HTTP_STATUS_UNPROCESSABLE_ENTITY', 422);
date_default_timezone_set('America/Sao_Paulo');

function getClientDetails($id_client)
{
    return app('db')->select(
        'SELECT c.id, c.limite, s.saldo, NOW() AS data_atual FROM clientes c join saldos s ON c.id = s.cliente_id WHERE c.id = ?',
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
    if (!is_int((int)$id_client) || $id_client < 1 || !getClientDetails($id_client)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid client id'
        ], HTTP_STATUS_NOT_FOUND);
    }

    $transactions = app('db')->select('SELECT valor, tipo, descricao, realizada_em FROM transacoes WHERE cliente_id = ? order by realizada_em desc limit 10', [$id_client]);
    $founds = getClientDetails($id_client);
    return response()->json([
        'saldo' => [
            'total' => $founds[0]->saldo,
            'data_extrato' => $founds[0]->data_atual,
            'limite' => $founds[0]->limite,            
        ],
        'ultimas_transacoes' => $transactions
    ], 200);
});

$router->post('/clientes/{id_client}/transacoes', function ($id_client) {
    if (!is_int((int)$id_client) || $id_client < 1 || !getClientDetails($id_client)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid client id'
        ], HTTP_STATUS_NOT_FOUND);
    }
    
    $transactionData = getTransactionData($id_client);

    if (!validateTransactionData($transactionData)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid transaction data'
        ], HTTP_STATUS_UNPROCESSABLE_ENTITY);
    }

    $isLocked = acquireLock($id_client);

    if (!$isLocked) {
        $initWhile = 0;
        while (!$isLocked && $initWhile < 10) {
            $isLocked = acquireLock($id_client);
            sleep(1);
            $initWhile++;
        }

        if (!$isLocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client ID is locked'
            ], HTTP_STATUS_UNPROCESSABLE_ENTITY);
        }
    }

    $clientDetails = getClientDetails($id_client);
    $clientLimit = $clientDetails[0]->limite;
    $clientBalance = $clientDetails[0]->saldo;

    if ($transactionData['tipo'] == 'c') {
        $newBalance = $clientBalance + $transactionData['valor'];
        updateSaldo($id_client, $newBalance);
    } else {
        $availableBalance = $clientBalance - $transactionData['valor'];
        if ($availableBalance < returnNegative($clientLimit)) {
            releaseLock($id_client);
            return response()->json([
                'message' => 'Insufficient funds'
            ], HTTP_STATUS_UNPROCESSABLE_ENTITY);
        }
        updateSaldo($id_client, $availableBalance);
    }

    insertTransaction($id_client, $transactionData['tipo'], $transactionData['valor'], $transactionData['descricao']);
    releaseLock($id_client);

    return response()->json([
        'limite' => $clientLimit,
        'saldo' => getClientDetails($id_client)[0]->saldo
    ], 200);
});

function getTransactionData($id_client) {
    $transactionData = request()->all();

    if (!$transactionData['valor'] || !$transactionData['tipo'] || !$transactionData['descricao']) {
        return null;
    }

    return $transactionData;
}

function validateTransactionData($transactionData) {
    if (!is_numeric($transactionData['valor']) || $transactionData['valor'] < 0) {
        return false;
    }

    if (!in_array($transactionData['tipo'], ['c', 'd'])) {
        return false;
    }

    if (strlen($transactionData['descricao']) > 10 && strlen($transactionData['descricao']) < 1) {
        return false;
    }

    return true;
}

function acquireLock($id_client) {
    $islocked = app('db')->select('SELECT acquire_lock(?)', [$id_client]);

    return $islocked[0]->acquire_lock;
}

function releaseLock($id_client) {
    $isReleased = app('db')->select('SELECT release_lock(?)', [$id_client]);

    return $isReleased[0]->release_lock;
}