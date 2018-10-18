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


/**
 *  /api/nodes
 */

//GET- Returns a JSON structure representing nodes and their nested structure
$router->get('/api/nodes', function (){
    try {
        $result = array();
        $nodes = App\Node::where('level', 0)->orderBy('lft','asc')->get();
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

//Recursive nested structure traversal
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

/**
 *  /api/nodes/{id}
 */

//GET- gets info about a single node
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

//PUT - Update endpoint for editing node properties (in this case, only the 'name' property, because the rest
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

//DELETE - Deletes a node. By default, children are not deleted but moved up a level.
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

/**
 *  /api/nodes/{id}/children
 */

//GET - gets all children of a node
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

//POST - Adds a child node. Use an id of 0 to add siblings to the top level.
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
            } else {
                return response(json_encode(['error' => "Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
            }
        }
        return response(json_encode(['message' => "Node id:{$createdId} successfully created"]), 201)->header('Content-Type', 'application/json');
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

//PUT - Takes a json body with {"idToMove":"{childId}"}, moving the childId from it's current location to
//be a child of the node specified in the URL.
//Use an id of 0 to move childId to be a sibling of the top level
$router->put('/api/nodes/{id}/children', function($id, Request $request){
    try {
        $data = $request->json()->all();
        //Validate the input JSON
        foreach ($data as $field => $value) {
            if ($field != 'idToMove')
                return response(json_encode(['error' => "Node property '{$field}' not recognized. Valid fields include: 'idToMove'"]), 400)->header('Content-Type', 'application/json');
        }

        //Validate that the nodes ids aren't the same
        if ($data['idToMove'] == $id){
            return response(json_encode(['error' => "Cannot move node, Node cannot be a parent of itself."]), 400)->header('Content-Type', 'application/json');
        }

        //Validate the nodeToMove input
        $nodeToMove = App\Node::find($data['idToMove']);
        if ($nodeToMove == null){
            return response(json_encode(['error' => "Node id:{$data['idToMove']} from field 'idToMove' not found."]), 400)->header('Content-Type', 'application/json');
        }

        //Calculate the size to move. (right - left + 1) if children are being moved. Otherwise, 2.
        $moveSize = $nodeToMove->rgt - $nodeToMove->lft + 1;

        if ($id == 0){
            DB::transaction(function () use ($nodeToMove, $moveSize) {

                //Find the largest rgt value in the database
                $level0Nodes = App\Node::where('level', '=', 0)->orderBy('rgt', 'desc')->take(1)->get();
                $right = $level0Nodes[0]->rgt + 1;
                if ($level0Nodes[0]->id == $nodeToMove->id) {
                    return;
                }

                //Capture a reference to all the children
                $children = App\Node::where([
                    ['lft', '>', $nodeToMove->lft],
                    ['lft', '<', $nodeToMove->rgt],
                    ['level', '>', $nodeToMove->level]
                ])->orderBy('lft', 'asc')->get();

                //Shift everything to the right of the node to move to the left to fill the gap
                App\Node::where('lft', '>', $nodeToMove->rgt)->decrement('lft', $moveSize);
                App\Node::where('rgt', '>', $nodeToMove->rgt)->decrement('rgt', $moveSize);

                //Refresh the index stored in the $right variable
                $level0Nodes = App\Node::where('level', '=', 0)->orderBy('rgt', 'desc')->take(1)->get();
                $right = $level0Nodes[0]->rgt + 1;

                //Update the nodeToMove
                $levelChange = 0 - $nodeToMove->level;
                $boundChange = $right - $nodeToMove->lft;
                App\Node::where('id', '=', $nodeToMove->id)->update([
                    'level'=>$nodeToMove->level + $levelChange,
                    'rgt'=>$nodeToMove->rgt + $boundChange,
                    'lft'=>$nodeToMove->lft + $boundChange
                ]);

                //Update the children nodes
                foreach($children as $child){
                    App\Node::where('id', '=', $child->id)->update([
                        'level'=>$child->level + $levelChange,
                        'rgt'=>$child->rgt + $boundChange,
                        'lft'=>$child->lft + $boundChange
                    ]);
                }
            });
        } else {
            //Verify the new parent node exists
            $parent = App\Node::find($id);
            if ($parent == null) {
                return response(json_encode(['error' => "Node id:{$id} not found."]), 404)->header('Content-Type', 'application/json');
            }

            DB::transaction(function () use ($parent, $nodeToMove, $moveSize) {

                //Capture a reference to all the children
                $children = App\Node::where([
                    ['lft', '>', $nodeToMove->lft],
                    ['lft', '<', $nodeToMove->rgt],
                    ['level', '>', $nodeToMove->level]
                ])->orderBy('lft', 'asc')->get();

                //Shift everything to the right of the node to move to the left to fill the gap
                App\Node::where('lft', '>', $nodeToMove->rgt)->decrement('lft', $moveSize);
                App\Node::where('rgt', '>', $nodeToMove->rgt)->decrement('rgt', $moveSize);

                //Refresh the parent data stored in the $parent variable
                $parent = App\Node::find($parent->id);

                //Shift everything to the right of the new parent (including the parent's rgt) to fit the new node
                App\Node::where('lft', '>', $parent->rgt)->increment('lft', $moveSize);
                App\Node::where('rgt', '>=', $parent->rgt)->increment('rgt', $moveSize);

                //Update the nodeToMove
                $levelChange = ($parent->level + 1) - $nodeToMove->level;
                $boundChange = $parent->rgt - $nodeToMove->lft;
                App\Node::where('id', '=', $nodeToMove->id)->update([
                    'level'=>$nodeToMove->level + $levelChange,
                    'rgt'=>$nodeToMove->rgt + $boundChange,
                    'lft'=>$nodeToMove->lft + $boundChange
                ]);

                //Update the children nodes
                foreach($children as $child){
                    App\Node::where('id', '=', $child->id)->update([
                        'level'=>$child->level + $levelChange,
                        'rgt'=>$child->rgt + $boundChange,
                        'lft'=>$child->lft + $boundChange
                    ]);
                }
            });
        }
        return response(json_encode(['message' => "Node id:{$nodeToMove->id} successfully moved."]), 201)->header('Content-Type', 'application/json');
    }
    catch (Exception $e) {
        return response(json_encode(['error' => $e->getMessage()]), 500)->header('Content-Type', 'application/json');
    }
});

