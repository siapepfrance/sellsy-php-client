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

	// Function definitions

	function getOneSectionInformation($section) {
		 $information = [
			 'blockTitle' => '',
			 'blockTitleFixed' => '',
			 'isBlockTitleFixed' => false,
			 'methodName' => '',
			 'tagName' => '',
			 'requestModel' => '',
			 'responseModel' => '',
			 'responseModelName' => '',
			 'requestModelName' => '',
			 'pathName' => ''
		 ];

		 // 1 - extract the title
	   $sectionTitle = $section->Find("div.page-header");
	   $blockTitle = '';
		 foreach ($sectionTitle as $key => $value) {$blockTitle = $value->GetPlainText();}
		 $blockTitle = $blockTitle;
		 $information['blockTitle'] = $blockTitle;
		 $information['blockTitleFixed'] = applyFixes($blockTitle, 'fixAccountdatasTags');
		 $information['isBlockTitleFixed'] = $information['blockTitle'] != $information['blockTitleFixed'];

		 //fixAccountdatasTags

		 // 2 - extract the method name, request model, request model name and response model name
		 $requestBlock = $section->Find("pre.lang-php");
		 foreach ($requestBlock as $key => $value) {
			 	$requestTextToEval = applyFixes($value->GetPlainText(), 'sanitizeRequestBlock');
			 	// Fix : This remove the json code which are written in php block
			 	$requestTextToEval = stripos($requestTextToEval, '$request') != false ? $requestTextToEval : '';
			 	$transformResult = getRequestModelTagAndName($requestTextToEval, $requestAsArray);
				$requestModel = $transformResult['model'];
				$information['methodName'] = $transformResult['name'];
				$information['tagName'] = $transformResult['tag'];
				$information['requestModel'] = (is_array($requestModel) && count($requestModel) > 0) || ($requestModel != null && trim($requestModel) != 'null') ? $requestModel : null;
				$information['requestModelName'] = (is_array($requestModel) && count($requestModel) > 0) || ($requestModel != null && trim($requestModel) != 'null') ? getModelName($information['methodName'], 'Request') : null;
				$information['responseModelName'] = getModelName($information['methodName'], 'Response');
				$information['pathName'] = $information['methodName'];
		 }


		 //requestModelName

		 return $information;
	}

	function getSectionsInformations($sections) {
		 $informations = [];
		 foreach ($sections as $section)
 		 {
				$informations[] = getOneSectionInformation($section);
		 }
		 return $informations;
	}

	// Util functions

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
				case 'fixDocumentCreateEndpoint':
					if (stripos($string, "Document.create'") != false) {
						  // Remove &nbsp; caracter from the string
							return str_replace("\xc2\xa0",' ',$string);
					}
					return $string;
					break;
				case 'fixDocumentUpdateEndpoint':
					if (stripos($string, "Document.update'") != false) {
						  // Remove &nbsp; caracter from the string
							return str_replace("\xc2\xa0",' ',$string);
					}
					return $string;
					break;
				default:
					return $string;
					break;
			}
	}

	function fixEndpoint($endpointString, $requestAsArray) {
				$inputFixed = applyFixes($endpointString, 'removeWhiteSpaces');
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
				$inputFixed = applyFixes($inputFixed, 'fixDocumentCreateEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixDocumentUpdateEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixPaymentsGetListEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixPaymentsCreateEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixPaymentsDeleteEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixPOSReceiptCreateEndpoint');
				$inputFixed = applyFixes($inputFixed, 'fixTimetrackingGetListEndpoint');
				$inputFixed = applyFixes($inputFixed, 'ignoreDocCreate');
				return $inputFixed;
	}

  function getRequestModelTagAndName($requestTextToEval, $requestAsArray) {
		$data = ['name' => '', 'tag' => [], 'model' => []];
		$path = '';
		$inputFixed = '';
		try {
			  $requestTextToEval = trim($requestTextToEval);
			  if(stripos($requestTextToEval, 'method') != false) {
						$inputFixed = fixEndpoint($requestTextToEval, $requestAsArray);
						eval($inputFixed);
						$data = [];
						$data['name'] = $request['method'];
						$data['tag'] = explode(".", trim($request['method']))[0];
						if (isset($request['params']) and count($request['params']) > 0) {
							 $data['model'] = getRequestModel($request['params']);
					  }
			  }
		} catch(\Exception $e) {}
		return $data;
	}

	function getRequestModel($params, $firstLoop = true, $merged = false) {
		if ($firstLoop === true || $merged === true) {
			$data = ['type' => 'object', 'properties' => []];
		} else {
				$isArray = (is_array($params) && count($params) > 0 and is_array($params[array_key_first($params)]));
				if ($isArray) {
					$data = ['type' => 'array', 'items' => []];
				} else {
					$data = ['type' => 'object', 'properties' => []];
				}
		}

		if ($data['type'] == 'object') {
				foreach ($params as $key => $value) {
						$isArray = (is_array($value) && count($value) > 0 and is_array($value[array_key_first($value)]));
						$isObject = (is_array($value) && count($value) > 0 and !is_array($value[array_key_first($value)]));
						$isAttribute = !$isArray && !$isArray;
						if($isArray) {
							// Treat Array
							$data['properties'][$key] = [
									'type' => 'array',
									'items' => getRequestModel($value, false)
							];
						} elseif($isObject) {
							// Treat Object
			 				 $data['properties'][$key] = getRequestModel($value, true);
						} elseif($isAttribute) {
							// Treat Attribute
			 				 $data['properties'][$key] = [
			 					 'type' => 'string',
			 					 'required' => false
			 				 ];
						}
				}
		}
		else {
			$mergedProperties = [];
			foreach ($params as $key => $param) {
				$mergedProperties = array_merge($mergedProperties, $param);
			}
			unset($data['type']);
			unset($data['items']);
			$data = getRequestModel($mergedProperties, false, count($params) > 1);
		}
		return $data;
	}

	function getModelName($title, $modelSuffix) {
		$titleSplitted =  explode(".", trim($title));
		$baseClassName =  implode("", array_map(function($word){ return ucfirst($word); }, $titleSplitted));
		return sprintf('%s%s', $baseClassName, $modelSuffix);
	}

	function writeFile($name, $content) {
		file_put_contents($name, $content);
	}

  // Execution


	// 1 - Get website sections
	$rows = $root->Find("section")->Filter("pre");

	echo count($rows) . ' sections found<br/><br/>';

	// 2 - Show each row

	$sections = getSectionsInformations($rows);
	$requests = [];
	$pathsAsJson = "{\n";
	$pathModel = file_get_contents('../templates/swagger_path_with_response.tpl');
	$pathModelWithRequest = file_get_contents('../templates/swagger_path_with_response_request.tpl');
	$pathModelWithComa = file_get_contents('../templates/swagger_path_with_response_coma.tpl');
	$pathModelWithComaAndRequest = file_get_contents('../templates/swagger_path_with_response_request_coma.tpl');

	foreach ($sections as $key => $value) {
		// Display
		echo '<b>' . $value['blockTitle'] . ' ' . ($value['isBlockTitleFixed'] ? (' -> fixed -> ' . $value['blockTitleFixed']) : ('')) . '</b>: <br/><br/>';
		echo '- blockTitleFixed : ' . $value['blockTitleFixed'] . '<br/>';
		echo '- methodName : ' . $value['methodName'] . '<br/>';
		echo '- tagName : ' . $value['tagName'] . '<br/>';
		echo '- requestModel : ' . json_encode($value['requestModel']) . '<br/>';
		echo '- responseModel : ' . $value['responseModel'] . '<br/>';
		echo '- requestModelName : ' . ($value['requestModelName'] != null ? $value['requestModelName'] : 'null') . '<br/>';
		echo '- responseModelName : ' . $value['responseModelName'] . '<br/>';
		echo '- pathName : ' . $value['pathName'] . '<br/><br/>';

		// File generation
		$hasRequestModel = ($value['requestModel'] && isset($value['requestModel']['properties']) && $value['requestModel']['properties'] != null);

		// 1 - Requests Models
		if($hasRequestModel) {
			$requests[$value['requestModelName']] = $value['requestModel'];
		}
		// 2 - Paths
		// Last section
		if($key == (count($sections) - 1)) {
				if($hasRequestModel === true) {
					$matchedPathModel =  $pathModelWithRequest;
				} else {
					$matchedPathModel =  $pathModel;
				}
		} else {
		// Other sections
				if($hasRequestModel === true) {
					$matchedPathModel =  $pathModelWithComaAndRequest;
				} else {
					$matchedPathModel =  $pathModelWithComa;
				}
		}
		$pathsAsJson .= str_replace(
				['{pathName}', '{tagName}', '{requestModelName}', '{responseModelName}'],
				[$value['pathName'], $value['tagName'], $value['requestModelName'], $value['responseModelName']],
				$matchedPathModel
		);
	}
	$pathsAsJson .= "\n}";

	writeFile('../scrapping_outputs/swagger_generated_requests.json', json_encode($requests));
	writeFile('../scrapping_outputs/swagger_generated_paths.json', $pathsAsJson);


?>
