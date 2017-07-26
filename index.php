<?php

require_once __DIR__ . '/vendor/autoload.php';

//アクセストークンでCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClient とシークレットを使いLineBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient,['channelSecret' => getenv('CHANNEL_SECRET')]);


// LINE Messaging API がリクエストに付与した署名を取得
$signature = $_SERVER["HTTP_" . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];
try {
  // 署名が正当かチェック 正当であればリクエストをパースして配列へ
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log("parseEventRequest failed. InvalidSignatureException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log("parseEventRequest failed. UnknownEventTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log("parseEventRequest failed. UnknownMessageTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log("parseEventRequest failed. InvalidEventRequestException => ".var_export($e, true));
}
//配列に格納された各イベントをループ処理
foreach ($events as $event) {
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    $bot->replyText($event->getReplyToken(), "そんなもん送られても何もできません");
    error_log('Non message event has come');
    continue;
  }
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    $bot->replyText($event->getReplyToken(), "そんなもん送られても困ります");
    error_log('Non text message has come');
    continue;
  }
  /*
  // テキスト返信
  $bot->replyText($event->getReplyToken(), "今日のシフトです");
  error_log('Bot has replyed massage. This bot is running on github');
  */
  
  // ユーザーIDをコンソールに表示
  error_log("userID : " . $event->getUserId());
  
  // とりあえずeventからuserIdとってきて無理やりpush通知
  $bot->pushMessage($event->getUserId(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
  // そのあとreplytoken使って画像を送信
  replyImageMessage($bot,$event->getReplyToken(),
  'https://'.$_SERVER['HTTP_HOST'].'/shifitpic/20170726.jpg',
  'https://'.$_SERVER['HTTP_HOST'].'/shifitpic/20170726.jpg');
  
}

// 画像を返信 引数(LINEBot,返信先,画像URL,サムネイルURL)
function replyImageMessage($bot,$replyToken,$originalImageUrl,$previewImageUrl){
  $response = $bot->replyMessage($replyToken,
  new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalImageUrl,$previewImageUrl));
 if (!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
 }
}
 ?>