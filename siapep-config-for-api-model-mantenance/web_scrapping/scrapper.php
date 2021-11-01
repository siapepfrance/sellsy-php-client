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
  $responseOutPut = '{';
	foreach ($rows as $row)
	{
    $sectionTitle = $row->Find("div.page-header");
    $title = '';
    $ResponseClassName = '';
    foreach ($sectionTitle as $key => $value) {
      $title = $value->GetPlainText();
    }
    $ResponseClassName .=  implode("", array_map(function($word){ return ucfirst($word); }, explode(".", trim($title)))) . 'Response';
    echo "\t <b>Section</b>: \n\n" . $title . "<br/><br/>";
    echo "\t <b>ResponseClassName</b>: \n\n" . $ResponseClassName . "<br/><br/>";

    $input = $row->Find("pre.lang-php");
    foreach ($input as $key => $value) {
      //echo "\t <b>Input</b>: \n\n" . $value->GetOuterHTML() . "<br/>";
    }
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
		//echo "\t" . $row->GetOuterHTML() . "<br/>";
	}
  $responseOutPut .=  '}';

  echo $responseOutPut;
?>
