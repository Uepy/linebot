<?php

require_once __DIR__ . '/vendor/autoload.php';

//アクセストークンでCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClient とシークレットを使いLineBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient,['channelSecret' => getenv('CHANNEL_SECRET')]);

// シフト提出を促す とりあえずuserIDは僕のだけ
$userID = 'U9a6675ed0946c116097b44bd69024fd4';
$message = "本日はシフト提出期限です\nシフトは提出しましたか？";

// heroku Scheduleで毎日9時に呼び出されるように設定してある
// 呼び出された日が8日か23日の場合はメッセージを送信する
if(date("d") == 7 || date("d") ==22){
    $response = $bot->pushMessage($userID, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
}
if (!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
}
 ?>