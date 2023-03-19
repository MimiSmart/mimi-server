<?php

date_default_timezone_set('Europe/Moscow');
header("Content-Type: application/json; text/xml");
define("BASE_DIR", __DIR__ . "/");
define("XML_FILE", BASE_DIR . "logic.xml"); //set chmod 666
define("LOG_FILE", BASE_DIR . "sh2/logs/logs.txt");

//begin smart house server settings ##################################
define("HOST", "127.0.0.1");
define("PORT", 55555);
define("SECRET_KEY", "1234567890123456");


$globalSettings = array();
$globalSettings["shs"] = array();
$globalSettings["shs"]["host"] = HOST;
$globalSettings["shs"]["port"] = PORT;
$globalSettings["shs"]["secret_key"] = SECRET_KEY;
$globalSettings["shs"]["logFile"] = LOG_FILE;
$globalSettings["debug"] = FALSE;
$globalSettings["logFile"] = LOG_FILE;

require_once BASE_DIR . 'AES128.php';
require_once BASE_DIR . 'SHClient.php';

// $fields = array(
//     "addr" => "588:40",
//     "scale" => 10,
//     "from" => "2022-12-16 15:00:00",
//     "to" => "2022-12-17 15:00:00"
// );

$fields = array(
    "request" => null,
    "addr" => null,
    "scale" => null,
    "from" => null,
    "to" => null,
    "message" => null,
    "message_type" => null,
    "id" => null,
    "subid" => null,
    "command" => null,
);

if ($_REQUEST['request'] === null) {
    print_r('MimiSmart API 2.0');
} elseif ($_REQUEST['request'] === 'get-history') {
    get_history($fields);
} elseif ($_REQUEST['request'] === 'get-xml') {
    get_xml();
} elseif ($_REQUEST['request'] === 'send-message') {
    send_message($fields);
} elseif ($_REQUEST['request'] === 'send-command') {
    send_command_to_sh($fields);
} elseif ($_REQUEST['request'] === 'get-state') {
    get_state($fields);
} else {
    print_r('Request not supported');
}


function get_xml()
{
    $shClient = new SHClient(HOST, PORT, SECRET_KEY, LOG_FILE);

    if ($shClient->run2()) {
        $xml = $shClient->getXml(TRUE);
        print_r($xml);
        exit;
    } else {
        print_r($shClient->errors);
    }
}

function get_state($fields)
{
    foreach ($fields as $field => $value) {
        if (array_key_exists($field, $_REQUEST)) $fields[$field] = $_REQUEST[$field];
    }

    // list($id, $subid) = explode(":", $fields["addr"]);
    $shClient = new SHClient(HOST, PORT, SECRET_KEY, LOG_FILE);

    if ($shClient->run2()) {

        $state = $shClient->getDeviceStateByAddr($fields["addr"]);
        print_r($state);
        exit;
    } else {
        print_r($shClient->errors);
    }
}

function send_command_to_sh($fields)
{
    foreach ($fields as $field => $value) {
        if (array_key_exists($field, $_REQUEST)) $fields[$field] = $_REQUEST[$field];
    }

    $shClient = new SHClient(HOST, PORT, SECRET_KEY, LOG_FILE);

    if ($shClient->run2()) {

        $shClient->sendCommand($fields['command'], TRUE);
        print_r('Command sent.');
        exit;
    } else {
        print_r($shClient->errors);
    }
}

function send_message($fields)
{

    foreach ($fields as $field => $value) {
        if (array_key_exists($field, $_REQUEST)) $fields[$field] = $_REQUEST[$field];
    }

    list($id, $subid) = explode(":", $fields["addr"]);
    print_r($fields['message'] . ' ' . $fields['message_type'] . ' ' . $id . ' ' . $subid . PHP_EOL);

    $shClient = new SHClient(HOST, PORT, SECRET_KEY, LOG_FILE);

    if ($shClient->run2()) {

        $shClient->sendMessage($fields['message'], $fields['message_type'], $id, $subid);
        print_r('Message sent.');
        exit;
    } else {
        print_r($shClient->errors);
    }
}


