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

/**
 * Retrieve the transaction value for a given client ID from the database.
 *
 * @param int $id_client The ID of the client
 * @throws DatabaseException If there is an error with the database query
 * @return array The transaction value for the client
 */
function getTransactionValue($id_client)
{
    return app('db')->select(
        'SELECT valor FROM transacoes WHERE cliente_id = ?',
        [$id_client]
    );
}


function updateSaldo($amount, $id_client, $description, $transaction_type)
{
    $result = app('db')->select(
        'SELECT executar_transacao(?, ?, ?, ?)',
        [(int)$amount, (int)$id_client, (string)$description, (string)$transaction_type]
    );

    return $result;
}

/**
 * Inserts a transaction into the database.
 *
 * @param datatype $id_client description
 * @param datatype $transaction_type description
 * @param datatype $transaction_value description
 * @param datatype $transaction_descricao description
 * @throws Some_Exception_Class description of exception
 * @return Some_Return_Value
 */
function insertTransaction($id_client, $transaction_type, $transaction_value, $transaction_descricao)
{
    app('db')->insert(
        "INSERT INTO transacoes (cliente_id, tipo, valor, descricao) VALUES ($id_client,'$transaction_type',$transaction_value,'$transaction_descricao')"
    );
}

/**
 * Returns the negative value of the input.
 *
 * @param int|float $value The input value
 * @return int|float The negative value of the input
 */
function returnNegative($value)
{
    return -abs($value);
}

$router->get('/', function () use ($router) {
    return view('error');
});


/**
 * Retrieves transaction data for a given client.
 *
 * @param int $id_client The ID of the client
 * @return array|null The transaction data if valid, null otherwise
 */
function getTransactionData($id_client)
{
    $transactionData = request()->all();

    if (!$transactionData['valor'] || !$transactionData['tipo'] || !$transactionData['descricao']) {
        return null;
    }

    return $transactionData;
}

/**
 * Validates transaction data.
 *
 * @param array $transactionData The transaction data to validate
 * @return bool
 */
function validateTransactionData($transactionData)
{
    if (!isset($transactionData['valor']) || !is_numeric($transactionData['valor']) || $transactionData['valor'] < 0 || !is_int($transactionData['valor'])) {
        return false;
    }

    if (!isset($transactionData['tipo']) || !in_array($transactionData['tipo'], ['c', 'd'])) {
        return false;
    }

    if (!isset($transactionData['descricao']) || strlen($transactionData['descricao']) > 10 || strlen($transactionData['descricao']) < 1) {
        return false;
    }

    return true;
}

/**
 * Acquires a lock for the given client ID.
 *
 * @param int $id_client The ID of the client
 * @throws Some_Exception_Class Description of exception
 * @return bool The result of the lock acquisition
 */
function acquireLock($id_client)
{
    $islocked = app('db')->select('SELECT acquire_lock(?)', [$id_client]);

    return $islocked[0]->acquire_lock;
}

/**
 * Releases a lock for the given client ID.
 *
 * @param int $id_client The ID of the client
 * @throws Some_Exception_Class Description of exception
 * @return bool The result of releasing the lock
 */
function releaseLock($id_client)
{
    $isReleased = app('db')->select('SELECT release_lock(?)', [$id_client]);

    return $isReleased[0]->release_lock;
}

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

    $clientDetails = getClientDetails($id_client);
    $clientLimit = $clientDetails[0]->limite;

    $Exec_updateMoney = updateSaldo((int)$transactionData['valor'], (int)$id_client, $transactionData['descricao'], $transactionData['tipo']);

    if ($Exec_updateMoney === 'Limite ultrapassado') {
        return response()->json([
            'status' => 'error',
            'message' => 'Limite ultrapassado'
        ], HTTP_STATUS_UNPROCESSABLE_ENTITY);
    } else if ($Exec_updateMoney === 'Erro na transação') {
        while ($Exec_updateMoney !== 'Transação concluída' && $Exec_updateMoney !== 'Limite ultrapassado') {
            $Exec_updateMoney = updateSaldo((int)$transactionData['valor'], (int)$id_client, $transactionData['descricao'], $transactionData['tipo']);
        }
        if ($Exec_updateMoney === 'Limite ultrapassado') {
            return response()->json([
                'status' => 'error',
                'message' => 'Limite ultrapassado'
            ], HTTP_STATUS_UNPROCESSABLE_ENTITY);
        }
    }

    return response()->json([
        'limite' => $clientLimit,
        'saldo' => getClientDetails($id_client)[0]->saldo
    ], 200);
});

$router->get('/teste', function () {
    return response()->json([
        'status' => 'ok'
    ], 200);
});
