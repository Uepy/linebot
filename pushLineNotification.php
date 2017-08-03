<?php

require_once __DIR__ . '/vendor/autoload.php';


// 従業員情報のテーブル名を定義
define('WORKERS_INFO','tbl_workers_info');

//アクセストークンでCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClient とシークレットを使いLineBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient,['channelSecret' => getenv('CHANNEL_SECRET')]);

// 明日のシフトユーザーにをプッシュ通知する
pushShift($bot,date('Ymd',strtotime('+1 day')));

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

// WORKERS_INFOに登録されているユーザーidにシフト情報をpush通知する
function pushShift($bot,$date){
  $dbh = dbConnection::getConnection();
  // 
  $sql = 'select pgp_sym_decrypt(x.userid,\'' . getenv('DB_ENCRYPT_PASS') . '\') , x.name , y.shift_in , y.shift_out from '. WORKERS_INFO .' as x 
  join tbl_'.$date. ' as y using(id);';
  $sth = $dbh->query($sql);
  $shiftDataArray = $sth->fetchAll();
  error_log("\n shiftDataArray : " . print_r($shiftDataArray,true));
  foreach($shiftDataArray as $value){
    $response = $bot->pushMessage($value[0], 
    new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(
        $value[1]."さん！\n明日、".date('n月j日',strtotime('+1 day')) . "はシフトインしております！\n出勤時刻は" .substr($value[2],0,5). "\n退勤予定時刻は".substr($value[3],0,5)."です。\nよろしくお願いします！"));
    if (!$response->isSucceeded()){
    error_log('failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
  }
}

 ?>