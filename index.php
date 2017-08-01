<?php

require_once __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Asia/Tokyo');

// lineIDと名前を一致させるためのテーブル名を定義
define('TABLE_TO_IDENTIFY','tbl_user_identify');
// 従業員情報のテーブル名を定義
define('WORKERS_INFO','tbl_workers_info');
// アプリケーション管理者の名前
define('APP_MANAGER','上田');

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
// 配列に格納された各イベントをループ処理
foreach ($events as $event) {
  
  /*
  // テキスト返信
  $bot->replyText($event->getReplyToken(), $event->getText() . "今日のシフトです");
  error_log('Bot has replyed massage. This bot is running on github');
  */
  
  // ユーザーIDの取得
  $userId = $event->getUserId();
  // イベントがPostBackの時、
  if($event instanceof \LINE\LINEBot\Event\PostbackEvent){
    
    switch ($event->getPostbackData()) {
      
      case 'cmd_cancel':
        setUserName($userId,'');
        setReady2Identify($userId,'false');
        $bot->replyText($event->getReplyToken(), "登録はキャンセルされました");
        break;
        
        
      case 'cmd_OK':
        $userName = getUserName($userId);
        setReady2Identify($userId,'false');
        setIsIdentified($userId,'true');
        setUserId($userId,$userName);
        $bot->replyText($event->getReplyToken(), "あなたを「".$userName."」さんで登録しました");
        break;
        
      default :
        // TABLE_TO_IDENTIFYのready_to_idnetifyがtrueの時のみ通過
        error_log("\nここ通りました1");
        if(getReady2Identify($userId)){
          error_log("\nここ通りました2");
          // 一時的に名前を登録、is_identifiedはtrueにしない
          setUserName($userId,substr($event->getPostbackData(),4));
           error_log("\nここ通りました3");
          replyConfirmTemplate($bot, $event->getReplyToken(),
          'あなたは'. substr($event->getPostbackData(),4) . 'さんで間違いありませんか？',
          'あなたは'. substr($event->getPostbackData(),4) . 'さんで間違いありませんか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい','cmd_OK'),
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('いいえ','cmd_cancel'));
        }
        break;
    }
    
    
  }else if($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage){

    // ユーザーIDをコンソールに表示
    error_log("\nuserID : " . $userId);
    
    
    // ユーザーIDが登録されてなければ登録する
    if(is_registeredUserId($userId)){
      error_log("\n Your userID is already registerd.");
    }else{
      registerUserId($userId);
      error_log("\n Your userID is registerd in database now.");
    }
    
    
    // userid登録フェーズ
    // TABLE_TO_IDENTIFYのready_to_idnetifyがtureの時のみ通過
    if(getReady2Identify($userId)){
      // ユーザーから送られてきたテキスト(名前)が、未登録者であるか
      $templatePostbackAction = unidentifiedWorkers($event->getText());
      if($templatePostbackAction){
        $alterText = 'LINEのアカウントに名前を登録します';
        $imageUrl = 'https://'.$_SERVER['HTTP_HOST'].'/img/identify.jpg';
        $title = 'ユーザー登録';
        $text = "あなたの名前を選んでください";
        $actionArray = array();
        replyButtonsTemplate($bot, $event->getReplyToken(),$alterText,$imageUrl,$title,$text,$templatePostbackAction);
         error_log("\nここ通りました0");
      }else{
        $bot->replyText($event->getReplyToken(), "あなたの名前で別の誰かが登録しているか、まだ聞いたことがありません。一度"
        . APP_MANAGER . "に問い合わせてみてください。");
        setReady2Identify($userId,'false');
      }
    }
    
  
    
    
    
    switch ($event->getText()) {
      
      // "登録"というテキストが来たら、userid登録フェーズに移る
      case '登録':
        // すでにuseridが登録されていたらはじく
        if(getIsIdentified($userId)){
          $bot->replyText($event->getReplyToken(), "あなたは既に名前が登録されています");
        }else{ 
          setReady2Identify($userId,'true');
          $bot->replyText($event->getReplyToken(), "あなたの名前を「フルネーム」で教えてください");
        }
        break;
        
      // ユーザーからのテキストによってシフト画像を送信する
      // 今日のパターン
      case "今日":
      case "きょう":
      case "本日":
      case "ほんじつ":
      case "today":
      case "Today":
      case "TODAY":
        // thedayには「20170620」みたいに格納される
        $theday = date('Ymd');
        $message = date('n月j日')."のシフトです";
        // ファイルのディレクトリを指定 
        $filename = 'https://'.$_SERVER['HTTP_HOST'].'/shiftpic/'.$theday.'.jpg'; 
        // httpヘッダーからOKが返ってくるか(ファイルがあるかどうか)を調べる
        if(strpos(array_shift(get_headers($filename)),'OK')){
          // とりあえずeventからuserIdとってきて無理やりpush通知
          $bot->pushMessage($userId, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
          // そのあとreplytoken使って画像を送信
          replyImageMessage($bot,$event->getReplyToken(),$filename,$filename);
        }else{
          // ファイルがない場合はその旨のメッセージを送信する
          $bot->replyText($event->getReplyToken(),
          date('n月j日'). "のシフト画像が見つかりませんでした\nまだ登録されていないかもしれません");
        }
        break;
        
      // ユーザーからのテキストによってシフト画像を送信する
      // 明日のパターン
      case "明日":
      case "あした":
      case "あす":
      case "tomorrow":
      case "Tomorrow":
      case "TOMORROW":
        $theday = date('Ymd',strtotime('+1 day'));
        $message = date('n月j日',strtotime('+1 day'))."のシフトです";
        $filename = 'https://'.$_SERVER['HTTP_HOST'].'/shiftpic/'.$theday.'.jpg';
        // httpヘッダーからOKが返ってくるか(ファイルがあるかどうか)を調べる
        if(strpos(array_shift(get_headers($filename)),'OK')){
          // とりあえずeventからuserIdとってきて無理やりpush通知
          $bot->pushMessage($userId, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
          // そのあとreplytoken使って画像を送信
          replyImageMessage($bot,$event->getReplyToken(),$filename,$filename);
        }else{
          // ファイルがない場合はその旨のメッセージを送信する
          $bot->replyText($event->getReplyToken(),
          date('n月j日',strtotime('+1 day')). "のシフト画像が見つかりませんでした\nまだ登録されていないかもしれません");
        }
        break;
      
      // どれでもない場合は、時間帯によって挨拶しとく
      default:
        if(date('G') > 6 && date('G') < 12){
          $bot->replyText($event->getReplyToken(),"おはようございます");
        }else if(date('G') >= 12 && date('G') < 18){
          $bot->replyText($event->getReplyToken(),"こんにちは");
        }else if(date('G') >= 2 && date('G') < 6){
          $bot->replyText($event->getReplyToken(),"はよ寝なさい");
        }else{
          $bot->replyText($event->getReplyToken(),"こんばんは");
        }
        break;
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

/*
// buttons テンプレート アクション引数が可変長版 関数がオーバーロードできればこんなコメントアウトいらないのに...
// Buttons テンプレートを返信 
// 引数(LINEBot,返信先,代替テキスト,画像URL,タイトル,本文,アクション(可変長引数))
function replyButtonsTemlate($bot,$replyToken,$alterText,$imageUrl,$title,$text, ...$actions){
  // アクションを格納する配列
  $actionArray = array();
  // アクションをすべて追加
  foreach($action as $value){
    array_push($actionArray , $value);
  }
  // TemplateMessageBuilderの引数(代替テキスト,ButtonTemplateBuilder)
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alterText,
  // ButtonTemplateBuilderの引数(タイトル,本文,画像URL,アクション配列)
  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder(
    $title,$text,$imageUrl,$actionArray));
    
  $response = $bot -> replyMessage($replyToken,$builder);
  if(!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}
*/

// buttons テンプレート アクション引数が配列版
// Buttons テンプレートを返信 
// 引数(LINEBot,返信先,代替テキスト,画像URL,タイトル,本文,アクション配列)
function replyButtonsTemplate($bot,$replyToken,$alterText,$imageUrl,$title,$text,$actionArray){

  // TemplateMessageBuilderの引数(代替テキスト,ButtonTemplateBuilder)
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alterText,
  // ButtonTemplateBuilderの引数(タイトル,本文,画像URL,アクション配列)
  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder(
    $title,$text,$imageUrl,$actionArray));
    
  $response = $bot -> replyMessage($replyToken,$builder);
  if(!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Confirm テンプレートを返信 
// 引数(LINEBot,返信先,代替テキスト,本文,可変長アクション配列)
function replyConfirmTemplate($bot,$replyToken,$alterText,$text,...$actions){
  error_log("\nここ通りました4");
  $actionArray = array();
  foreach($actions as $value){
    array_push($actionArray,$value);
  }
  // TemplateMessageBuilderの引数(代替テキスト,ButtonTemplateBuilder)
  error_log("\nここ通りました5");
  error_log("\naltertext :".$alterText);
  error_log("\ntext :".$text);
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alterText,
  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder(
    $text,$actionArray));
  error_log("\nここ通りました6");
  $response = $bot -> replyMessage($replyToken,$builder);
  if(!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    error_log("\nここ通りました7");
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
      $dsn = sprintf('pgsql:host=%s;dbname=%s',$url['host'],substr($url['path'],1));
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
  $sql = 'insert into ' . TABLE_TO_IDENTIFY . ' (userid) values 
  (pgp_sym_encrypt(?,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
}


// TABLE_TO_IDENTIFYにユーザーidがあるか調べる すでにある場合true ない場合はfalseを返す
function is_registeredUserId($userId){
  $dbh = dbConnection::getConnection();
  // pgp_sym_decryptは複合化
  $sql = 'select userid from ' . TABLE_TO_IDENTIFY . ' where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
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

// WORKERS_INFOにuseridを登録する
function setUserId($userId,$name){
  $dbh = dbConnection::getConnection();
  $sql = 'update tbl_workers_info 
  set userid = pgp_sym_encrypt(?,\'' . getenv('DB_ENCRYPT_PASS') . '\') where name = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId,$name));
}
  
// TABLE_TO_IDENTIFYに名前を登録する
function setUserName($userId,$name){
  error_log("\nname :" .$name);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_TO_IDENTIFY .' set name = \''.$name.'\'  where userid = 
  (pgp_sym_decrypt(?,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
}

// TABLE_TO_IDENTIFYの名前を返す
function getUserName($userId){
  $dbh = dbConnection::getConnection();
  $sql = 'select name from ' . TABLE_TO_IDENTIFY . ' where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  $userName = array_column($sth->fetchAll(),'name');
  return $userName[0];
}

// TABLE_TO_IDENTIFYのis_identifiedの(true/false)をスイッチする
function setIsIdentified($userId,$bool){
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_TO_IDENTIFY . ' set is_identified = ? where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($bool,$userId));
}

// TABLE_TO_IDENTIFYのis_identifiedの(true/false)を返す
function getIsIdentified($userId){
  $dbh = dbConnection::getConnection();
  $sql = 'select is_identified from ' . TABLE_TO_IDENTIFY . ' where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  $identify = array_column($sth->fetchAll(),'is_identified');
  if($identify[0] == 1){
    return true;
  }else{
    return false;
  }
}

// TABLE_TO_IDENTIFYのready_to_identifyの(true/false)をスイッチする
function setReady2Identify($userId,$bool){
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_TO_IDENTIFY . ' set ready_to_identify = ? where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($bool,$userId));
}


// TABLE_TO_IDENTIFYのready_to_identify(true/false)を返す
function getReady2Identify($userId){
  $dbh = dbConnection::getConnection();
  $sql = 'select ready_to_identify from ' . TABLE_TO_IDENTIFY . ' where ? =
  (pgp_sym_decrypt(userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') )' ;
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));

  $ready = array_column($sth->fetchAll(),'ready_to_identify');
  //error_log("\nready : " . print_r($ready,true));
  if($ready[0] == 1){
    error_log("\nready is true " );
    return true;
  }else{
    error_log("\nready is false" );
    return false;
  }
  
  /*
  error_log("\ntmp : " . print_r($tmp,true));
  $ready = array_column($sth->fetch(),'ready_to_identify');
  error_log("\nready : " . print_r($ready,true));
  if($ready[0]){
    error_log("\nready is true" );
  }else if(!$ready[0]){
    error_log("\nready is false " );
  }else{
    error_log("\nready isnt true and false " );
  }
  */
}





// ########### LineSDK 側のエラーで、リッチテキストのボタンは4個までしか作れないとのことだった　###########
// ########### そのためこの関数はいったん開発中ということで置いておく マジアリエン　###########

// TABLE_TO_IDENTIFYに登録されているuserIDで名前が未登録の人の名前を
// WORKERS_INFOからとってきてPostbackTemplateActionBuilerの配列を返す
function unidentifiedWorkers($name){
  $dbh = dbConnection::getConnection();
  // サブクエリでNOT INを使って TABLE_TO_IDENTIFY から名前をとってくる
  $sql = 'select name from ' . WORKERS_INFO . ' where 
  name NOT IN (select name from ' . TABLE_TO_IDENTIFY .' where is_identified = true)';
  $sth = $dbh->query($sql);
  $nameArray = array_column($sth->fetchAll(),'name');
  
  $actionArray = array();
  
  foreach($nameArray as $value){
    if($value == $name){
      $actionArray[] = new 
      LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder($value,'cmd_'. $value);
    }
  }
  if($actionArray){
    // actionArrayの最後にキャンセルのボタンも追加する
    $actionArray[] = new 
        LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('キャンセル','cmd_cancel');
    error_log("\nactionArray : " . print_r($actionArray,true));
    return $actionArray;
  }else{
    error_log("\nactionArray : " . print_r($actionArray,true));
    return $actionArray;
  }
}

/*
// TABLE_TO_IDENTIFYに登録されているuserIDで名前が未登録の人の名前を配列でエラーログに出す
// デバッグ用
function testgetarray(){
  $dbh = dbConnection::getConnection();
  $sql = 'select name from ' . WORKERS_INFO . ' where 
  name NOT IN (select name from ' . TABLE_TO_IDENTIFY .' where is_identified = true)';
  $res = $dbh->query($sql);
  $nameArray = array_column($res->fetchAll(),'name');
  error_log("\nnameArray : " . print_r($nameArray,true));
}
*/

 ?>