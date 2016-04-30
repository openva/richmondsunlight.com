<?php

###
# Create Bill JSON
# 
# PURPOSE
# Accepts a section of code, and responds with a listing of bills that addressed that section.
# 
# NOTES
# This is not intended to be viewed. It just spits out a JSON file and that's that.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once 'includes/settings.inc.php';
require_once 'includes/functions.inc.php';

# As of this writing, the server is running PHP 5.1.8. So here's a function to substitute for
# json_encode, which wasn't added until 5.2, courtesy of boukeversteegh@gmail.com, found at
# http://www.php.net/manual/en/function.json-encode.php#100835.
if (!function_exists(json_encode))
{
	function json_encode( $data ) {            
		if( is_array($data) || is_object($data) ) { 
			$islist = is_array($data) && ( empty($data) || array_keys($data) === range(0,count($data)-1) ); 
			
			if( $islist ) { 
				$json = '[' . implode(',', array_map('json_encode', $data) ) . ']'; 
			} else { 
				$items = Array(); 
				foreach( $data as $key => $value ) { 
					$items[] = json_encode("$key") . ':' . json_encode($value); 
				} 
				$json = '{' . implode(',', $items) . '}'; 
			} 
		} elseif( is_string($data) ) { 
			# Escape non-printable or Non-ASCII characters. 
			$string = '"' . addcslashes($data, "\"\\\n\r\t/" . chr(8)) . '"'; 
			$json    = ''; 
			$len    = strlen($string); 
			# Convert UTF-8 to Hexadecimal Codepoints. 
			for( $i = 0; $i < $len; $i++ ) { 
				
				$char = $string[$i]; 
				$c1 = ord($char); 
				
				# Single byte; 
				if( $c1 <128 ) { 
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1); 
					continue; 
				} 
				
				# Double byte 
				$c2 = ord($string[++$i]); 
				if ( ($c1 & 32) === 0 ) { 
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128); 
					continue; 
				} 
				
				# Triple 
				$c3 = ord($string[++$i]); 
				if( ($c1 & 16) === 0 ) { 
					$json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128)); 
					continue; 
				} 
					
				# Quadruple 
				$c4 = ord($string[++$i]); 
				if( ($c1 & 8 ) === 0 ) { 
					$u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1; 
				
					$w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3); 
					$w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128); 
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2); 
				} 
			} 
		} else { 
			# int, floats, bools, null 
			$json = strtolower(var_export( $data, true )); 
		} 
		return $json; 
	} 
}

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# LOCALIZE VARIABLES
$section = mysql_escape_string(urldecode($_REQUEST['section']));
if (isset($_REQUEST['callback']) && !empty($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# Select the bill data from the database.
$sql = 'SELECT sessions.year, bills.number, bills.catch_line
		FROM bills
		LEFT JOIN bills_section_numbers
			ON bills.id = bills_section_numbers.bill_id
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE bills_section_numbers.section_number =  "'.$section.'"
		ORDER BY year ASC';
$result = mysql_query($sql);
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
$bill = mysql_fetch_array($result, MYSQL_ASSOC);

# Build up a listing of all bills.
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($bill = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$bill['url'] = 'http://www.richmondsunlight.com/bill/'.$bill['year'].'/'.$bill['number'].'/';
	$bill['number'] = strtoupper($bill['number']);
	$bills[] = array_map('stripslashes', $bill);
}

# Send an HTTP header defining the content as JSON.
header('Content-type: application/json');

# Send the JSON. If a callback has been specified, prefix the JSON with that callback and wrap the
# JSON in parentheses.
if (isset($callback))
{
	echo $callback.' (';
}
echo json_encode($bills);
if (isset($callback))
{
	echo ');';
}

?>