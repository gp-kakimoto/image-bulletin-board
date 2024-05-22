<?php
//定数の設定

//データベースの接続情報
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS',apache_getenv('DB_PASSWORD'));
define('DB_NAME','board');


//画像フォルダのパスを設定
define('THUMNAILS_PATH', '/image-bulletin-board/thumbs/');
define('IMAGES_PATH', '/image-bulletin-board/images/');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

//変数の初期化
$view_name = null;
$message = array();
$message_data = null;
//配列messageを要素として取り扱うための配列(二次元配列のイメージ)
$message_array = array();
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
    //$pdo = new PDO('mysql:charset=UTF8;dbname=board;host=localhost', 'root', '',$option);
    $pdo = new PDO('mysql:charset=UTF8;dbname='.DB_NAME.';host='.DB_HOST , DB_USER, DB_PASS, $option);

} catch(PDOException $e){
    //接続エラーのときエラー内容を取得する
    $error_message[] = $e->getMessage();
}

if( (!empty($_GET['message_id']) && empty($_POST['message_id']))){
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

} elseif( !empty($_POST['message_id']) && empty($_POST['btn_cancel'])){
    // 空白除去
    //文章の先頭にある一文字以上の空白を除去し、文章の末尾にある一文字以上の空白等の連なりを除去する
    $view_name = preg_replace('/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['view_name']);
    $message = preg_replace( '/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['message']);

    // 表示名の入力が空でないかをチェック
    if( empty($view_name)){
        $error_message[] = '表示名を入力して下さい';
    }
    // メッセージの入力チェック
    if( empty($message)){
        $error_message[] = 'メッセージを入力してください';
    } else {
        if( 100 < mb_strlen($message,'UTF-8')){
            $error_message[] = 'ひと言メッセージは100文字以内で入力してください。';
        }
    }

    if( empty($error_message)){
        // ここにデータベースに保存する処理する入る
        //トランザクション開始
        $pdo->beginTransaction();
        try{

            // SQL作成 
            $stmt = $pdo->prepare("UPDATE message SET view_name = :view_name, message= :message WHERE id = :id");

            // 値をセット
            $stmt->bindParam(':view_name', $view_name, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':id', $_POST['message_id'], PDO::PARAM_INT);

                // SQL クエリの実行
            $stmt->execute();

            // コミット

            $res = $pdo->commit();

        } catch (Exception $e){
            // エラーが発生した時はロールバック
            $pdo->rollBack();
        }

        // 更新に成功したら一覧に戻る
        if( $res ) {
            header("Location: ./admin.php");
            exit;
        }

    } else {
        $message_data['image']=$_POST['image_data_for_update']['image'];
        $message_data['post_date']=$_POST['image_data_for_update']['post_date'];
        var_dump($message_data['image']);
    }
}

if(!empty($_POST['btn_cancel'])){
    header("Location: ./admin.php");
    exit;

}
//データベース接続を閉じる
$pdo = null;

?> 

<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <title>ひと言掲示板 管理ページ (投稿の編集)</title>
        <link href="./reset.min.css" rel="stylesheet">
        <link href="./style.css" rel="stylesheet">
    </head>
    <body>
        <h1>ひと言掲示板 管理ページ (投稿の編集)</h1>
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

        <section>
            <form method="post">
                <div class="wrapper">
                    <div class ="inner_wrapper">
                        <div class="information information_delete">
                            <label for="view_name">表示名</label>
                            <!-- $_SESSION['view_name'] が空でなければ、サニタイズし、echo文により出力する--->
                            <input id="view_name" type="text" name="view_name" value="<?php if( !empty($message_data['view_name']) ){ echo $message_data['view_name']; } elseif( !empty($view_name)){ echo h($view_name);} ?>">
                        </div>
                        <div class="message_wrapper">
                            <label for="message">ひと言メッセージ</label>
                            <textarea id="message" name="message" maxlength="100"><?php if( !empty($message_data['message']) ){ echo $message_data['message']; } elseif( !empty($message)){ echo h($message);} ?></textarea>
                            <div class="thumbnail_viewer">
                                <a href="<?php echo IMAGES_PATH.$message_data['post_date'].'_'.$message_data['image']; ?>" target="_blank" rel="noopener noreferrer"><img src="<?php if($message_data['image']!=null){/* $path_parts = $_SERVER["REQUEST_URI"];*/ echo h(THUMNAILS_PATH.$message_data['post_date'].'_'.$message_data['image']);}  ?>"></a>
                            </div>
                         </div>
                    </div>
                    <input type="submit" name="btn_cancel" value="キャンセル">
                    <input type="submit" name="btn_submit" value="更新">
                    <input type="hidden" name="message_id" value="<?php if(!empty($message_data['id']) ){ echo $message_data['id']; } elseif( !empty($_POST['message_id'])){echo h($_POST['message_id']);} ?>">
                    <input type="hidden" name="image_data_for_update[post_date]" value="<?php echo $message_data['post_date'] ?>"> 
                    <input type="hidden" name="image_data_for_update[image]" value="<?php echo $message_data['image']; ?>"> 
                </div>
            </form>
        </section>

    </body>
</html>
