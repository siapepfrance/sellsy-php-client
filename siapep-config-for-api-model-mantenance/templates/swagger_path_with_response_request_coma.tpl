		"/{pathName}": {
				"post": {
					"tags": [
						"{ModelName}"
					],
					"summary": "",
					"description": "",
					"produces": [
						"application/json"
					],
					"parameters": [
						{
							"in": "body",
							"name": "body",
							"description": "params",
							"required": true,
							"schema": {
								"$ref": "#/definitions/{requestModelName}"
							}
						}
					],
					"responses": {
						"200": {
							"description": "Request executed successfully",
							"schema": {
								"$ref": "#/definitions/{responseModelName}"
							}
						},
						"400": {
							"description": "Error : bad parameters or missing parameters"
						},
						"401": {
							"description": "Error : User is not authenticated"
						},
						"403": {
							"description": "Error : User has not enought rights"
						},
						"500": {
							"description": "Error : account is disabled"
						}
					}
				},
		},
