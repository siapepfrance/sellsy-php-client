<?php
	require_once "ultimate-web-scraper/support/web_browser.php";
	require_once "ultimate-web-scraper/support/tag_filter.php";

	// Retrieve the standard HTML parsing array for later use.
	$htmloptions = TagFilter::GetHTMLOptions();

	// Retrieve a URL (emulating Firefox by default).
	$url = "https://api.sellsy.com/documentation/methodes";
	$web = new WebBrowser();
	$result = $web->Process($url);

	// Check for connectivity and response errors.
	if (!$result["success"])
	{
		echo "Error retrieving URL.  " . $result["error"] . "\n";
		exit();
	}

	if ($result["response"]["code"] != 200)
	{
		echo "Error retrieving URL.  Server returned:  " . $result["response"]["code"] . " " . $result["response"]["meaning"] . "\n";
		exit();
	}

	// Get the final URL after redirects.
	$baseurl = $result["url"];

	// Use TagFilter to parse the content.
	$html = TagFilter::Explode($result["body"], $htmloptions);

	// Retrieve a pointer object to the root node.
	$root = $html->Get();

	// Find all anchor tags inside a div with a specific class.
	// A useful CSS selector cheat sheet:  https://gist.github.com/magicznyleszek/809a69dd05e1d5f12d01
	echo "All the URLs:<br/><br/>";
	$rows = $root->Find("div.someclass a[href]");
	foreach ($rows as $row)
	{
		echo "\t" . $row->href . "\n";
		echo "\t" . HTTP::ConvertRelativeToAbsoluteURL($baseurl, $row->href) . "\n";
	}


	// Find all table rows that have 'th' tags.
	$rows = $root->Find("section")->Filter("pre");


  function buildSwaggerResponses($rows, &$models, $outputFileName) {
	  $responseOutPut = '{';
		foreach ($rows as $row)
		{
	    $sectionTitle = $row->Find("div.page-header");
	    $title = '';
	    $ResponseClassName = '';
	    foreach ($sectionTitle as $key => $value) {$title = $value->GetPlainText();}
			$title = applyFixes($title, 'fixAccountdatasTags');
			$EndpointNameSplitted =  explode(".", trim($title));
			$BaseClassName =  implode("", array_map(function($word){ return ucfirst($word); }, $EndpointNameSplitted));
			$ResponseClassName =  $BaseClassName . 'Response';
	    echo "\t <b>Route</b>: \n\n" . $title . "<br/><br/>";
			echo "\t <b>Model de sortie</b>: \n\n" . $ResponseClassName . "<br/><br/>";

			// Get the JSON Responses for each request
	    $output = $row->Find("pre.lang-js");
	    foreach ($output as $key => $value) {
	      $formattedOutput = "\n\n\"" . $ResponseClassName . '" : ' .$value->GetPlainText() . "\n\n";
	      if($responseOutPut == '{') {
	        $responseOutPut .=  $formattedOutput;
	      } else {
	        $responseOutPut .= ",\n\n" . $formattedOutput;
	      }
	      //echo "\t <b>Ouput</b>: \n\n" . $formattedOutput . " <br/>";
	    }

			echo '------------------------------<br/><br/>';
		}
		$responseOutPut .=  '}';

		file_put_contents($outputFileName, $responseOutPut);
	}

	function applyFixes($string, $case, $extraData = []) {
			switch ($case) {
				case 'commaAfterKeyValue':
					$formattedOutput = str_replace('}}\'"', '}}\',"', str_replace("}}''",  "}}','", $string));
					$formattedOutput = str_replace("'}", "'", $formattedOutput);
					return $formattedOutput;
					break;
				case 'removeWhiteSpaces':
					return str_replace(' ', '', str_replace('	', '', str_replace(' ',  '', $string)));
					break;
				case 'removeLineBreaks':
					return str_replace("\n",  '', $string);
					break;
				case 'fixAccountdatasCreateEndpoint':
					return strpos($string, 'Accountdatas.create') != false ? str_replace("'params'=>array('unit'=>'value'=>'{{unit_value}}','isEnabled'=>'{{unit_enabled}}'))", "'params'=>array('unit'=>array('value'=>'{{unit_value}}','isEnabled'=>'{{unit_enabled}}')))", $string) : $string;
					break;
				case 'fixPaymentsGetListEndpoint':
					return strpos($string, 'Payments.getList') != false ? str_replace("]]);",  ']]];', $string) : $string;
					break;
				case 'fixPaymentsCreateEndpoint':
					return strpos($string, 'Payments.create') != false && strlen($string) > 70 ? str_replace("']]);",  "']]];", $string) : $string;
					break;
				case 'fixPaymentsDeleteEndpoint':
					return strpos($string, 'Payments.create') != false && strlen($string) < 70 ?
					str_replace(
						");",  "];",
						str_replace(
							'create', 'delete',
							$string
						)
					)
					:
					$string;
					break;
				case 'fixPOSReceiptCreateEndpoint':
					return strpos($string, 'POSReceipt.create') != false ?
					str_replace(
						"#Commononce/item",  "",
						str_replace(
							'#Item', '',
							$string
						)
					)
					:
					$string;
					break;
				case 'fixTimetrackingGetListEndpoint':
					return strpos($string, 'Timetracking.getList') != false ?
					str_replace(
						"{{periodecreated_end}",  "{{periodecreated_end}}'",
						$string
					)
					:
					$string;
					break;
				case 'fixPurchaseCreateEndpoint':
					return
					strpos($string, 'Purchase.create') != false ?
					str_replace(
						array(
							applyFixes('# Common for all line types #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for next row types  :  ‘once‘, ‘item‘, ‘shipping‘ et ‘packaging‘ #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘once‘ and ‘item‘ row types #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘item‘ row type #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘title‘ row type #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘comment‘ row type #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘shipping‘ row type #', 'removeWhiteSpaces'),
							applyFixes('# Applicable for ‘packaging‘ row type #', 'removeWhiteSpaces')
						),
						array('', '', '', '', '', '', '', ''),
						$string)
						:
						$string;
					break;
				case 'fixBankAccountCreateEndpoint':
					return strpos($string, 'BankAccount.create') != false ?
					str_replace(
							array(
								applyFixes("# hasiban == 'Y'", 'removeWhiteSpaces'),
								applyFixes("# hasiban == 'N'", 'removeWhiteSpaces')
							),
							array('', ''),
							$string)
							:
							$string;
					break;
				case 'fixBankAccountUpdateEndpoint':
					return strpos($string, 'BankAccount.update') != false ?
					str_replace(
							array(
								applyFixes("# hasiban == 'Y'", 'removeWhiteSpaces'),
								applyFixes("# hasiban == 'N'", 'removeWhiteSpaces')
							),
							array('', ''),
							$string)
							:
							$string;
					break;
				case 'fixBankAccountMassCreateEndpoint':
					return strpos($string, 'BankAccount.massCreate') != false ?
					str_replace(
							array(
								applyFixes("# hasiban == 'Y'", 'removeWhiteSpaces'),
								applyFixes("# hasiban == 'N'", 'removeWhiteSpaces')
							),
							array('', ''),
							$string)
							:
							$string;
					break;
				case 'fixBankAccountMassUpdateEndpoint':
					return strpos($string, 'BankAccount.massUpdate') != false ?
					str_replace(
							array(
								applyFixes("# hasiban == 'Y'", 'removeWhiteSpaces'),
								applyFixes("# hasiban == 'N'", 'removeWhiteSpaces')
							),
							array('', ''),
							$string)
							:
							$string;
					break;
				case 'fixAccountdatasTags':
					return str_replace("Accoundatas",  "Accountdatas", $string);
					break;
				case 'fixClientUpdateEndpoint':
				  $isClientUpdateEndpoint = stripos($string, "Client.update'");
					if ($isClientUpdateEndpoint != false) {
							$finalString = str_replace(
								'Client.create',
								'Client.Client.update',
								str_replace("'third'", "'clientid' => '{{clientid}}', 'third'", $extraData['Client.create']['blockContent'])
							);
							return $finalString;
					}
					return $string;
					break;
				case 'addMissingEndInstructionSign':
					$stringLength = strlen($string);
					if($string[($stringLength - 1)] != ';') {
						return str_replace(';;', ';', ($string . ';'));
					}
					return str_replace(';;', ';', $string);
					break;
				case 'addMissingEndParenthese':
				  $arrayCount = substr_count(strtolower($string), 'array(');
				  $endParetheseCount = substr_count($string, ')');
					if($arrayCount > $endParetheseCount) {
						return str_replace(');', '));', $string);
					}
					return $string;
					break;
				case 'addMissingComaBetweenArrays':
					return str_replace(")'", "),'", $string);
					break;
				case 'lowercaseArrays':
					return str_replace("Array(", "array(", $string);
					break;
				case 'removeTooMuchEndParenthese':
				  $arrayCount = substr_count(strtolower($string), 'array(');
					$arrayBisCount = substr_count($string, '[');
				  $endParetheseCount = substr_count($string, ')');
					$numberOfTooMuchParentheses = $endParetheseCount - $arrayCount;
					if($arrayBisCount == 0 and $arrayCount < $endParetheseCount) {
						for ($i = 0; $i < $numberOfTooMuchParentheses; $i++) {
								$string = str_replace('));', ');', $string);
						}
					}
					return $string;
					break;
				case 'sanitizeRequestBlock':
				  $formattedOutput = str_replace('&gt;', '>', $string);
					$formattedOutput = str_replace('$response = sellsyConnect::load()->requestApi($request);', '', $formattedOutput);
					$formattedOutput = str_replace('sellsyConnect::load()->requestApi($request);', '', $formattedOutput);
					$formattedOutput = str_replace('invokitConnect_curl::load()->requestApi($request, false, $file);', '', $formattedOutput);
					$formattedOutput = str_replace('invokitConnect_curl::load()->requestApi($request, false);', '', $formattedOutput);
					$formattedOutput = str_replace('{{', '\'{{', $formattedOutput);
					$formattedOutput = str_replace('}}', '}}\'', $formattedOutput);
					return $formattedOutput;
					break;
				case 'fixElectronicSignCreateEndpoint':
					return str_replace(
						"':'", "','",
						str_replace(
							"':array", "', array",
							str_replace(
								",'{{peopleId}}',...", "",
								$string
							)
						)
					);
				case 'ignoreDocCreate':
					if (
						stripos($string, "Document.create'") != false ||
						stripos($string, "Document.update'") != false
					) {
							return '';
					}
					return $string;
					break;
				default:
					return $string;
					break;
			}
	}

  function transformToRequest($inputToEval, $requestAsArray) {
		$data = [];
		$path = '';
		$inputFixed = '';
		try {
			  $inputToEval = trim($inputToEval);
			  if(stripos($inputToEval, 'method') != false) {
						$inputFixed = applyFixes($inputToEval, 'removeWhiteSpaces');
						$inputFixed = applyFixes($inputFixed, 'removeLineBreaks');
						$inputFixed = applyFixes($inputFixed, 'commaAfterKeyValue');
						$inputFixed = applyFixes($inputFixed, 'fixAccountdatasCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixPurchaseCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixBankAccountCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixBankAccountMassCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixBankAccountUpdateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixBankAccountMassUpdateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixClientUpdateEndpoint', $requestAsArray);
						$inputFixed = applyFixes($inputFixed, 'addMissingEndParenthese');
						$inputFixed = applyFixes($inputFixed, 'removeTooMuchEndParenthese');
						$inputFixed = applyFixes($inputFixed, 'addMissingEndInstructionSign');
						$inputFixed = applyFixes($inputFixed, 'addMissingComaBetweenArrays');
						$inputFixed = applyFixes($inputFixed, 'lowercaseArrays');
						$inputFixed = applyFixes($inputFixed, 'fixElectronicSignCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixPaymentsGetListEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixPaymentsCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixPaymentsDeleteEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixPOSReceiptCreateEndpoint');
						$inputFixed = applyFixes($inputFixed, 'fixTimetrackingGetListEndpoint');
						$inputFixed = applyFixes($inputFixed, 'ignoreDocCreate');
						echo '$inputFixed : ' . $inputFixed . '<br/><br/>';
						eval($inputFixed);
						if (isset($request['params']) and count($request['params']) > 0) {
							foreach ($request['params'] as $key => $value) {
								 if(is_array($value)) {
								 	 $data[$key] = [
										 'type' => 'object',
										 'properties' => []
									 ];
									 foreach ($value as $attributeKey => $attribute) {
			                $data[$key]['properties'][$attributeKey] = [
													'type' => 'string',
				 								 'required' => false
											];
									 }
								 } else {
									 $data[$key] = [
											 'type' => 'string',
											 'required' => false
									 ];
								 }
							}
					}
					$path = $request['method'];
			  }
		} catch(\Exception $e) {}

		if(isset($request['params']) and count($request['params']) == 0) {
				return null;
		}
		return [
				'path' => $path,
				'value' => $data,
				'blockContent' => $inputFixed,
		];
	}

  function buildSwaggerRequests($rows, &$models, $outputFileName) {
	  $responseOutPut = '';
		$requestAsJson = "{\n";
		$requestAsArray = [];
		foreach ($rows as $row)
		{
	    $sectionTitle = $row->Find("div.page-header");
	    $title = '';
	    $ResponseClassName = '';
	    foreach ($sectionTitle as $key => $value) {$title = $value->GetPlainText();}
			$title = applyFixes($title, 'fixAccountdatasTags');
			$EndpointNameSplitted =  explode(".", trim($title));
			$BaseClassName =  implode("", array_map(function($word){ return ucfirst($word); }, $EndpointNameSplitted));
			$ResponseClassName =  $BaseClassName . 'Response';
			$RequestClassName =  $BaseClassName . 'Request';

			// Get the JSON Responses for each request
	    $output = $row->Find("pre.lang-php");
	    foreach ($output as $key => $value) {
				$formattedOutput = applyFixes($value->GetPlainText(), 'sanitizeRequestBlock');
				// Fix : This remove the json code which are written in php block
				$formattedOutput = stripos($formattedOutput, '$request') != false ? $formattedOutput : '';
				$transformResult = transformToRequest($formattedOutput, $requestAsArray);
				if($transformResult != null) {
						$requestAsArray[$transformResult['path']] = $transformResult;
						$requestAsJson .= json_encode($transformResult);
						$responseOutPut .= "\n\n\"" . $ResponseClassName . '" :\n\n ' . $formattedOutput;
				}
	    }

		}
		var_dump($requestAsArray);
		$requestAsJson .= "\n}";

		file_put_contents($outputFileName, $requestAsJson);
	}

	function generateModels($rows) {
		foreach ($rows as $row)
		{
	    $sectionTitle = $row->Find("div.page-header");
	    $title = '';
	    foreach ($sectionTitle as $key => $value) {$title = $value->GetPlainText();}
			$title = applyFixes($title, 'fixAccountdatasTags');
			$EndpointNameSplitted =  explode(".", trim($title));
			$model = [
				'modelName' => $EndpointNameSplitted[0],
				'endpointNameCamelCase' => isset($EndpointNameSplitted[1]) ? $EndpointNameSplitted[1] : '',
				'endpointNamePascalCase' => ucfirst(isset($EndpointNameSplitted[1]) ? $EndpointNameSplitted[1] : ''),
				'hasRequest' => true
			];
			// Check if there are parameters or not ( to include Request block or not )
			$alertBlocks = $row->Find("div.alert-block");
			$noParametersText = 'no parameter';
	    foreach ($alertBlocks as $alertBlock) {
				if(strpos(strtolower($alertBlock->GetPlainText()), $noParametersText) != false) {
						$model['hasRequest'] = false;
				}
	    }
			$models[] = $model;
		}
		return $models;
	}

  function buildSwaggerPaths($models, $outputFileName) {
			$pathModel = file_get_contents('./pathModel.tpl');
			$pathModelWithRequest = file_get_contents('./pathModelWithRequest.tpl');
			$pathModelWithComa = file_get_contents('./pathModelWithComa.tpl');
			$pathModelWithComaAndRequest = file_get_contents('./pathModelWithComaAndRequest.tpl');
			$output = "{\n";
			foreach ($models as $key => $model) {
				$modelName = $model['modelName'];
				$endpointNameCamelCase = $model['endpointNameCamelCase'];
				$endpointNamePascalCase = $model['endpointNamePascalCase'];
				$endpointNamePascalCase = $model['endpointNamePascalCase'];
				$hasRequest = $model['hasRequest'];
				if($key == (count($models) - 1)) {
						if($hasRequest === true) {
							$matchedPathModel =  $pathModelWithRequest;
						} else {
							$matchedPathModel =  $pathModel;
						}
				} else {
						if($hasRequest === true) {
							$matchedPathModel =  $pathModelWithComaAndRequest;
						} else {
							$matchedPathModel =  $pathModelWithComa;
						}
				}
				$generatedPathModel = str_replace("{ModelName}", $modelName, $matchedPathModel);
				$generatedPathModel = str_replace("{EndpointNameCamelCase}", $endpointNameCamelCase, $generatedPathModel);
				$generatedPathModel = str_replace("{EndpointNamePascalCase}", $endpointNamePascalCase, $generatedPathModel);
				$output .=  $generatedPathModel;
			}
			$output .= "\n}";
			file_put_contents($outputFileName, $output);
	}

	$models = generateModels($rows);

	buildSwaggerResponses($rows, $models, '../scrapping_outputs/swagger_generated_responses.json');

	buildSwaggerRequests($rows, $models, '../scrapping_outputs/swagger_generated_requests.json');

	buildSwaggerPaths($models, '../scrapping_outputs/swagger_generated_paths.json');

	file_put_contents('json_responses.json', $responseOutPut);
?>
