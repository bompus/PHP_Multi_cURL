/* Example: */
$url_arr = array('http://www.google.com','http://www.yahoo.com','http://www.bing.com');
$response_arr = PHP_Multi_cURL($url_arr);
var_dump($response_arr);


/*
 * PHP_Multi_cURL
 *
 * @author bompus
 * @url http://github.com/bompus/PHP_Multi_cURL
 *
 * Fetches multiple URL's via CURL and returns their output or errors.
 * Only supports GET for now
 *
 * @path (array) a URL or an array of URL's to fetch
 * @return (array)
 */
function PHP_Multi_cURL($url_arr)
{
    $error = 'Unknown Error';
    $return_arr = array();
    if (!is_array($url_arr)) { $url_arr = array($url_arr); }
    
    $url_count = count($url_arr);
    $curl_arr = array();
    $master = curl_multi_init();
    
    for($i = 0; $i < $url_count; $i++)
    {
        $return_arr[$i] = false;
        $curl_arr[$i] = curl_init($url_arr[$i]);
        
        curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl_arr[$i], CURLOPT_TIMEOUT, 60);
        curl_setopt($curl_arr[$i], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
        
        curl_multi_add_handle($master, $curl_arr[$i]);
    }
    
    $active = null;
        
    $process = curl_multi_exec($master, $active);
    while ($process === CURLM_CALL_MULTI_PERFORM)
    {
        $process = curl_multi_exec($master, $active);
        slaap_ms(0.01);
    }
    
    while (($active >= 1) && ($process === CURLM_OK))
    {
        /* the 0.01 timeout to curl_multi_select is the same as slaap_ms(0.01) */
        /* it was set to 3 before and blocked for 3 seconds before, even if something responded before 3 seconds */
        
        if (curl_multi_select($master, 0.01) != -1)
        {
           $process = curl_multi_exec($master, $active);
           while ($process === CURLM_CALL_MULTI_PERFORM)
           {
               $process = curl_multi_exec($master, $active);
               slaap_ms(0.01);
           }
        }
    }

    for($i = 0; $i < $url_count; $i++)
    {
        $return_arr[$i] = curl_multi_getcontent($curl_arr[$i]);
        
        if ($return_arr[$i] == false)
        {
            /* you could optionally have $return_arr[$i] = false; */
            $return_arr[$i] = curl_error($curl_arr[$i]);
        }
        
        curl_multi_remove_handle($master,$curl_arr[$i]);
        curl_close($curl_arr[$i]);
    }
    
    curl_multi_close($master);

    return $return_arr; /* returns the array of CURL fetches , or the curl_error if the request failed */
}

/*
 * slaap_ms
 *
 * Only supports GET for now
 *
 * @seconds (float) Number of seconds to sleep for, accepts 0.5, etc.
 */
function slaap_ms($seconds) // glorified sleep function allows for half seconds, etc
{
    $seconds = abs($seconds); 
    if ($seconds < 1) { usleep($seconds*1000000); } else { sleep($seconds); }     
}