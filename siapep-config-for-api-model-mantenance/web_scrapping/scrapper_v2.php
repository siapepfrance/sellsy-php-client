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
			 'methodName' => '',
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
		 $blockTitle = applyFixes($blockTitle, 'fixAccountdatasTags');
		 $information['blockTitle'] = $blockTitle;

		 // 2 - extract the method name and model
		 $requestBlock = $section->Find("pre.lang-php");
		 foreach ($requestBlock as $key => $value) {
			 	$requestTextToEval = applyFixes($value->GetPlainText(), 'sanitizeRequestBlock');
			 	// Fix : This remove the json code which are written in php block
			 	$requestTextToEval = stripos($requestTextToEval, '$request') != false ? $requestTextToEval : '';
			 	$transformResult = getRequestPathAndName($requestTextToEval, $requestAsArray);
				$information['methodName'] = $transformResult['name'];
				$information['requestModel'] = $transformResult['model'];
		 }

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

  function getRequestPathAndName($requestTextToEval, $requestAsArray) {
		$data = ['name' => '', 'model' => []];
		$path = '';
		$inputFixed = '';
		try {
			  $requestTextToEval = trim($requestTextToEval);
			  if(stripos($requestTextToEval, 'method') != false) {
						$inputFixed = fixEndpoint($requestTextToEval, $requestAsArray);
						eval($inputFixed);
						$data = [];
						$data['name'] = $request['method'];
						if (isset($request['params']) and count($request['params']) > 0) {
							foreach ($request['params'] as $key => $value) {
								 if(is_array($value)) {
								 	 $data['model'][$key] = [
										 'type' => 'object',
										 'properties' => []
									 ];
									 foreach ($value as $attributeKey => $attribute) {
			                $data['model'][$key]['properties'][$attributeKey] = [
													'type' => 'string',
				 								 'required' => false
											];
									 }
								 } else {
								 	 $data['model'][$key] = [
										 'type' => 'string',
										 'required' => false
									 ];
								 }
							}
					}
			  }
		} catch(\Exception $e) {}

		return $data;
	}

  // Execution



	// 1 - Get website sections
	$rows = $root->Find("section")->Filter("pre");

	echo count($rows) . ' sections found<br/><br/>';

	// 2 - Show each row

	$sections = getSectionsInformations($rows);

	foreach ($sections as $key => $value) {
		echo '<b>' . $value['blockTitle'] . '</b>: <br/>';
		echo '- blockTitle :' . $value['blockTitle'] . '<br/>';
		echo '- methodName :' . $value['methodName'] . '<br/>';
		echo '- requestModel :' . json_encode($value['requestModel']) . '<br/>';
		echo '- responseModel :' . $value['responseModel'] . '<br/>';
		echo '- requestModelName :' . $value['requestModelName'] . '<br/>';
		echo '- responseModelName :' . $value['responseModelName'] . '<br/>';
		echo '- pathName :' . $value['pathName'] . '<br/><br/>';
	}


?>
