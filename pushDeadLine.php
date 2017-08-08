<?php

require_once __DIR__ . '/vendor/autoload.php';

//アクセストークンでCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClient とシークレットを使いLineBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient,['channelSecret' => getenv('CHANNEL_SECRET')]);

/*
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
*/

if(date("d") == 7 || date("d") ==22){
    pushDeadLine($bot);
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

// プッシュ通知でシフト提出期限を知らせる
function pushDeadLine($bot){
    
    $dbh = dbConnection::getConnection();
    $sql = 'select pgp_sym_decrypt(x.userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') from tbl_workers_info where not userid is null';
    $sth = $dbh->query($sql);
    $userIdArray = $sth->fetchAll();
    error_log("\n userIdArray : " . print_r($userIdArray,true));
    foreach($userIdArray as $value){
        $response = $bot->pushMessage($value[0], 
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(
            "本日はシフト提出期限です\nシフトは提出しましたか？"));
        if (!$response->isSucceeded()){
        error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
        }
    }
}
 ?>