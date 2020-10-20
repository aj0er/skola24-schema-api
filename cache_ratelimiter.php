<?php

$requests = json_decode(file_get_contents("requests.json"), true);

if($requests == null)
    $requests = [];

function getCache($ip, $wholeWeek){
    global $requests;
    for($i=0;$i<count($requests);$i++){
        $request = $requests[$i];
        if(($request["ip"] == $ip) && $request["wholeWeek"] == $wholeWeek){
            if((getTimeMillis() - $request["requestTime"]) > 1000*60*5){
                \array_splice($requests, $i, $i);
                file_put_contents("requests.json", json_encode($requests));
            } else {
                return $request["data"];
            }
        }
    }
    
    return null;
}

function addToCache($ip, $data, $wholeWeek){
    global $requests;
    
    $obj = [
        "ip" => $ip,
        "data" => $data,
        "wholeWeek" => $wholeWeek,
        "requestTime" => getTimeMillis()
    ];
    
    array_push($requests, $obj);
    file_put_contents("requests.json", json_encode($requests));
}

function getTimeMillis(){
    return round(microtime(true) * 1000);
}