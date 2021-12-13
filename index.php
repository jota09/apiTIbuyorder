<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ENV = parse_ini_file("api.env");

main();

function main(){

    $tokenBearer = getToken();

    $productsAvailable = getProducts($tokenBearer);

    foreach ($productsAvailable as $product){
        if(is_int($product["Quantity"])){
            if($product["Quantity"]>0){
                echo "<pre>".print_r($product,1)."</pre>";
            }
        }
    }

    print_r("Success \r\n");
}

/////// ------------- Functions ------------------- //////////////////

// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value

function callAPI($header = array("Content-Type:application/json"), $method, $url, $data = false)
{
    $curl = curl_init();

    switch ($method)
    {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data && $header[0] == "Content-Type:application/json")
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            if ($data && $header[0] == "Content-Type:application/x-www-form-urlencoded")
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    // Optional Authentication:
    //curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    //curl_setopt($curl, CURLOPT_USERPWD, "username:password");

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    if( ! $result = curl_exec($curl))
    {
        trigger_error(curl_error($curl));
    }
    // execute
    $result = curl_exec($curl);
    if (empty($result)) {
        // some kind of an error happened
        die(curl_error($curl));
        curl_close($ch); // close cURL handler
    } else {
        $info = curl_getinfo($curl);
        curl_close($curl); // close cURL handler

        if (empty($info['http_code'])) {
            die("No HTTP code was returned");
        } else {
            // load the HTTP codes
            $http_codes = parse_ini_file("statushttp.env");

            $logs = fopen("history.log", "a+") or die("Unable to open file!");
            /* echo results
            echo "The server responded: <br />";
            echo $info['http_code'] . " " . $http_codes[$info['http_code']];
            echo "<br />Url: <br />";
            echo $url;
            echo "<br /><br />";
            */
            $response = "The server responded:".PHP_EOL . $info['http_code'] . " " . $http_codes[$info['http_code']].PHP_EOL . "URL:" . $url . PHP_EOL. "Result: " . $result . PHP_EOL . " Unix time: ".time().PHP_EOL.PHP_EOL;
            fwrite($logs,$response );
            fclose($logs);
        }

    }

    return json_decode($result,true);
}

/// Validate token in file token.txt (to reuse token cause "expires_in": 3599)
/// Else method will call method auth and save again token during 3600 secs

function getToken(){
    global $ENV;

    $method = $ENV["methodAuth"];
    $url = $ENV["urlAuth"];
    $expire_in = $ENV["expire_inAuth"];
    $data = [
        "grant_type" => $ENV["grant_type"],
        "client_id" => $ENV["client_id"],
        "client_secret" => $ENV["client_secret"],
    ];
    if (file_exists("token.txt")) {
        $myfile = fopen("token.txt", "r") or die("Unable to open file!");
        $content = fread($myfile,filesize("token.txt"));
        $contentaux = explode(" ", $content);
        fclose($myfile);
        if((time() - $contentaux[1]) > $expire_in){
            $authentication = callAPI(array("Content-Type:application/x-www-form-urlencoded"),$method,$url,$data);
            $tokenBearer = $authentication["access_token"];
            $myfile = fopen("token.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $tokenBearer." ".time());
            fclose($myfile);
        }else
            $tokenBearer = $contentaux[0];
    } else {
        $authentication = callAPI(array("Content-Type:application/x-www-form-urlencoded"),$method,$url,$data);
        $tokenBearer = $authentication["access_token"];
        $myfile = fopen("token.txt", "w") or die("Unable to open file!");
        fwrite($myfile, $tokenBearer." ".time());
        fclose($myfile);
    }
    return $tokenBearer;
}

function getProducts($token){
    global $ENV;

    $method = $ENV["methodCatalog"];
    $handle = @fopen("products.txt", "r");
    $products = [];
    if ($handle) {
        while (($buffer = fgets($handle)) !== false) {
            $parts = preg_split('/\s+/', $buffer);
            $url = $ENV["urlCatalog"].$parts[0];
            $arrayResponse = callAPI(array("Content-Type:application/json","Authorization: Bearer ".$token),$method,$url);
            if(isset($arrayResponse["ProductIdentifier"])){
                $product["ProductIdentifier"] = $arrayResponse["ProductIdentifier"];
                $product["Quantity"] = ($parts[1]>$arrayResponse["Quantity"])?$arrayResponse["Quantity"]:$parts[1];
            }else{
                $product["ProductIdentifier"] = $parts[0];
                $product["Quantity"] = "No found result";
            }
            $products[] = $product;
        }
        if (!feof($handle)) {
            echo "Error: unexpected fgets() fail\n";
        }
        fclose($handle);
    }

    return $products;
}