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
    $results = app('db')->select('SELECT * FROM clientes WHERE id = ?', [$id_client]);
    return response()->json([
        'id_client' => $id_client,
        'results' => $results
    ]);
});
