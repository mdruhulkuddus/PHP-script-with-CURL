<?php

// $BASE_URL = 'http://localhost/dshe';
$BASE_URL = 'https://dshe.mmcm.gov.bd';
$INSTITUTE_INSERT_URL = $BASE_URL . '/administer/institutes/add';
$INSTITUTE_SEARCH_URL = $BASE_URL . '/administer/Institutes/instituteList?eiin=';
$INSTITUTE_UPDATE_URL = $BASE_URL . '/administer/institutes/update-institute-info/';
$LOGIN_URL = $BASE_URL . '/login';
$login_data = ['username' => 'dsheadmin', 'password' => 'dshepassword'];
$curl_handle = curl_init();
$login_cookie = null;
$nonUpdatedInstitutes = array();
$nonInsertedInstitutes = array();
$UpdateCount = 0;
$insertCount = 0;

function login($curl_handle, $login_data, $LOGIN_URL)
{
    global $login_cookie;
    echo PHP_EOL . "Logging in..." . PHP_EOL;
    curl_setopt($curl_handle, CURLOPT_URL, $LOGIN_URL);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $login_data);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HEADER, 1);

    $response = curl_exec($curl_handle);

    if (curl_errno($curl_handle)) {
        echo 'Login cURL error: ' . curl_error($curl_handle);
        exit();
    } else {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            if (substr($item, 0, 8) == 'CAKEPHP=') {
                $login_cookie = substr($item, 8);
                break;
            }
        }
        echo "Login Success:" . PHP_EOL . PHP_EOL;
        // echo "Login Success:".$login_cookie."\n";         
    }
}

function instituteExists($eiin, $institute_info)
{
    global $INSTITUTE_SEARCH_URL, $login_cookie;
    $curl_handle = curl_init();
    echo "Checking institute ... ( " . $eiin . " ) " . $institute_info['name'] . PHP_EOL;
    curl_setopt($curl_handle, CURLOPT_URL, $INSTITUTE_SEARCH_URL . $eiin);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_COOKIE, "CAKEPHP=" . $login_cookie);
    curl_setopt($curl_handle, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "GET");

    $respons = curl_exec($curl_handle);
    if ($error = curl_errno($curl_handle)) {
        echo 'cURL error!';
    } else {
        $decoded = json_decode($respons, true);
        // var_dump($decoded);
        curl_close($curl_handle);
        //return count($decoded['data']) > 0;
        return $decoded;
    }
}

function insert_institue($institute_info)
{
    global $INSTITUTE_INSERT_URL, $login_cookie, $nonInsertedInstitutes, $insertCount;
    $curl_handle = curl_init();
    echo "Inserting institute ... " . $institute_info['name'] . PHP_EOL;
    $request_data = http_build_query($institute_info);
    curl_setopt($curl_handle, CURLOPT_URL, $INSTITUTE_INSERT_URL);
    curl_setopt($curl_handle, CURLOPT_POST, 1);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HEADER, 1);
    curl_setopt($curl_handle, CURLOPT_COOKIE, "CAKEPHP=" . $login_cookie);
    curl_setopt($curl_handle, CURLINFO_HEADER_OUT, true);

    $respons = curl_exec($curl_handle);
    $info = curl_getinfo($curl_handle);

    // var_dump($info);
    // if ($error = curl_errno($curl_handle)) {
    //     echo $error;
    // } else {
    //     echo "inserted....!" . PHP_EOL;
    // }
    // //
    if ($info['http_code'] == 200) {
        echo sprintf("ERROR: Could Not Insert: %s (%s)", $institute_info['name'], $institute_info['code']).PHP_EOL;
        $nonInsertedInstitutes[$institute_info['code']] = $institute_info['name'];
        $insertCount--;
    }
    else if ($info['http_code'] == 302) {
        echo "Inserted....!" . PHP_EOL;
    }
    curl_close($curl_handle);
}

function update_institute($institue_id, $institute_info)
{
    global $login_cookie, $INSTITUTE_UPDATE_URL, $nonUpdatedInstitutes, $UpdateCount;
    $curl_handle = curl_init();
    echo "Updating institute ... " . $institute_info['name'] . PHP_EOL;
    $request_data = http_build_query($institute_info);
    curl_setopt($curl_handle, CURLOPT_URL, $INSTITUTE_UPDATE_URL . $institue_id);
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HEADER, 1);
    curl_setopt($curl_handle, CURLOPT_COOKIE, "CAKEPHP=" . $login_cookie);
    curl_setopt($curl_handle, CURLINFO_HEADER_OUT, true);

    $respons = curl_exec($curl_handle);
    $info = curl_getinfo($curl_handle);

    if ($info['http_code'] == 200) {
        echo sprintf("ERROR: Could Not Update: %s (%s)", $institute_info['name'], $institute_info['code']).PHP_EOL;
        $nonUpdatedInstitutes[$institute_info['code']] = $institute_info['name'];
        $UpdateCount--;
    }
    else if ($info['http_code'] == 500) {
        // echo sprintf("Updated successfuly: %s (%s)", $institute_info['name'], $institute_info['code']).PHP_EOL;
        echo "Updated....!" . PHP_EOL;
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
    global $curl_handle, $login_data, $LOGIN_URL, $nonUpdatedInstitutes, $UpdateCount, $insertCount, $nonInsertedInstitutes;
    $institutes_data = [];  

    echo "CSV (institute) File Path: ";
    $csv_file = trim(fgets(STDIN));
    // $csv_file = "testwithhigher.csv";

    if (empty($csv_file)) {
        echo "Empty CSV File" . PHP_EOL;
        exit();
    }

    if (!readCsv($csv_file, $institutes_data)) {
        echo "CSV file could not be opened.";
        exit();
    }

    login($curl_handle, $login_data, $LOGIN_URL);
    $headers = $institutes_data[0];
    foreach ($institutes_data as $rowNumber => $institute_info) {
        if ($rowNumber == 0)
            continue;
        $institute_request_data = array_combine($headers, $institute_info);
        // if($institute_request_data["is_higher"] == 0){
        //     unset($institute_request_data["is_higher"]);
        // }
        // if($institute_request_data["is_secondary"] == 0){
        //     unset($institute_request_data["is_secondary"]);
        // }
        echo $rowNumber . ".  ";
        $eiin = $institute_request_data['code'];
        $response_data = instituteExists($eiin, $institute_request_data);
        
        if (count($response_data['data']) > 0) { // update
            $institue_id = $response_data['data'][0]['id'];
            $institute_request_data['_method'] = 'PUT';
            $institute_request_data['ref'] = 'registered';
            $institute_request_data['status'] = '2';
            update_institute($institue_id, $institute_request_data);
            $UpdateCount++;
        } else {  // insert
            $institute_request_data['_method'] = 'POST';
            insert_institue($institute_request_data);
            $insertCount++;
        }
    }
    echo PHP_EOL . "Successfully Inserted - " . $insertCount . " - Institues." . PHP_EOL;
    echo PHP_EOL . "Successfully Updated - " . $UpdateCount . " - Institues." . PHP_EOL;

    if(!empty($nonInsertedInstitutes))
    {
        echo PHP_EOL ."Non Inserted institutes: ". PHP_EOL;
        print_r($nonInsertedInstitutes);
    }

    if(!empty($nonUpdatedInstitutes))
    {
        echo PHP_EOL ."Non Updated institutes: ". PHP_EOL;
        print_r($nonUpdatedInstitutes);
    }

    curl_close($curl_handle);
    exit();
}

main();
