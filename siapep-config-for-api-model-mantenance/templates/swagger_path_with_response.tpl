		"/{pathName}": {
				"post": {
					"tags": [
						"{tagName}"
					],
					"summary": "",
					"description": "",
					"produces": [
						"application/json"
					],
					"parameters": [],
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
		}
