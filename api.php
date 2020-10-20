<?php
include("cache_ratelimiter.php");
header('Content-type: text/json; charset=utf-8');

$headers = [
        'Connection: keep-alive', 
        'Accept: application/json, text/javascript, */*; q=0.01',
        'X-Scope: 8a22163c-8662-4535-9050-bc5e1923df48',
        'X-Requested-With: XMLHttpRequest',
        'User-Agent: Mozilla/5.0 (X11; CrOS x86_64 13310.76.0) 
            AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.108 Safari/537.36',
        'Content-Type: application/json',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Dest: empty',
        'Accept-Language: sv,en-US;q=0.9,en;q=0.8',
        'Cookie: ASP.NET_SessionId=dhbjeydgz05drg4qovgyzqrm'];

function setReferrer($headers, $domain){
    $headers[] = sprintf('Referer: https://web.skola24.se/timetable/timetable-viewer/%s/', $domain);
}

if(isset($_POST['domain']) && isset($_POST['id'])){
    $value = encryptSignature($_POST["id"]);
    $time = time()+60*60*60*24*14;
    setcookie("domain", $_POST["domain"], $time);
    setcookie("signature", $value, $time);
    header("Location: /schema");
    return;
}

function getRenderKey(){
    $ch = curl_init();
    global $headers;
    
    $h = array_values($headers);
    setReferrer($h, "skola24.se");

    curl_setopt($ch, CURLOPT_URL, 'https://web.skola24.se/api/get/timetable/render/key');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    
    curl_close($ch);
    return json_decode($result, true)["data"]["key"];
}

function getRawScheduleBySignature($key, $domain, $signature, $width, $height, $wholeWeek){
    $ch = curl_init();
    global $headers;
    
    $weekDay = date("w");
    
    $body = [
    "renderKey"=> $key,
    "host"=> rawurlencode($domain),
    "unitGuid"=> "OWM1YWRhYTEtYTNmYi1mNzYzLWI5NDItZjkzZjE3M2VhNjA4",
    "scheduleDay"=> ($weekDay == 6||$wholeWeek) ? 0:$weekDay,
    "blackAndWhite"=> false,
    "width"=> $width,
    "height"=> $height,
    "selectionType"=> 4,
    "selection"=> $signature,
    "showHeader"=> false,
    "periodText"=> "",
    "week"=> ($weekDay == 6 || $weekDay == 0) ? date("W")+1 : date("W"),
    "year"=>date("Y"),
    "privateFreeTextMode"=> false,
    "privateSelectionMode"=>null
    ];

    echo json_encode($body);
    
    $h = array_values($headers);
    setReferrer($h, $signature);

    curl_setopt($ch, CURLOPT_URL, 'https://web.skola24.se/api/render/timetable');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    
    curl_close($ch);

    return json_decode($result, true);
}

function getSchedule($domain, $signature, $wholeWeek){
    echo "aaa";
    $sched = getRawScheduleBySignature(getRenderKey(), $domain, $signature, $_GET["width"], $_GET["height"], $wholeWeek);
    return json_encode($sched);
    /*
    $timetable = json_decode($sched["data"]["timetableJson"], true);
    
    $timetable["parsed"] = parseTextList($timetable["textList"]);
    
    return json_encode($timetable);*/
}

function encryptSignature($id){
    $ch = curl_init();
    global $headers;
    
    $h = array_values($headers);
    setReferrer($h, "skola24.se");

    curl_setopt($ch, CURLOPT_URL, 'https://web.skola24.se/api/encrypt/signature');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"signature\":\"" . $id . "\"}");
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    
    curl_close($ch);
    
    return json_decode($result, true)["data"]["signature"];
}

function parseTextList($textList){
  $timeMode = false;
  $items = [];
  
  $itemIdx = 0;
  $helperTimeMode = false;
  $subjectTimeModeStart = 0;

  for ($a = 0; $a < count($textList); $a++) {
      
    $current = $textList[$a]["text"];
    $time = strpos($current, ":");
    
      if($a == 0 && $time){
          $helperTimeMode = true;
      } else if($helperTimeMode && !$time){
          $helperTimeMode = false;
      }
    
    if($helperTimeMode)
        continue;
    
    if($subjectTimeModeStart === 0){

      if($current === "" || strlen($current) == 0){
          $item["name"] = $textList[$a-1]["text"];
          $item["teacher"] = $textList[$a+1]["text"];
          $item["room"] = $textList[$a+2]["text"];
          array_push($items, $item);
      }

      if(strpos($textList[$a+1]["text"], ":")){
        $subjectTimeModeStart = $a+1;
      }
    } else {
      $u = $a - $subjectTimeModeStart;

      if($u < count($items)*2){
        if(($u) % 2 != 0){
          $items[$itemIdx]["timeEnd"]   = transformTime($current);
          $itemIdx++;
        } else {
          $items[$itemIdx]["timeStart"] = transformTime($current);
        }
      }

    }
  }
  return $items;
}

function transformTime($timeStr){
  $a = explode(":", $timeStr);
  if(strlen($a[0]) == 1){
    $a[0] = "0" + $a[0];
  }

  $seconds = (+$a[0]) * 60 * 60 + (+$a[1]) * 60;
  return ($seconds * 1000);
}

$method = $_GET["method"];
if(!isset($method)){
    die("Method not specified.");
}

$ip = $_SERVER['REMOTE_ADDR'];

switch($method){
    case "getRenderKey":{
        echo getRenderKey();
        break;
    }

    case "getSchedule":{
        echo "a";
        /*
        $wholeWeek  = $_GET["wholeWeek"] === "true" ? true : false;
        $cachedData = getCache($ip, $wholeWeek);

        if(isset($cachedData)){
            echo $cachedData;
            return;
        }*/
        
        $data = getSchedule($_GET["domain"], $_GET["signature"], $wholeWeek);
        addToCache($ip, $data, $wholeWeek);
        echo $data;
        break;
    }

    /*    
    case "getRawSchedule":{
        
        if(!isset($_GET['id']))
            return;
            
            
        $tList = getRawScheduleBySignature(getRenderKey(), $_GET["domain"], $_GET["signature"], $_GET["width"], $_GET["height"]);
        $k = json_decode($tList["data"]["timetableJson"], true)["textList"];
        echo json_encode(parseTextList($k));
        
    }*/
    
    // TODO
    case "getParsedSchedule":{
        
        if(!isset($_GET['signature']))
            return;
            
        
        
    }
    
    default: {
        echo "Invalid method";
        break;
    }
}