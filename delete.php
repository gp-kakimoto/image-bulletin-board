<?php
//定数の設定
//データベースの接続情報
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS',apache_getenv('DB_PASSWORD'));
define('DB_NAME','board');

//画像を保存しているフォルダのパスを保持する定数
define('THUMNAILS_PATH', '/image-bulletin-board/thumbs/');
define('IMAGES_PATH', '/image-bulletin-board/images/');

define('THUMNAILS', './thumbs/');
define('IMAGES', './images/');
// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

//変数の初期化
$view_name = null;
$message = array();
$message_data = null;
//配列messageを要素として取り扱うための配列(二次元配列のイメージ)
$message_array = array();

//$success_message = null;
$error_message = array();

//データベースへのアクセスに使う変数たち
$pdo = null;
$stmt = null;
$res = null;
$option = null;


function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }

session_start();

//　管理者としてログインしているか確認
if( empty($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true ){

    // ログインページへリダイレクト
    header("Location: ./admin.php");
    exit;
}
// データベースに接続
try{
    $option = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    );
    $pdo = new PDO('mysql:charset=UTF8;dbname='.DB_NAME.';host='.DB_HOST , DB_USER, DB_PASS, $option);

} catch(PDOException $e){
    //接続エラーのときエラー内容を取得する
    $error_message[] = $e->getMessage();
}

if( !empty($_GET['message_id']) && empty($_POST['message_id'])){
    //投稿を取得するコードが入る
    //SQL作成
    $stmt = $pdo->prepare("SELECT * FROM message WHERE id = :id");

    //:idの値をセット
    $stmt->bindValue(':id', $_GET['message_id'],PDO::PARAM_INT);

    // SQLクエリの実行
    $stmt->execute();

    // 表示するデータを取得
    $message_data = $stmt->fetch();

    // 投稿データが取得できなときは管理ページに戻る
    if(empty($message_data)){
        header("Location: ./admin.php");
        exit;
    }

} elseif(!empty($_POST['btn_cancel'])){
    header("Location: ./admin.php");
    exit;

} elseif(!empty($_POST['message_id'])){
    //トランザクション開始
    $pdo->beginTransaction();
    try{
        //SQL作成
        $stmt =$pdo->prepare("DELETE FROM message WHERE id = :id");

        //値をセット
        $stmt->bindValue( ':id', $_POST['message_id'], PDO::PARAM_INT);

        //SQLクエリの実行
        $stmt->execute();

        //コミット
        $res = $pdo->commit();

    } catch(Exception $e){
        // エラーが発生した時はロールバック
        $pdo->rollBack();
    }
    //画像あれば削除する
    if($_POST['image_data_for_delete'] != null){
        if(is_writable(THUMNAILS.$_POST['image_data_for_delete'])){
            echo "We can delete this file.";
        }
       unlink(THUMNAILS.$_POST['image_data_for_delete']);
       unlink(IMAGES.$_POST['image_data_for_delete']);
    }

    //削除に成功したら一覧に戻る
    if( $res ){
       header("Location: ./admin.php");
        exit;
    }
}
//データベース接続を閉じる
$stmt = null;
$pdo = null;
?> 

<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>ひと言掲示板 管理ページ (投稿の削除)</title>
        <link href="./reset.min.css" rel="stylesheet">
        <link href="./style.css" rel="stylesheet">
        <title>削除</title>
    </head>
    <body>
        <h1>ひと言掲示板 管理ページ (投稿の削除)</h1>
        <!-- 書き込み成功のメッセージを表示する -->

        <!---- error_messageが空でなければ以下の処理が実行される------->
        <?php if(!empty($error_message)): ?>
            <ul class="error_message">
                <?php foreach($error_message as $value):?>
                    <li><?php echo $value; ?></li>
             <?php endforeach; ?>
            </ul>
        <?php endif; ?>
<!-- ここにメッセージの入力フォームを設置 -->
        <header>
            <img src="./img/logo.JPG">
            <h1>ひと言掲示板管理ページ</h1>
            <button class="home_button" onclick="location.href='./index.php'">HOME</button>
            <button class="admin_button" onclick="location.href='./admin.php'">ADMIN</button>  
        </header>
        <hr class="hr">
        <p class="text-confirm">以下の投稿を削除します。</p>
        <section>
            <form method="post">
                <div class="wrapper">
                    <div class ="inner_wrapper">
                        <div class="information information_delete">
                            <label for="view_name">表示名</label>
                            <!-- $_SESSION['view_name'] が空でなければ、サニタイズし、echo文により出力する--->
                            <input id="view_name" type="text" name="view_name" value="<?php if( !empty($message_data['view_name']) ){ echo $message_data['view_name']; } elseif( !empty($view_name)){ echo h($view_name);} ?>" disabled>
                        </div>
                        <div class="message_wrapper">
                            <label for="message">ひと言メッセージ</label>
                            <textarea id="message" name="message" disabled><?php if( !empty($message_data['message']) ){ echo $message_data['message']; } elseif( !empty($message)){ echo h($message);} ?></textarea>
                            <div class="thumbnail_viewer">
                                <a href="<?php echo IMAGES_PATH.$message_data['post_date'].'_'.$message_data['image']; ?>" target="_blank" rel="noopener noreferrer"><img src="<?php if($message_data['image']!=null){/* $path_parts = $_SERVER["REQUEST_URI"];*/ echo h(THUMNAILS_PATH.$message_data['post_date'].'_'.$message_data['image']);}  ?>"></a>
                            </div>
                        </div>
                    </div>
                    <input type="submit" name="btn_cancel" value="キャンセル">
                    <input type="submit" name="btn_submit" value="削除">
                    <input type="hidden" name="message_id" value="<?php if(!empty($message_data['id']) ){ echo $message_data['id']; } elseif( !empty($_POST['message_id'])){echo h($_POST['message_id']);} ?>">
                    <input type="hidden" name="image_data_for_delete" value="<?php echo $message_data['post_date'].'_'.$message_data['image']; ?>">
                </div>
            </form>
        </section>
    </body>
</html>