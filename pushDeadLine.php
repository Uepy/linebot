<?php

require_once __DIR__ . '/vendor/autoload.php';

//アクセストークンでCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClient とシークレットを使いLineBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient,['channelSecret' => getenv('CHANNEL_SECRET')]);

$userID = 'U9a6675ed0946c116097b44bd69024fd4';
$message = "本日はシフト提出期限です\nシフトは提出しましたか？";


if(date("d") == 7 || date("d") ==25){
    $response = $bot->pushMessage($userID, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
}
if (!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
}
 ?>