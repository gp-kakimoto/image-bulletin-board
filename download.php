<?php
//定数の設定
//define ('FILENAME','./message.txt');
//管理ページのログインパスワード
//define('PASSWORD','adminPassword');
//データベースの接続情報
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','board');

//変数の初期化
$csv_data = null;
$sql = null;
$pdo = null;
$option = null;
$message_array = array();
$limit = null; //csvデータの取得件数を保持するための変数
$stmt = null;

session_start();

if( !empty($_GET['limit'])){
    if($_GET['limit'] === "10"){
        $limit = 10;
    } elseif( $_GET['limit'] === "30"){
        $limit = 30;
    }
}
// 取得件数

if( !empty($_SESSION['admin_login']) && $_SESSION['admin_login'] === true ) {
    // データベースに接続
    try {
        $option = array(
            PDO::ATTR_ERRMODE=> PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false
        );
        $pdo = new PDO('mysql:charset=UTF8;dbname='.DB_NAME.';host='.DB_HOST ,DB_USER, DB_PASS, $option);

        // メッセージのデータを取得する
        if ( !empty($limit)){
            // SQL作成
            $stmt = $pdo->prepare("SELECT * FROM message ORDER BY post_date ASC LIMIT :limit");
			// 値をセット
			$stmt->bindValue( ':limit', $_GET['limit'], PDO::PARAM_INT);
        } 
        else {
            //post_dateで昇順に並び替えて、messageテーブルの一覧を取得する
            //$sql = "SELECT * FROM message ORDER BY post_date ASC";
            $stmt = $pdo->prepare("SELECT * FROM message ORDER BY post_date ASC");
        }

        //SQLクエリの実行
        $stmt->execute();
        $message_array = $stmt->fetchAll();

        //$message_array = $pdo->query($sql);
        //データベースの接続を閉じる
        $stmt = null;
        $pdo = null;
    } catch(PDOException $e){
        //管理者ページへリダイレクト
        header("Location: ./admin.php");
        exit;
    }
	// ここにファイル作成＆出力する処理が入る
    //出力の設定
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=メッセージデータ.csv");
    header("Content-Transfer-Encoding: binary");

    //csvデータを作成
    if( !empty($message_array)){
        //1行目のラベル作成 改行のコードのところだけ、ダブルクオーテーションで囲む
        $csv_data .= '"ID","表示名","メッセージ","投稿日時","画像情報"'."\n";
        //ここも、改行のところだけ、ダブルクオーテーションで囲んでる, .= は文字列の連結　 hoge .= llll は hoge = hoge.llllになるず
        foreach( $message_array as $value) {  
            $csv_data .= '"' . $value['id'] . '","' . $value['view_name'] . '","' . $value['message'] . '","' . $value['post_date'] . '","' . $value['image'] . "\"\n";
        }
    }

    //ファイルを出力
    echo $csv_data;
} else {

	// ログインページへリダイレクト
	header("Location: ./admin.php");
	exit;
}

return;