function get_history($fields)
{
    foreach ($fields as $field => $value) {
        if (array_key_exists($field, $_REQUEST)) $fields[$field] = $_REQUEST[$field];
    }

    $showSeveralDays = false;

    $shClient = new SHClient(HOST, PORT, SECRET_KEY, LOG_FILE);

    if ($shClient->run2()) {
        //begin get logic xml
        $methodName = "stopListenEvents";
        $callback = array($shClient, $methodName);

        $shClient->sendCommandToSH('get-shc', $callback);
        $shClient->listenEventsOnMsg();
        //end get logic xml

        $params = array();
        $re = "/\d{4}\-\d{2}\-\d{2}\ \d{2}\:\d{2}\:\d{2}/";

        if (!is_null($fields["scale"]) && $fields["scale"] > 1) $params["scale"] = $fields["scale"];

        if (
            !is_null($fields["from"]) && !is_null($fields["to"]) &&
            preg_match($re, $fields["from"]) && preg_match($re, $fields["to"]) &&
            strtotime($fields["to"]) > strtotime($fields["from"])
        ) {

            $params["fromDate"] = gmdate("U", (int)strtotime($fields["from"]));
            $params["toDate"] = gmdate("U", (int)strtotime($fields["to"]));
            $params["toDateUTS"] = (int)strtotime($fields["to"]);
            if (strtotime($fields["to"]) > (strtotime($fields["from"]) + (3600 * 24))) $showSeveralDays = false;
        } else {
            $params["fromDate"] = gmdate("U", time() - 3600);
            $params["toDate"] = gmdate("U");
            $params["toDateUTS"] = time();
        }

        if (!is_null($fields["addr"]) && is_string($fields["addr"]) && strpos($fields["addr"], ":") !== FALSE) $fields["addr"] = array($fields["addr"]);

        if (is_array($fields["addr"]) && count($fields["addr"])) {
            $addrNumber = count($fields["addr"]);
            $chartDataTmp = array();
            foreach ($fields["addr"] as $addr) {
                $dataTmp = array();
                if (strpos($addr, ":") !== FALSE) {
                    list($id, $subid) = explode(":", $addr);
                    $itemType = $shClient->getItemType($id, $subid);
                    // $this->msg = "itemType: " . $itemType;

                    if ($itemType != "") {
                        $params["id"] = $id;
                        $params["subid"] = $subid;
                        $params["devtype"] = $itemType;

                        //get history of device
                        if ($showSeveralDays) {
                            $fromTmp = (int)strtotime($fields["from"]);
                            $toTmp = (int)strtotime($fields["to"]);
                            while ($fromTmp < $toTmp) {
                                $params["fromDate"] = gmdate("U", $fromTmp);
                                $params["toDate"] = gmdate("U", ($toTmp));
                                $params["toDateUTS"] = $toTmp;
                                $dataPart = $shClient->getDeviceHistory($params);
                                $dataResult = array_merge($dataTmp, $dataPart);
                                $dataTmp = $dataResult;
                                $fromTmp = $fromTmp + (3600 * 24);
                            }
                        } else $dataTmp = $shClient->getDeviceHistory($params);
                    }
                }
                $chartDataTmp[] = $dataTmp;
            }
            if ($addrNumber > 1) {
                $timesUniq = array();
                foreach ($chartDataTmp as $key1 => $deviceData) {
                    foreach ($deviceData as $key2 => $data) {
                        $time = $data[0];
                        $value = $data[1];

                        if (!array_key_exists($time, $timesUniq)) {
                            $timesUniq[$time] = array();
                            if ($key1 > 0) {
                                for ($i = 0; $i < $key1; $i++) {
                                    $timesUniq[$time][] = null;
                                }
                            }
                        }
                        $timesUniq[$time][] = $value;
                    }
                }
                $chartDataTmp = array();
                foreach ($timesUniq as $time => $values) {
                    $padedValues = array_pad($values, $addrNumber, null);
                    $chartDataTmp[] = array_merge(array($time), $padedValues);
                }
                $chartData = $chartDataTmp;
            } elseif (array_key_exists(0, $chartDataTmp)) $chartData = $chartDataTmp[0];
        }
    } else {
        print_r($shClient->errors);
    }

    print str_replace('"', "", json_encode($chartData));
    exit;
}
