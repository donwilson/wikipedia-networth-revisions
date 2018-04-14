<?php
	ini_set('memory_limit', "1024M");
	set_time_limit(0);
	
	$networths = [];
	
	function make_wikipedia_api_call($params=[]) {
		$cache_key = md5(http_build_query($params));
		
		if(file_exists(__DIR__ ."/tmp/". $cache_key)) {
			return json_decode(file_get_contents(__DIR__ ."/tmp/". $cache_key), true);
		}
		
		$ch = curl_init();
		
		curl_setopt_array($ch, array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_URL => "https://en.wikipedia.org/w/api.php?format=json&". http_build_query($params),
		));
		
		$raw_data = curl_exec($ch);
		
		file_put_contents(__DIR__ ."/tmp/". $cache_key, $raw_data);
		
		$data = json_decode($raw_data, true);
		
		curl_close($ch);
		
		return $data;
	}
	
	function clean_networth($text) {
		$text = str_replace(array("\r", "\n", "\t"), " ", $text);
		$text = preg_replace("#\{\{\s*nbsp\s*\}\}#si", " ", $text);
		$text = preg_replace("#&nbsp;#si", " ", $text);
		$text = strip_tags($text);
		$text = preg_replace("#\s*\{\{\s*(increase|decrease|profit|loss|gain)\s*\}\}\s*#si", "", $text);
		$text = preg_replace("#\{\{\s*US\s*\\\$?\s*\|\s*([^\}]+?)\s*\}\}#si", "\\1", $text);
		$text = preg_replace("#\{\{\s*unbulleted\s*list\s*\|\s*#si", "", $text);
		$text = preg_replace("#\[\[(?:[^\|]+?)\|([^\]]+?)\]\]#si", "\\1", $text);
		$text = preg_replace("#\[\[([^\]]+?)\]\]#si", "\\1", $text);
		
		if(preg_match("#([0-9\.\,]+)\s*(billion|million|thousand|B|M)#si", $text, $match)) {
			switch(strtolower($match[2])) {
				case 'billion': case 'b': $text = (floatval($match[1]) * 1000000000); break;
				case 'million': case 'm': $text = (floatval($match[1]) * 1000000); break;
				case 'thousand': $text = (floatval($match[1]) * 1000); break;
			}
		}
		
		return $text;
	}
	
	
	$people = [
		"Donald Trump",
		"Mark Zuckerberg",
		"Bill Gates",
		"Jeff Bezos",
		"Richard Branson",
		"Larry Page",
		"Sergey Brin",
		"Larry Ellison",
		"Steve Ballmer",
		"Jack Ma",
		"Paul Allen",
		"Elon Musk",
	];
	$charts = [];
	
	foreach($people as $person) {
		for($year = 2004; $year <= date("Y"); $year++) {
			for($month = 1; $month <= 12; $month++) {
				
				if(($year === 2004 && $month < 2) || (($year === 2017) && ($month > date("n")))) {
					continue;
				}
				
				$start = mktime(0, 0, 0, $month, 01, $year);
				$end = mktime(23, 59, 59, $month, date("t", $start), $year);
				
				
				$url_params = [
					'action' => "query",
					'prop' => "revisions",
					'titles' => $person,
					'rvprop' => "content",
					'rvstart' => $start,
					//'rvdir' => "newer",
					//'rvend' => $end,
					//'rvsection' => "0",
					'rvlimit' => "1",
				];
				
				$data = make_wikipedia_api_call($url_params);
				
				//print "<pre>". print_r($data, true) ."</pre>\n";
				
				if(empty($data['query']['pages'])) {
					$networths[ $year ][ $month ] = ["FALSE_". __LINE__, $data];
					$networths[ $year ][ $month ] = "";
					
					continue;
				}
				
				$page = array_shift($data['query']['pages']);
				
				if(empty($page['revisions'][0])) {
					$networths[ $year ][ $month ] = ["FALSE_". __LINE__, $data];
					//$networths[ $year ][ $month ] = "";
					
					continue;
				}
				
				
				$content = $page['revisions'][0]['*'];
				
				if(preg_match("#\|\s*net\_?worth\s*\=\s*(.+?)\n#si", $content, $match)) {
					$networths[ $year ][ $month ] = array(
						'networth' => clean_networth($match[1]),
						//'revisions' => $page['revisions'],
					);
				} else {
					$networths[ $year ][ $month ] = "";
				}
			}
		}
		
		
		
		$chart_data = [];
		
		foreach($networths as $year => $months) {
			foreach($months as $month => $value) {
				if(!isset($value['networth']) || "" === $value['networth']) {
					continue;
				}
				
				$chart_data[] = [ (mktime(0, 0, 0, $month, 1, $year) * 1000), (int)$value['networth'] ];
			}
		}
		
		$charts[] = [
			'label' => $person,
			'data' => $chart_data,
			'lines' => ['show' => true, 'steps' => true],
		];
	}
	
	
	//print "<pre>". htmlentities(print_r($networths, true)) ."</pre>\n";
?>
<!doctype html>
<html>
<head>
	<title>Trump Net Worth via Wikipedia</title>
	
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script src="../chaturbate/flot/jquery.flot.js"></script>
	<script language="javascript" type="text/javascript" src="../chaturbate/flot/jquery.flot.time.js"></script>
	<script language="javascript" type="text/javascript" src="../chaturbate/flot/jquery.flot.stack.js"></script>
	<script type="text/javascript">
		function numberWithCommas(x) {
			return x.toString().replace(/\B(?=(?:\d{3})+(?!\d))/g, ",");
		}
		
		function nFormatter(num) {
		  var digits = 0;
		  
		  var si = [
		    { value: 1E15, symbol: "Q" },
		    { value: 1E12, symbol: "T" },
		    { value: 1E9,  symbol: "B" },
		    { value: 1E6,  symbol: "M" },
		    { value: 1E3,  symbol: "K" }
		  ], rx = /\.0+$|(\.[0-9]*[1-9])0+$/, i;
		  for (i = 0; i < si.length; i++) {
		    if (num >= si[i].value) {
		      return "$"+ (num / si[i].value).toFixed(digits).replace(rx, "$1") + si[i].symbol;
		    }
		  }
		  return "$"+ num.toFixed(digits).replace(rx, "$1");
		}

	</script>
</head>
<body>
	
	<h1>Net Worths (stacked)</h1>
	<div id="chart__stacked" style="width: 100%; height: 500px;"></div>
	<script type="text/javascript">
		;jQuery(document).ready(function($) {
			$.plot($("#chart__stacked"), <?=json_encode($charts);?>, {
				'xaxis': {
					'mode': "time"
				},
				'yaxis': {
					'tickFormatter': nFormatter
				},
				'legend': {
					'position': "nw"
				}
			});
		});
	</script>
	
	<hr />
	
	<?php foreach($charts as $chart): ?>
	<h1><?=$chart['label'];?></h1>
	<div id="chart__<?=md5($chart['label']);?>" style="width: 100%; height: 500px;"></div>
	<script type="text/javascript">
		;jQuery(document).ready(function($) {
			$.plot($("#chart__<?=md5($chart['label']);?>"), [<?=json_encode($chart['data']);?>], {
				'xaxis': {
					'mode': "time"
				},
				'yaxis': {
					'tickFormatter': nFormatter
				},
				'legend': {
					'position': "nw"
				}
			});
		});
	</script>
	<?php endforeach; ?>
	
</body>
</html>