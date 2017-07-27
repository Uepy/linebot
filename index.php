<?php

require_once __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Asia/Tokyo');

// lineIDと名前を一致させるためのテーブル名を定義
define('TABLE_TO_IDENTIFY','tbl_user_identify');

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
    $bot->replyText($event->getReplyToken(), "そんなんされても何もできません");
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
  $bot->replyText($event->getReplyToken(), $event->getText() . "今日のシフトです");
  error_log('Bot has replyed massage. This bot is running on github');
  */
  
  // ユーザーIDをコンソールに表示
  error_log("\nuserID : " . $event->getUserId());
  
  
  // ユーザーIDが登録されてなければ登録する
  if(is_registeredUserId($event->getUserId())){
    echo "\n Your userID is already registerd.";
  }else{
    registerUserId($event->getUserId());
    echo "\n Your userID is registerd in database now.";
  }
  
  
  // ユーザーからのテキストによってシフト画像を送信する
  // phpの例外処理がよくわからん！から、このtry-catch文はよくないかもしれない
  try{
    // 今日のパターン
    if($event->getText() == "今日" || $event->getText() == "きょう" ){
      // thedayには「20170620」みたいに格納される
      $theday = date('Ymd');
      $message = date('n月d日')."のシフトです";
      // ファイルのディレクトリを指定 
      // 絶対パス指定
      $filename = 'https://'.$_SERVER['HTTP_HOST'].'/shiftpic/'.$theday.'.jpg'; 
      // 相対パス指定
      //$filename = '../shiftpic/'.$theday.'.jpg'; 
      // とりあえずeventからuserIdとってきて無理やりpush通知
      $bot->pushMessage($event->getUserId(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
      // そのあとreplytoken使って画像を送信
      replyImageMessage($bot,$event->getReplyToken(),$filename,$filename);
  
    // 明日のパターン
    }else if($event->getText() == "明日" || $event->getText() == "あした"){
      $theday = date('Ymd',strtotime('+1 day'));
      $message = date('n月d日',strtotime('+1 day'))."のシフトです";
      $filename = 'https://'.$_SERVER['HTTP_HOST'].'/shiftpic/'.$theday.'.jpg';
      // とりあえずeventからuserIdとってきて無理やりpush通知
      $bot->pushMessage($event->getUserId(), new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
      // そのあとreplytoken使って画像を送信
      replyImageMessage($bot,$event->getReplyToken(),$filename,$filename);
    }
    // ファイルがない場合はその旨のメッセージを送信する
  }catch(Exception $e){
    $bot->replyText($event->getReplyToken(),
    "シフト画像が見つかりませんでした\nまだ登録されていないかもしれません");
  }

  // getReplyTokenが生きてる(まだ何も返信してない)場合、挨拶しとく
  if($event->getReplyToken()){
    if(date('G') > 6 && date('G') < 12){
      $bot->replyText($event->getReplyToken(),"おはようございます");
    }else if(date('G') >= 12 && date('G') < 18){
      $bot->replyText($event->getReplyToken(),"こんにちは");
    }else if(date('G') >= 2 && date('G') < 6){
      $bot->replyText($event->getReplyToken(),"はよ寝なさい");
    }else{
      $bot->replyText($event->getReplyToken(),"こんばんは");
    }
  }
      
  

  

  
}

// 画像を返信 引数(LINEBot,返信先,画像URL,サムネイルURL)
function replyImageMessage($bot,$replyToken,$originalImageUrl,$previewImageUrl){
  $response = $bot->replyMessage($replyToken,
  new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalImageUrl,$previewImageUrl));
 if (!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
 }
}

// データベースへの接続を管理するクラス
class dbConnection{
  // インスタンス
  protected static $db;
  // コンストラクタ
  private function __construct(){
    
    try{
      // 環境変数からデータベースへの接続情報を取得
      $url = parse_url(getenv('DATABASE_URL'));
      // データソース
      $dsn = sprintf('pgsql:host=%s;dbname=%s',$url('host'),substr($url['path'],1));
      // 接続を確立
      self::$db = new PDO($dsn,$url['user'],$url['pass']);
      // エラー時には例外を投げるように設定
      self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $e){
      echo 'Connection Error: ' . $e->getMessage();
    }
  }

    // シングルトン 存在しない場合のみインスタンス化
    public static function getConnection(){
      if(!self::$db){
        new dbConnection();
      }
      return self::$db;
    }
    
}

// TABLE_TO_IDENTIFYにユーザーidを登録
// is_registeredUserId()と併用すべし
// ** 狭域かつ限定的にしかシフト通知君を公開していないのでこれでもいいかもしれないが
// システムが大きくなればこれはよくない気がする **
function registerUserId($userId){
  $dbh = dbConnection::getConnection();
  // pgp_sym_encryptは暗号化 引数は(暗号化するデータ, 共有鍵) 共有鍵はherokuに登録してある
  $sql = 'insert into ' . TABLE_TO_IDENTIFY . '(userid) values
  (pgp_sym_encrypt(?,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
}


// TABLE_TO_IDENTIFYにユーザーidがあるか調べる すでにある場合true ない場合はfalseを返す
function is_registeredUserId($userId){
  $dbh = dbConnection::getConnection();
  // pgp_sym_decryptは複合化
  $sql = 'select userid from' . TABLE_TO_IDENTIFY . 'where ? =
  (pgp_sym_decrypt(?,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  // レコードが存在しなければfalse
  if(!($row = $sth->fetch())){
    return false;
  }else{
    // ある場合はtrue
    return true;
  }
}
  
  
  

 ?>