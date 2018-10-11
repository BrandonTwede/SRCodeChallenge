<?php

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
    return $router->app->version();
});

$router->get('/api/nodes', function (){


    //$users = DB::select('SHOW TABLES');
    //var_dump(DB::select('SELECT * FROM node'));
    #var_dump($tables);
//    foreach($tables as $table)
//    {
//        echo $table->Tables_in_db_name;
//    }



//    $flight = App\Node::find(1);
//
//    $flight->name = 'New Flight Name';
//
//    $flight->save();
    $result = array();
    $nodes = App\Node::all();
    $temp = array();
    foreach ($nodes as $node) {
        $temp['name'] = $node->name;
        $temp['id'] = $node->id;
        $temp['level'] = $node->level;
        $temp['lft'] = $node->lft;
        $temp['rgt'] = $node->rgt;
        $result[] = $temp;
    }
    return json_encode($result);
});

$router->get('/api/nodes/{id}', function ($id){

    $result = [];
    $node = App\Node::find($id);

    $result['name'] = $node->name;
    $result['id'] = $node->id;
    $result['level'] = $node->level;
    $result['lft'] = $node->lft;
    $result['rgt'] = $node->rgt;
    return json_encode($result);
});