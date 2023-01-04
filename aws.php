<?php
$aws_key = "AKIAJL3LTL2KSQHRLV3A";
$aws_secret = "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";
echo $aws_secret;

$aws_bucket = 'showseeker/test'; // AWS bucket 
$aws_object = 'SS_P4.png';         // AWS object name (file name)

if (strlen($aws_secret) != 40) die("$aws_secret should be exactly 40 bytes long");


$dt = gmdate('r'); // GMT based timestamp 

// preparing string to sign
$string2sign = "GET


{$dt}
/{$aws_bucket}/{$aws_object}";


// preparing HTTP query 
$query = "GET /{$aws_bucket}/{$aws_object} HTTP/1.1
Host: s3.amazonaws.com
Connection: close
Date: {$dt}
Authorization: AWS {$aws_key}:".amazon_hmac($string2sign)."\n\n";

echo "Downloading:  https://s3.amazonaws.com/{$aws_bucket}/{$aws_object}\n <br>";
list($header, $resp) = downloadREST($fp, $query);
echo "\n\n";

if (strpos($header, '200 OK') === false) // checking for error
    die($header."\r\n\r\n".$resp); // response code is not 200 OK -- failure

$aws_object_fs = str_replace('/', '_', $aws_object);
// AWS object may contain slashes. We're replacing them with underscores 

@$fh = fopen($aws_object_fs, 'wb');
if ($fh == false) 
    die("Can't open file {$aws_object_fs} for writing. Fatal error!\n");
    
echo "Saving data to {$aws_object_fs}...\n";
fwrite($fh, $resp);
fclose($fh);


// Sending HTTP query, without keep-alive support
function downloadREST($fp, $q)
{
    // opening HTTP connection to Amazon S3
    // since there is no keep-alive we open new connection for each request 
    $fp = fsockopen("s3.amazonaws.com", 80, $errno, $errstr, 30);

    if (!$fp) die("$errstr ($errno)\n"); // connection failed, pity 
        
    fwrite($fp, $q); // sending queyr
    $r = ''; // buffer for result 
    $check_header = true; // header check flag
    $header_end = 0;
    while (!feof($fp)) {
        $r .= fgets($fp, 256); // reading response

        if ($check_header) // checking for header 
        {
            $header_end = strpos($r, "\r\n\r\n"); // this is HTTP header boundary
            if ($header_end !== false) 
                $check_header = false; // We've found it, no more checking 
        }
    }

    fclose($fp);
    
    $header_boundary = $header_end+4; // 4 is length of "\r\n\r\n"
    return array(substr($r, 0, $header_boundary), substr($r, $header_boundary));
    // returning HTTP response header and retrieved data 
}


// hmac-sha1 code START
// hmac-sha1 function:  assuming key is global $aws_secret 40 bytes long
// read more at http://en.wikipedia.org/wiki/HMAC
// warning: key($aws_secret) is padded to 64 bytes with 0x0 after first function call 
function amazon_hmac($stringToSign) 
{
    // helper function binsha1 for amazon_hmac (returns binary value of sha1 hash)
    if (!function_exists('binsha1'))
    { 
        if (version_compare(phpversion(), "5.0.0", ">=")) { 
            function binsha1($d) { return sha1($d, true); }
        } else { 
            function binsha1($d) { return pack('H*', sha1($d)); }
        }
    }

    global $aws_secret;

    if (strlen($aws_secret) == 40)
        $aws_secret = $aws_secret.str_repeat(chr(0), 24);

    $ipad = str_repeat(chr(0x36), 64);
    $opad = str_repeat(chr(0x5c), 64);

    $hmac = binsha1(($aws_secret^$opad).binsha1(($aws_secret^$ipad).$stringToSign));
    return base64_encode($hmac);
}
// hmac-sha1 code END 

?>
