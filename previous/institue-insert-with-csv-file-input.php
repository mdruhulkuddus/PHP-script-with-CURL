<?php

//$BASE_URL='http://localhost/dshe';
$BASE_URL = 'https://dshe.mmcm.gov.bd';
$INSTITUTE_INSERT_URL = $BASE_URL . '/administer/institutes/add';
$LOGIN_URL = $BASE_URL . '/login';
$login_data = ['username' => 'dsheadmin', 'password' => 'dshepassword'];
$curl_handle = curl_init();
$login_cookie = null;

function login($curl_handle, $login_data, $LOGIN_URL) {
    global $login_cookie;
    echo PHP_EOL."Logging in...".PHP_EOL;
    curl_setopt($curl_handle, CURLOPT_URL, $LOGIN_URL);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $login_data);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HEADER, 1);

    $response = curl_exec($curl_handle);

    if (curl_errno($curl_handle)) {
        echo 'Login cURL error: ' . curl_error($curl_handle);
        exit();
    }
    else{
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            if(substr($item,0,8)=='CAKEPHP='){
                $login_cookie = substr($item, 8);
                break;
            }
        }
        echo "Login Success:".PHP_EOL.PHP_EOL;         
        // echo "Login Success:".$login_cookie."\n";         
    }
}

function insert_institue($institute_info)
{
    global $INSTITUTE_INSERT_URL, $login_cookie;
    $curl_handle = curl_init();
    echo "Inserting institute ... ".$institute_info['name'].PHP_EOL;
    $request_data = http_build_query($institute_info);   
    curl_setopt($curl_handle, CURLOPT_URL, $INSTITUTE_INSERT_URL);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);    
    curl_setopt($curl_handle, CURLOPT_HEADER, 1);
    curl_setopt($curl_handle, CURLOPT_COOKIE, "CAKEPHP=".$login_cookie);
    curl_setopt($curl_handle, CURLINFO_HEADER_OUT, true);
    
    $respons = curl_exec($curl_handle);
    $info = curl_getinfo($curl_handle);
    if($error = curl_errno($curl_handle)){
        echo $error;
    }
    else{
        echo"inserted....!".PHP_EOL;
    }
    curl_close($curl_handle);
}


function readCsv(string $pathToCsvFile, array &$result): bool
{
    $handle = fopen($pathToCsvFile, 'r');
    if (!$handle) {
        return false;
    }

    while (false !== ($data = fgetcsv($handle, null))) {
        $result[] = $data;
    }

    fclose($handle);
    return true;
}



function main()
{
    global $curl_handle, $login_data, $LOGIN_URL;
    $institutes_data = [];

    echo "CSV (institute) File Path: ";
    $csv_file = trim(fgets(STDIN)); 

    if(empty($csv_file)) {
        echo "Empty CSV File".PHP_EOL;
        exit(); 
    }

    if (!readCsv($csv_file, $institutes_data)) {
        echo "CSV file could not be opened.";
        exit();
    }    

    login($curl_handle, $login_data, $LOGIN_URL);
    $InstitueCount = 0;
    $headers = $institutes_data[0];        
    foreach ($institutes_data as $rowNumber => $institute_info) {
        if ($rowNumber == 0) continue;
        $institute_request_data = array_combine($headers, $institute_info);        
        $institute_request_data['_method'] = 'POST';
        insert_institue($institute_request_data);
        $InstitueCount++;
    }
     echo PHP_EOL."Successfully Inserted - ".$InstitueCount." - Institues.".PHP_EOL;
    curl_close($curl_handle);
    exit();
}

main();
