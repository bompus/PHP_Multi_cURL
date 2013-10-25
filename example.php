<?php
/*
 * PHP_Multi_cURL
 *
 * @author bompus
 * @url http://github.com/bompus/PHP_Multi_cURL
 *
 * Fetches multiple URLs concurrently via CURL and returns their output or errors.
 * Supports GET, POST, COOKIES
 *
 * @url_arr (array) multidimensional array of arguments
 * @return (array) multidimensional array with matching index [ 'content','error','meta' ]
 */
 
/*
 * 	argument: $url_arr = multidimensional array of arguments, example below:
 *
 *	$my_arr = [
 *		[ 'url' => 'http://www.google.com', 'method' => 'GET' ],
 *		[ 'url' => 'http://www.yahoo.com', 'method' => 'POST', 'post_params' => [ 'abc' => '123', 'def' => '456' ], 'cookie_file' => '/path/to/files/yahoo.txt' ],
 *		[ 'url' => 'http://www.google.com', 'method' => 'GET' 'meta' => [ 'unique_id' => 'abc123', 'email' => 'me@you.com' ] ]
 *	];
 *	$ret_arr = PHP_Multi_cURL($my_arr);
 *	$unique_id = $ret_arr[2]['meta']['unique_id']; // will be 'abc123'
 *
 *	$url_arr arguments for each array item: 
 *	url - the URL to fetch
 *	method - GET or POST ( defaults to GET )
 *	post_params = array of parameters for POST ( defaults to NONE )
 * 	cookie_file = absolute file system path to cookie filename ( defaults to NONE )
 * 	meta = provide extra data ( in a key => value array ) that will be returned in response , 
 *			example: [ 'unique_id' => 'abc123', 'email' => 'me@you.com' ] ( defaults to NONE )
 */
 
function PHP_Multi_cURL($url_arr) {
    $return_arr = array();
    if (!is_array($url_arr)) { return false; }
    
    $url_count = count($url_arr);
    $curl_arr = array();
    $master = curl_multi_init();
    
    for($i = 0; $i < $url_count; $i++) {
	
		$x = $url_arr[$i];
	
		$return_arr[$i]['content'] = '';
		$return_arr[$i]['error'] = false;
		if (!isset($x['meta'])) { $x['meta'] = []; }
		$return_arr[$i]['meta'] = $x['meta'];
	
        $curl_arr[$i] = curl_init($x['url']);
		
		$rand_ua = false;
		if ($rand_ua) {
			$ua = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.8 Safari/537.'.mt_rand(0,9999);
		} else {
			$ua = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.8 Safari/537.36';
		}
		
		$header = array(
		  'Accept: application/json,text/javascript,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		  'Accept-Language: en-us,en;q=0.5',
		  'Accept-Encoding: gzip,deflate',
		  'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
		  'User-Agent: '.$ua,
		  'Connection: close'
		);
		
		if (!isset($x['method'])) { $x['method'] = 'GET'; }
		if ($x['method'] === 'POST') {
			$query = http_build_query($x['post_params'], '', '&');
			curl_setopt($curl_arr[$i], CURLOPT_POST, 1);
			curl_setopt($curl_arr[$i], CURLOPT_POSTFIELDS, $query); // abc=123&def=456
			$header[] = 'Content-Type: application/x-www-form-urlencoded';
        }
		
		if (!isset($x['cookie_file'])) { $x['cookie_file'] = false; }
		if ($x['cookie_file'] !== false) {
			curl_setopt($curl_arr[$i], CURLOPT_COOKIEJAR, $x['cookie_file']);
			curl_setopt($curl_arr[$i], CURLOPT_COOKIEFILE, $x['cookie_file']);
		}
        
		curl_setopt($curl_arr[$i], CURLOPT_HEADER, false);
		curl_setopt($curl_arr[$i], CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_arr[$i], CURLOPT_TIMEOUT, 10);
        curl_setopt($curl_arr[$i], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_arr[$i], CURLOPT_REFERER, $x['url']);
		curl_setopt($curl_arr[$i], CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl_arr[$i], CURLOPT_ENCODING , "gzip");
        
        curl_multi_add_handle($master, $curl_arr[$i]);
    }
    
    $active = null;
    
    $process = curl_multi_exec($master, $active);
    while ($process === CURLM_CALL_MULTI_PERFORM) {
        $process = curl_multi_exec($master, $active);
        usleep(1000);
    }
    
    while (($active >= 1) && ($process === CURLM_OK)) {
        /* this is the more proper way to work around a Windows PHP + cURL issue */
        if (curl_multi_select($master) === -1) { usleep(1000); }
		do {
			$process = curl_multi_exec($master, $active);
			usleep(1000);
		} while ($process === CURLM_CALL_MULTI_PERFORM);
		usleep(1000);
    }

    for($i = 0; $i < $url_count; $i++) {
        $return_arr[$i]['content'] = curl_multi_getcontent($curl_arr[$i]);
        if ($return_arr[$i]['content'] === false) {
			$return_arr[$i]['content'] = '';
            $return_arr[$i]['error'] = curl_error($curl_arr[$i]);
        }
        curl_multi_remove_handle($master,$curl_arr[$i]);
        curl_close($curl_arr[$i]);
    }
    
    curl_multi_close($master);

    return $return_arr; /* returns the array of CURL fetches , or the curl_error if the request failed */
}