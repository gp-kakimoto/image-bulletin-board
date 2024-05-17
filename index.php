<?php
define('THUMBNAIL_WIDTH', 400);
define('IMAGES_DIR', __DIR__ . '/images/');
define('THUMSNAIL_DIR', __DIR__ . '/thumbs/');
define('THUMSNAIL_PATH', '/image-bulletin-board/thumbs/');
define('IMAGES_PATH', '/image-bulletin-board/images/');
define('MAX_FILE_SIZE',  15000000);
ini_set('display_errors', 1);

//データベースの接続情報
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','board');


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

function makeThumb ($originalFile, $thumbSize)
{

    var_dump($originalFile.'!!!!!!');
    // 画像の横幅・高さ取得
    list($originalWidth, $originalHeight) = getimagesize($originalFile);

    // サムネイルの横幅指定
    $thumbWidth = $thumbSize;
    // サムネイルの高さ算出
    $thumbHeight = round($originalHeight * $thumbWidth / $originalWidth );

    $fileType = substr($originalFile, strrpos($originalFile, '.') + 1);

    // ファイルタイプ別に作成
    if ($fileType === "jpg" || $fileType === "jpeg"||$fileType== "JPG" || $fileType == "JPEG") {

        $originalImage = imagecreatefromjpeg($originalFile);
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

    } elseif ($fileType === "png" || $fileType == "PNG") {

        $originalImage = imagecreatefrompng($originalFile);
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // ※アルファチャンネル保存
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);

    } elseif ($fileType === "gif" || $fileType == "GIF") {

        $originalImage = imagecreatefromgif($originalFile);
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // 透明色を定義する
        $transparent = imagecolortransparent($originalImage);
        imagefill($thumbImage, 0, 0, $transparent);
        imagecolortransparent($thumbImage, $transparent);

    } else { 

        return '画像形式が正しくありません'; 

    }

    // 
    imagecopyresampled($thumbImage, $originalImage, 0, 0, 0, 0,
            $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);

    // テンポラリファイルに画像出力
   // $tmpFile = $_FILES['image']['tmp_name'];
    $originalFile_basename = pathinfo($originalFile,PATHINFO_BASENAME);
    if ($fileType === "jpg" || $fileType === "jpeg"||$fileType=="JPG"||$fileType=="JPEG"){
     //   imagejpeg($thumbImage, $tmpFile);
    // $originalFile_basename = pathinfo($originalFile,PATHINFO_BASENAME);
     var_dump('---------------');
     imagejpeg($thumbImage,THUMSNAIL_DIR.$originalFile_basename);
    } elseif ($fileType === "png"||$fileType == "PNG") {
       // imagepng($thumbImage, $tmpFile);
       imagepng($thumbImage,THUMSNAIL_DIR.$originalFile_basename);
    } elseif ($fileType === "gif" || $fileType == "GIF") {
       // imagegif($thumbImage, $tmpFile);
       imagegif($thumbImage,THUMSNAIL_DIR.$originalFile_basename);
    } 

    // 画像破棄
    imagedestroy($originalImage);
    imagedestroy($thumbImage);
}



function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }


session_start();

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

    var_dump("接続エラー\n".$error_message);
}

if( !empty($_POST['submit'])){
    //$current_date = date("Y-m-d H:i:s");
    var_dump($_POST['view_name']);
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
            //var_dump($pdo);
        }

        if( $res ){
            //$success_message = 'メッセージを書き込みました。';  
            $_SESSION['success_message'] = 'メッセージを書き込みました。';          
        } else {
            $error_message[] = '書き込みに失敗しました。';
        }

      
        //var_dump($_FILES);
        //var_dump($_POST);
        if($_FILES['image']['name'] != NULL){
            $save_image_path = IMAGES_DIR.$current_date.'_'.$_FILES['image']['name'];
            var_dump($save_image_path);
            move_uploaded_file($_FILES['image']['tmp_name'], $save_image_path);
        
            makeThumb($save_image_path,THUMBNAIL_WIDTH);
        }

        $stmt = null;

        header('Location: ./');
    }
}    


//$_POST['submit']が空でなおかつ$error_messageが空のときに実行されるはず
if( empty($error_message) ) {

    // メッセージのデータを取得する
    $sql = "SELECT view_name,message,post_date,image FROM message ORDER BY post_date DESC";

//SQLに変数を利用していないので、pdo->query で実行している
    $message_array = $pdo->query($sql);
   // var_dump($message_array);
} else{
   // header('Location: ./');
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
    <title>Document</title>
</head>
<body>
    <header>
        <img src="./img/logo.JPG">
        <h1>ひと言掲示板</h1>
    </header>

<?php if(!empty($error_message)): ?>
    <ul class="error_message">
        <?php foreach($error_message as $value):?>
            <li><?php echo $value; ?></li>
            <?php endforeach; ?>
    </ul>
 <?php endif; ?>

    <div class=wrapper>
        <div class=inner_wrapper>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="view_name_wrapper">
                    <label for="view_name">表示名</label><br>
                    <input id="view_name" type="text" name="view_name" value= "<?php if( !empty($_SESSION['view_name']) ){ echo h($_SESSION['view_name']);} ?>">
                </div>
                <div class="one_phrase_wrapper">
                    <label for="message">ひと言メッセージ</label><br>
                    <textarea id="message" name="message"><?php if( !empty($message)){ echo h($message);} ?></textarea>
                </div>
                <div>
                    <input type="hidden" name="MAX_FILE_SIZE" value="">
                    <input type="file" id ="image" name="image"><br>
                    <input type="submit" name="submit" value="書き込む">
                </div>
            </form> 
        </div>
    </div>
    <hr class="hr">
    <section>
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
                    </div>
                    <div class="message_wrapper">
                        <p class="message"><?php echo nl2br(h($value['message'])); ?></p>
                        <div class="thumbnail_viewer">
                            <a href="<?php echo IMAGES_PATH.$value['post_date'].'_'.$value['image']; ?>" target="_blank" rel="noopener noreferrer"><img src="<?php if($value['image']!=null){/* $path_parts = $_SERVER["REQUEST_URI"];*/ echo h(THUMSNAIL_PATH.$value['post_date'].'_'.$value['image']);}  ?>"></a>
                        </div>
                    <?php //var_dump(THUMSNAIL_DIR.$value['post_date'].'_'.$value['image']); 
                    ?> 
                    </div>
                </article>
                <?php
            }
        }
    ?>
</section>

</body>
</html>