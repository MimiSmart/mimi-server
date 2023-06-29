<?php

date_default_timezone_set('Europe/Moscow');
header("Content-Type: application/json; text/xml");
define("BASE_DIR", __DIR__ . "/");
define("XML_FILE", "/home/sh2/logic.xml");
define("LOG_FILE", "/home/sh2/logs/logs.txt");
define("DB_FILENAME", "/home/settings/db/mimismart.db");
define("KEYS", "/home/sh2/keys.txt");

//begin smart house server settings ##################################
define("HOST", "127.0.0.1");
define("PORT", 55555);

$globalSettings = array();
$globalSettings["shs"] = array();
$globalSettings["shs"]["host"] = HOST;
$globalSettings["shs"]["port"] = PORT;
$globalSettings["shs"]["secret_key"] = $_REQUEST['key'];
$globalSettings["shs"]["logFile"] = LOG_FILE;
$globalSettings["debug"] = FALSE;
$globalSettings["logFile"] = LOG_FILE;

require_once BASE_DIR . "AES128.php";
require_once BASE_DIR . "SHClient.php";
require BASE_DIR . "/vendor/autoload.php";

use jalder\Upnp\Mediaserver;
use jalder\Upnp\Renderer;


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
    "img_name" => null,
    "image" => null,
);


// Авторизация по токену ############################
// $authHeader = apache_request_headers();
// $authkey = $authHeader['Authorization'];
// $token = str_replace('Bearer ', '', $authkey);

// if (SECRET_KEY == $token) {
//     $auth_success = true;
// }
// if (!$auth_success) {
//     http_response_code(401);
//     die(json_encode(['error' => 'Unauthorized']));
// }


// Проверка параметров запроса ######################
if ($_REQUEST['request'] === null) {
    print_r('MimiSmart API 2.0');
} elseif ($_REQUEST['request'] === 'get-history') {
    get_history($fields);
} elseif ($_REQUEST['request'] === 'get-xml') {
    get_xml();
} elseif ($_REQUEST['request'] === 'send-message') {
    send_message();
} elseif ($_REQUEST['request'] === 'send-command') {
    send_command_to_sh();
} elseif ($_REQUEST['request'] === 'get-state') {
    get_state();
} elseif ($_REQUEST['request'] === 'get-image') {
    get_image();
} elseif ($_REQUEST['request'] === 'save-image') {
    save_image();
} elseif ($_REQUEST['request'] === 'get-media-servers') {
    get_media_servers();
} elseif ($_REQUEST['request'] === 'get-media') {
    get_media();
} else {
    print_r('Request not supported');
}


function check_key($reqKey)
{
    $lines = file(KEYS);
    foreach ($lines as $line) {
        $keys = explode(" ", $line);
        $key = $keys[0];
        if ($reqKey == $key) {
            $auth_success = true;
            break;
        } else {
            $auth_success = false;
        }
    }
    return $auth_success;
}

