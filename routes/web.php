<?php

use Illuminate\Http\Request;

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

$router->get('/api', function(){
    var_dump(DB::select('SELECT * FROM node'));
});

//Returns a JSON structure representing nodes and their nested structure
$router->get('/api/nodes', function (){
    try {
        $result = array();
        $nodes = App\Node::where('level', 0)->get();
        $temp = array();
        foreach ($nodes as $node) {
            $temp['name'] = $node->name;
            $temp['id'] = $node->id;
            $temp['level'] = $node->level;
            $temp['children'] = buildChildren($node->lft, $node->rgt, $node->level + 1);
            $result[] = $temp;
        }
        return response(json_encode($result), 200)->header('Content-Type', 'application/json');
    }
    catch (Exception $e){
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

function buildChildren($lft, $rgt, $level) {
    $children = App\Node::where([
        ['lft', '>', $lft],
        ['lft', '<', $rgt],
        ['level', '=', $level]
    ])->orderBy('lft', 'asc')->get();
    if (count($children) == 0) return [];
    $result = [];
    $temp = array();
    foreach ($children as $node){
        $temp['name'] = $node->name;
        $temp['id'] = $node->id;
        $temp['level'] = $node->level;
        $temp['children'] = buildChildren($node->lft, $node->rgt, $node->level + 1);
        $result[] = $temp;
    }
    return $result;
}

//Gets info about a single node
$router->get('/api/nodes/{id}', function ($id){
    try {
        $result = [];
        $node = App\Node::find($id);
        if ($node != null) {
            $result['name'] = $node->name;
            $result['id'] = $node->id;
            $result['level'] = $node->level;
            return response(json_encode($result), 200)->header('Content-Type', 'application/json');
        } else {
            return response(json_encode(['error'=>"Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
        }
    }
    catch (Exception $e){
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

//Gets all children of a node
$router->get('/api/nodes/{id}/children', function($id){
    try {
        $node = App\Node::find($id);
        if ($node != null) {
            $result = buildChildren($node->lft, $node->rgt, $node->level + 1);
            return response(json_encode($result), 200)->header('Content-Type', 'application/json');
        } else {
            return response(json_encode(['error'=>"Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
        }
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

//Update endpoint for editing node properties (in this case, only the 'name' property, because the rest
// of the properties are related to node position and are managed by the children endpoint)
$router->put('/api/nodes/{id}', function($id, Request $request){
    try {
        $data = $request->json()->all();
        $node = App\Node::find($id);

        //Validate the input JSON
        foreach($data as $field => $value){
            if ($field != 'name') {
                return response(json_encode(['error' => "Node property '{$field}' not recognized. Valid fields include: 'name'"]), 400)
                    ->header('Content-Type', 'application/json');
            }
        }

        //Update the node
        if ($node != null){
            if (array_key_exists("name", $data)) {
                $node['name'] = $data['name'];
                $node->save();
            }
            //Return success message
            return response(json_encode(['message'=>"Node id:{$id} successfully updated"]), 200)->header('Content-Type', 'application/json');
        } else {
            return response(json_encode(['error'=>"Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
        }
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

//Adds a child node. Use an id of 0 to add siblings to the top level.
//Request must have a JSON body with a 'name' key/value pair.
$router->post('/api/nodes/{id}/children', function($id, Request $request){
    try {
        $data = $request->json()->all();
        //Validate the input JSON
        foreach ($data as $field => $value) {
            if ($field != 'name')
                return json_encode(['error' => "Node property '{$field}' not recognized. Valid fields include: 'name'"]);
        }
        $name = $data['name'];
        if ($id == 0) {
            //Find the largest rgt value in the database
            $level0Nodes = App\Node::where('level', '=', 0)->orderBy('rgt', 'desc')->take(1)->get();
            $right = 1;
            //Right = 1 if the db is empty. If not empty, do this:
            if (count($level0Nodes) != 0) {
                $right = $level0Nodes[0]->rgt + 1;
            }
            //Insert new node to the right of the $rightmost node
            $createdId = App\Node::insertGetId([
                'name' => $name,
                'level' => 0,
                'lft' => $right,
                'rgt' => $right + 1]);
            return response(json_encode(['message' => "Node id:{$createdId} successfully created"]), 201)->header('Content-Type', 'application/json');
        } else {
            $parent = App\Node::find($id);
            if ($parent != null) {
                $createdId = 0;
                DB::transaction(function () use (&$createdId, $name, $parent) {
                    //Shift all parents and siblings to the right to fit the new node
                    App\Node::where('lft', '>', $parent->rgt)->increment('lft', 2);
                    App\Node::where('rgt', '>=', $parent->rgt)->increment('rgt', 2);

                    //Create the new node
                    $createdId = App\Node::insertGetId([
                        'name' => $name,
                        'level' => $parent->level + 1,
                        'lft' => $parent->rgt,
                        'rgt' => $parent->rgt + 1]);
                });
                return response(json_encode(['message' => "Node id:{$createdId} successfully created"]), 201)->header('Content-Type', 'application/json');
            } else {
                return response(json_encode(['error' => "Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
            }
        }
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

//Takes a json body with {"id":"{childId}"}, moving the childId from it's current location to
//be a child of the node specified in the URL.
//Use an id of 0 to move childId to be a sibling of the top level
$router->put('/api/nodes/{id}/children', function($id, Request $request){

});

//Deletes a node. By default, children are not deleted but moved up a level.
//Use query parameter deleteChildren=true to delete children as well.
$router->delete('/api/nodes/{id}', function($id, Request $request){
    try {
        $delChildren = $request->query('deleteChildren', 'false');

        $toDelete = App\Node::find($id);
        if ($toDelete == null) {
            return response(json_encode(['error' => "Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
        }

        //Calculate the size to delete. (right - left + 1) if children are being deleted. Otherwise, 2.
        $delSize = 2;
        if ($delChildren == 'true') {
            $delSize = $toDelete->rgt - $toDelete->lft + 1;
        }

        DB::transaction(function () use ($toDelete, $delChildren, $delSize) {
            //Delete or shift the children
            if ($delChildren == 'true') {
                App\Node::where([
                    ['lft', '>', $toDelete->lft],
                    ['lft', '<', $toDelete->rgt],
                    ['level', '>', $toDelete->level]
                ])->delete();
            } else {
                App\Node::where([
                    ['lft', '>', $toDelete->lft],
                    ['lft', '<', $toDelete->rgt],
                    ['level', '>', $toDelete->level]
                ])->decrement('lft', 1);
                App\Node::where([
                    ['lft', '>', $toDelete->lft],
                    ['lft', '<', $toDelete->rgt],
                    ['level', '>', $toDelete->level]
                ])->decrement('rgt', 1);
                App\Node::where([
                    ['lft', '>', $toDelete->lft],
                    ['lft', '<', $toDelete->rgt],
                    ['level', '>', $toDelete->level]
                ])->decrement('level', 1);
            }

            //Shift all parents and siblings to the left to fill the gap
            App\Node::where('lft', '>', $toDelete->rgt)->decrement('lft', $delSize);
            App\Node::where('rgt', '>', $toDelete->rgt)->decrement('rgt', $delSize);
            //Delete the chosen node
            App\Node::where('id', '=', $toDelete->id)->delete();
        });
        $message = "Node id:{$toDelete->id} " . ($delChildren == 'true' ? 'and its children have ' : 'has ') . 'been successfully deleted';
        return response(json_encode(['message' => $message]), 201)->header('Content-Type', 'application/json');
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});
