# Sales Rabbit Code Challenge - Response
## Completed by Brandon Twede

## Endpoints
My solution has three endpoints:

	/api/nodes                    -> Represents all the nodes
	/api/nodes/{id}               -> Represents an individual node
	/api/nodes/{id}/children      -> Represents a node's children
	
The different operations on the objects these endpoints represent are determined by the HTTP method used.

## /api/nodes

### GET /api/nodes
This API call will return a nested JSON structure with all nodes. Each JSON node object will have a node's id, name, level, and a list containing that node's children.

## /api/nodes/{id}

### GET /api/nodes/{id}
This API call will return information on a single node, given the node's {id}. Specifically, the node's id, name, and level are returned.

### PUT /api/nodes/{id}
This API call will update node properties for the node specified by {id}(in this case, only the 'name' property can be changed, because the rest of the properties are related to node position in the structure and are handled by the endpoints that insert/move/delete nodes).

This endpoint expects a JSON body containing the new name, as follows:

	{ "name" : "New Node Name" }
	
### DELETE /api/nodes/{id}
This API call will delete the node indicated by {id}. By default, children of {id} will not be deleted, but will be moved up a level. However, the use of the query parameter ?deleteChildren=true can be used to delete children as well.

## /api/nodes/{id}/children

### GET /api/nodes/{id}/children
Similar to GET /api/nodes, except this only returns a subset of the hierarchy. Specifically, only the children of node {id} are returned.

### POST /api/nodes/{id}/children
The API call for INSERTing new nodes into the hierarchy. The new node is insterted as a child of an existing node {id}, or use an {id} of 0 to add the new node to the top level.

This endpoint expects a JSON body containing the name for the new node, as follows:

	{ "name" : "New Child" }

### PUT /api/nodes/{id}/children
This API call will move a node (and its children) to a new location. The node to move is specified in the request body, and will be moved to be a child of the node specified by {id}. Use an {id} of 0 to move the node(s) to the top level.

This endpoint expects a JSON body containing the id of the node to move, as follows:

	{ "idToMove" : "id" }

### Testing
Also for your convenience, if you use Postman for testing, I have included a collection of API calls in Postman that can be used for testing. The package is called "SalesRabbitCodeChallenge.postman_collection".
## Responses
Generally, each endpoint will return a status code 200 if successful (201 for the 'insert' endpoint), a 404 if a path id is incorrect, and a 400 if a JSON body is incorrectly formatted or contains invalid information. Database errors will return a status code 500.

Responses with successful status codes will also be accompanied by a json object with the key "message" with some text about the operation performed being successful. Responses with a 400-500 status code with have a json object with the key "error" and some text about why the operation failed.