// Получение логики XML ######################
function get_xml()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_key($_REQUEST['key']) == true) {

            $shClient = new SHClient(HOST, PORT, $_REQUEST['key'], LOG_FILE);

            if ($shClient->run2()) {
                $xml = $shClient->getXml(TRUE);
                print_r($xml);
                exit;
            } else {
                print_r($shClient->errors);
            }
        } else {
            http_response_code(401);
            die(json_encode(['request' => $_REQUEST['request'], 'error' => 'Unauthorized']));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Получение статуса устройств ######################
function get_state()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_key($_REQUEST['key']) == true) {

            $shClient = new SHClient(HOST, PORT, $_REQUEST['key'], LOG_FILE);

            if ($shClient->run2()) {

                $state = $shClient->getDeviceStateByAddr($_REQUEST['addr']);
                print_r(json_encode($state));
                exit;
            } else {
                print_r($shClient->errors);
            }
        } else {
            http_response_code(401);
            die(json_encode(['request' => $_REQUEST['request'], 'error' => 'Unauthorized']));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


function get_media_servers()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $mediaserver = new Mediaserver();

        $servers = $mediaserver->discover();

        if (!count($servers)) {
            print_r('no upnp mediaservers found' . PHP_EOL);
        } else {
            print_r(json_encode($servers));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


function get_media()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $renderer = new Renderer();
        $renderers = $renderer->discover();


        if (!count($renderers)) {
            print_r('no upnp renderers found' . PHP_EOL);
        } else {
            print_r(json_encode($renderers));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Выгрузка изображения на сервер ######################
function save_image()
{

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            try {
                $filename = time() . '_' . $_FILES['image']['name'];
                $path = '/storage/images/' . $filename;

                $db = new SQLite3(DB_FILENAME);
                $img_table = "CREATE TABLE IF NOT EXISTS images (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR NOT NULL,
                    path VARCHAR NOT NULL
                )";
                $db->exec($img_table);
                $db->exec("INSERT INTO images (name, path) VALUES ('$filename', '$path')");
                // $last_id = $db->lastInsertRowID();
                $db->close();

                move_uploaded_file($_FILES['image']['tmp_name'], $path);
                echo json_encode(array('success' => true, 'name' => $filename));
            } catch (Exception $e) {
                header('HTTP/1.1 400 Bad Request');
                echo 'Error: ',  $e->getMessage(), "\n";
            }
        } else {
            header('HTTP/1.1 400 Bad Request');
            echo 'Error uploading file.';
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Загрузка изображения с сервера ######################
function get_image()
{

    $fields = json_decode(file_get_contents('php://input'), true);

    if (!isset($fields['img_id'])) {
        header('HTTP/1.1 400 Bad Request');
        die('Missing required fields');
    }

    if ($_SERVER['CONTENT_TYPE'] != 'application/json') {
        header('HTTP/1.1 400 Bad Request');
        die('This endpoint only accepts JSON requests');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        try {
            $db = new SQLite3(DB_FILENAME);
            $img_id = $fields['img_id'];
            $image = $db->querySingle("SELECT path FROM images WHERE id = $img_id");
            if ($image) {
                $img_base64 = file_get_contents($image);
                header('Content-Type: application/json');
                // 'data: '.mime_content_type($img_file).';base64,'.$imgData;
                echo json_encode(array('image' => $img_base64));
            } else {
                header('HTTP/1.1 404 Not Found');
                echo $image;
                die('Image not found');
            }
        } catch (Exception $e) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Error: ',  $e->getMessage(), "\n";
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Отправка команды серверу ######################
function send_command_to_sh()
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_key($_REQUEST['key']) == true) {

            $shClient = new SHClient(HOST, PORT, $_REQUEST['key'], LOG_FILE);

            if ($shClient->run2()) {

                $shClient->sendCommand($fields['command'], TRUE);
                print_r('Command sent.');
                exit;
            } else {
                print_r($shClient->errors);
            }
        } else {
            http_response_code(401);
            die(json_encode(['request' => $_REQUEST['request'], 'error' => 'Unauthorized']));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Отправка сообщения ######################
function send_message()
{

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_key($_REQUEST['key']) == true) {

            list($id, $subid) = explode(":", $_REQUEST['addr']);

            $shClient = new SHClient(HOST, PORT, $_REQUEST['key'], LOG_FILE);

            if ($shClient->run2()) {

                $shClient->sendMessage($_REQUEST['message'], $_REQUEST['message_type'], $id, $subid);
                print_r('Message sent.');
                exit;
            } else {
                print_r($shClient->errors);
            }
        } else {
            http_response_code(401);
            die(json_encode(['request' => $_REQUEST['request'], 'error' => 'Unauthorized']));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }
}


// Получение статистики устройств ######################
function get_history($fields)
{
    foreach ($fields as $field => $value) {
        if (array_key_exists($field, $_REQUEST)) $fields[$field] = $_REQUEST[$field];
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_key($_REQUEST['key']) == true) {
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
        } else {
            http_response_code(401);
            die(json_encode(['request' => $_REQUEST['request'], 'error' => 'Unauthorized']));
        }
    } else {
        http_response_code(401);
        die(json_encode(['error' => 'Unsupported request method']));
    }

    print str_replace('"', "", json_encode($chartData));
    exit;
}
