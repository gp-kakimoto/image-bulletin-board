

<?php
define('THUMBNAIL_WIDTH', 400);
define('IMAGES_DIR', __DIR__ . '/images/');
define('THUMNAILS_DIR', __DIR__ . '/thumbs/');
define('THUMNAILS_PATH', '/image-bulletin-board/thumbs/');
define('IMAGES_PATH', '/image-bulletin-board/images/');
define('MAX_FILE_SIZE',  15000000);
ini_set('display_errors', 1);
//管理ページのログインパスワード
//define('PASSWORD','$2y$10$GCjAEfNUQOnjOZfe8HGk6OrdR0ESGrfpGCGmdYlOshs9UlPfN6Z6y');
define('PASSWORD',apache_getenv('AD_PASSWORD'));

//データベースの接続情報
define('DB_HOST',apache_getenv('DB_HOSTNAME'));
define('DB_USER',apache_getenv('DB_USERNAME'));
define('DB_PASS',apache_getenv('DB_PASSWORD'));
define('DB_NAME',apache_getenv('DB_NAMED'));

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');
$message = array();
//messageがsqlのデータでmessage_arrayでそれを配列として持つ
$message_array = array();
$error_message = array();
$current_date = null;

//データベースへのアクセスに使う変数たち
$pdo = null;
$stmt = null;
$res = null;
$option = null;



function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }


session_start();

if( !empty($_GET['btn_logout'])){
    unset($_SESSION['admin_login']);
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

    var_dump("接続エラー\n".$error_message);
}

if (!empty($_POST['btn_submit'])){
    if(!empty($_POST['admin_password']) && ($_POST['admin_password'] == PASSWORD)){
        $_SESSION['admin_login'] = true;
    } else {
        $error_message[] = 'ログインに失敗しました。';
    }
}

if( !empty($_POST['submit'])){
    $view_name = preg_replace('/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['view_name']);
    $message = preg_replace( '/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['message']);
    if(empty($view_name)){
        $error_message[] = '表示名を入力して下さい';
    } else{
        $_SESSION['view_name'] = $view_name;
    }
      // メセージの入力チェック
      if( empty($message)){
        $error_message[] = 'ひと言メッセージを入力してください。';
    } else {
        //文字数を確認
        if(100 < mb_strlen($message, 'UTF-8')){
            $error_message[] = 'ひと言メッセージは100文字以内で入力してください。';
        }
        
    }

    if(empty($error_message)){
        //画像のアップロードの処理を調べて書く 名前とメッセージが空でないとき画像のアップロードを受け付ける？
        
        echo "<p>empty error_message</p>";
        var_dump($_FILES);
        $current_date = date("Y-m-d H:i:s");
        var_dump($current_date);
        //トランザクション開始
        $pdo->beginTransaction();
        try{
            //SQL作成
            $stmt = $pdo->prepare("INSERT INTO message (view_name, message, post_date, image)
            VALUES ( :view_name, :message, :current_date, :image)");

            //値をセット
            $stmt->bindParam( ':view_name', $view_name, PDO::PARAM_STR);
            $stmt->bindParam( ':message', $message, PDO::PARAM_STR);
            $stmt->bindParam( ':current_date', $current_date, PDO::PARAM_STR);
            $stmt->bindParam( ':image', $_FILES['image']['name'], PDO::PARAM_STR);
            // SQLクエリの実行
            $stmt->execute();
            //コミット
            $res = $pdo->commit();
        }catch (Exception $e){
            //エラーが発生した時はロールバック
            $pdo->rollBack();
        }

        if( $res ){
            //$success_message = 'メッセージを書き込みました。';  
            $_SESSION['success_message'] = 'メッセージを書き込みました。';          
        } else {
            $error_message[] = '書き込みに失敗しました。';
        }

      
        $stmt = null;

        header('Location: ./');
    }
}    


//$_POST['submit']が空でなおかつ$error_messageが空のときに実行されるはず
if( empty($error_message) ) {

    // メッセージのデータを取得する
    $sql = "SELECT id,view_name,message,post_date,image FROM message ORDER BY post_date DESC";

//SQLに変数を利用していないので、pdo->query で実行している
    $message_array = $pdo->query($sql);
}
    //データベース接続を閉じる
    $pdo = null;

?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="./reset.min.css" rel="stylesheet">
        <link href="./style.css" rel="stylesheet">
        <title>Admin</title>
    </head>
    <body class="admin_body">
        <?php if( !empty($_SESSION['admin_login']) && $_SESSION['admin_login'] === true ){ ?>
            <header>
                <img src="./img/logo.JPG">
                <h1>ひと言掲示板管理ページ</h1>
                <form method="get" action="">
                    <input class="logout_button" type="submit" name="btn_logout" value="ログアウト">
                </form>
            </header>
            <hr class="hr">
            <button class="home_button" onclick="location.href='./index.php'">HOME</button>
            <button class="admin_button" onclick="location.href='./admin.php'">ADMIN</button>
            <hr class="hr">
            <section>
                <form method="get" action="./download.php">
                    <select name="limit" class="select_limit">
                        <option value="">全て</option>
                        <option value="10">10件</option>
                        <option value="30">30件</option>
                    </select>
                    <input type="submit" class="btn_download" name="btn_download" value="ダウンロード">
                </form>
 
        <!-- ここに投稿されたメッセージを表示 -->
            <?php
                //message_arrayが空(null)でないとき以下のコードが実行される
            if(!empty($message_array)){
                //message_arrayの各要素に対して、以下のコードを実行する
                foreach($message_array as $value){?>

                    <article>
                        <div class="information">
                            <h2><?php echo h($value['view_name']); ?></h2>
                            <time><?php echo date('Y年m月d日H:i',strtotime($value['post_date'])); ?></time>
                            <p>
                                <a href="/image-bulletin-board/edit.php?message_id=<?php echo $value['id'];?>">編集</a>
                                <a href="/image-bulletin-board/delete.php?message_id=<?php echo $value['id']; ?>">削除</a>
                            </p> 
                        </div>
                        <div class="message_wrapper">
                            <p class="message"><?php echo nl2br(h($value['message'])); ?></p>
                            <div class="thumbnail_viewer">
                                <a href="<?php echo IMAGES_PATH.$value['post_date'].'_'.$value['image']; ?>" target="_blank" rel="noopener noreferrer"><img src="<?php if($value['image']!=null){/* $path_parts = $_SERVER["REQUEST_URI"];*/ echo h(THUMNAILS_PATH.$value['post_date'].'_'.$value['image']);}  ?>"></a>
                            </div>
                        </div>
                    </article>
                <?php
                }
            }
        }
    else{ ?>
        <header>
            <img src="./img/logo.JPG">
            <h1>管理者ページ</h1>
            <button class="home_button" onclick="location.href='./index.php'">HOME</button>
            <button class="admin_button" onclick="location.href='./admin.php'">ADMIN</button>
        </header>
        <section>
            <form method="post" class="login_form">
                <div>
                <label for="admin_password">ログインパスワード</label>
                <input id="admin_password" type="password" name="admin_password" value="">
            </div>
            <input type="submit" name="btn_submit" value="ログイン">    
        </form>
        <?php }?>
        </section>
    </body>
</html>