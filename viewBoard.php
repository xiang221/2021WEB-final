<?php
session_start();
include("pdoInc.php");

//-----刪除貼文-----//
if(isset($_GET['del'])){ 
    $sthDel = $dbh->prepare('SELECT account FROM my_thread WHERE id = ?');
    $sthDel->execute(array((int)$_GET['del']));
    $row = $sthDel->fetch(PDO::FETCH_ASSOC);
    if ($_SESSION['is_admin'] == 1 || $_SESSION['account'] == $row['account']){ //管理員or貼文擁有者有權刪除貼文

        $sth3 = $dbh->prepare('SELECT id FROM my_thread WHERE root_thread_id = ?'); //搜尋貼文下的留言id
        $sth3->execute(array( (int)$_GET['del'] ));
        while($row3 = $sth3->fetch(PDO::FETCH_ASSOC)){
            $sthDelDice = $dbh->prepare('DELETE FROM my_dice WHERE thread_id = ?'); //依留言id刪除骰子
            $sthDelDice->execute(array( (int)$row3['id'])); 
        }
        $sth = $dbh->prepare('DELETE FROM my_thread WHERE id = ? or root_thread_id = ?'); //刪除貼文與其留言
        $sth->execute(array((int)$_GET['del'], (int)$_GET['del']));
        
        $sthDelDice = $dbh->prepare('DELETE FROM my_dice WHERE thread_id = ?'); //刪除貼文的骰子
        $sthDelDice->execute(array( (int)$_GET['del']));
        echo '<meta http-equiv=REFRESH CONTENT=0;url='.basename($_SERVER['PHP_SELF']).'?id='.(int)$_GET['id'].'>';
    }

} 
   

//-----撰寫貼文-----//
$resultStr = " ";
$sthBoard = $dbh->prepare('SELECT id, name FROM my_board WHERE id = ?');

if(isset($_GET['id'])  && isset($_POST['title']) && isset($_POST['content'])){
    if(!isset($_SESSION['account'])){ //未登入不可撰寫新貼文
       $resultStr = "請先登入！";
    }
    else{
        if($_POST['title']!='' && $_POST['content']!='' && isset($_POST['min_point']) && gettype($_POST['min_point']) == 'array'){
            if($_POST['min_point'][0]>=0 && $_POST['min_point'][0]<=30 && $_POST['min_point'][0]%5==0){ //如果使用者設定的積分是0,5,10,15,...30
                //----取得使用者的積分----//
                $sthUserPoint = $dbh->prepare('SELECT point from user where account = ?');
                $sthUserPoint->execute(array($_SESSION['account']));
                $rowUserPoint = $sthUserPoint->fetch(PDO::FETCH_ASSOC);
                $userPoint = $rowUserPoint['point']; 

                if( $_POST['min_point'][0] <= $userPoint){ //如果使用者設定的文章積分<=自己的積分
                    $sthBoard->execute(array((int)$_GET['id']));
                    if($sthBoard->rowCount() == 1){

                        $sth = $dbh->prepare( //新增至資料表
                            'INSERT INTO my_thread (board_id, nickname, title, content, account, point) VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $sth->execute(array(
                            (int)$_GET['id'],
                            $_SESSION['nickname'],
                            $_POST['title'],
                            $_POST['content'],
                            $_SESSION['account'],
                            $_POST['min_point'][0]
                        ));

                        $lastId = $dbh->lastInsertId(); //取得最新加入的貼文的id
                        //------------ dice -------------//
                        $string_content = $_POST['content'];
                        $regex = "/\([\d\w\-]+\)/";

                        preg_match_all($regex, $string_content, $matches);
                        foreach ($matches[0] as $word) {
                            if($word == "(oj)"){ //type 1 == (oj) : Online Judge骰 -> AC/RE/WA
                                $rand_num = rand(0,2);
                                $dbh->exec(
                                    "INSERT INTO my_dice (type, thread_id, number) VALUES (1, '$lastId', '$rand_num')"
                                );
                            }else if($word == "(queen-rainbow)"){ //type 2 == (queen-rainbow) : 七彩女王骰
                                $rand_num = rand(0,5);
                                $dbh->exec(
                                    "INSERT INTO my_dice (type, thread_id, number) VALUES (2, '$lastId', '$rand_num')"
                                );
                            } else if($word == "(dice-six)"){ //type3 == (dice-six) ：六面骰
                                $rand_num = rand(0,5);
                                $dbh->exec(
                                    "INSERT INTO my_dice (type, thread_id, number) VALUES (3, '$lastId', '$rand_num')"
                                );
                            }
                        }
      
                        //------------ 發文新增點數 -------------//
                        $point = $rowUserPoint['point']+5; 
                        $sth2 = $dbh->prepare('UPDATE user SET point = ? WHERE account = ?');
                        $sth2->execute(array($point, $_SESSION['account']));
                        echo '<meta http-equiv=REFRESH CONTENT=0;url=viewBoard.php?id='.(int)$_GET['id'].'>';
                    }
                } else{
                    $resultStr = "文章積分不可高於自身的積分！";
                }
            }
        }
        else if($_POST['title']=='' && $_POST['content']!=''){
            $resultStr = "請輸入標題！";
        }
        else if($_POST['title']!='' && $_POST['content']==NULL){
            $resultStr = "請輸入內容！";
        }
        else{
            $resultStr = "請輸入標題及內容！";
        }
    }
}
?>
 
<html>
<head>
    <style>textarea{vertical-align:top}</style>
    <style>
        body{
    margin: 0 0 1em 0;
}
.container{
    font-size: 30px;
    background-color: #005CAF;
    margin-top: 0;
    height: 60px;   
}
.up-table{
    padding-top: 13px;
    margin-right: 30px;
    width: 100%;            
}
.up-table td{
    font-size: 20px;
}
.left-table td{
    padding-left:25px;
}
.right-table td{
    padding-right:25px;
}
.up-link{
    color:white;
    text-decoration:none;
}
.link{
    color: #113285;
    font-size: 23px;
    text-decoration:none;
}
.body-table{
    margin-top: 20px;
    margin-left: 40px;
}
.body-table td{
    padding-top: 10px; 
}
#name{
    padding-right:10px;
}
.login{
    color: #f5e8a2;
}
.text{
    color: #113285;
    font-size: 20px;
}
.container3{
    margin-top: 35px ;
}
font{
    font-family:Microsoft JhengHei;
}
a{
    font-family:Microsoft JhengHei;
}
.submit{
    position: relative;
    left:85px; 
}
.submit1{
    position: relative;
    top: 6px;
    left:197px; 
    margin-bottom: 10px;
}
.result{
    color:red;
    position: relative;
    right: 40px;
}
.result1{
    color:red;
    font-size: 23px;
}
.container4{
    margin-top: 20px ;
    margin-left: 35px;
}
.container5{
    margin-top: 30px ;
    margin-left: 35px;
    margin-right: 30px;
}
.zi_hr_01 {
    border-width: 0;
    height: 30px;
    background-image: url("./line2.png");
    background-repeat: repeat-x;
}
.hr_02{
    border-width: 0;
    border-bottom:1px #5f5f5f dashed;
}
.title-link{
    color: #6F3381;
    font-size: 20px;
    text-decoration:none;
    font-weight:bold;
}
.title-link:hover{
    text-decoration:underline;
}
.tex{
    font-size: 14px;
}
.text-big{
    font-size: 27px;
}
.upp-link{
            color:#f5e8a2;
            text-decoration:none;
        }
    </style>
     <script defer src="https://use.fontawesome.com/releases/v5.0.10/js/all.js" integrity="sha384-slN8GvtUJGnv6ca26v8EzVaR9DC58QEwsIk9q1QXdCU8Yu8ck/tL/5szYlBbqmS+" crossorigin="anonymous"></script>
</head>
<body bgcolor="#d4ebfa">
<div class="container" >
        <table class="up-table" border=0>
            <tr>
                <td>
                    <div align="left">
                        <table class="left-table" border=0>
                            <tr>
                                <?php
                                    echo "<td><a class=\"up-link\" href=\"./index.php\">返回看板列表</a></td>";

                                    if(isset($_SESSION['account']) && $_SESSION['account'] != null){ //如果登入，顯示暱稱等等    
                                        echo "<td class=\"login\"  ><a class=\"upp-link\" href=\"./admin.php\" id=\"name\"><font>Hi, ".$_SESSION['account']." (".htmlspecialchars($_SESSION['nickname']).")</font></a></td>"; 
                                        echo "<td style=\"color:#f7efc1; \"><i class=\"far fa-smile\"></i>";
                                        $sth = $dbh->prepare('SELECT point from user where account = ?');
                                        $sth->execute(array($_SESSION['account']));
                                        $row = $sth->fetch(PDO::FETCH_ASSOC);
                                        echo "&nbsp&nbsp&nbsp".$row['point']."</td>";
                                    } 
                                ?>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div align="right">
                        <table class="right-table" border=0>
                            <tr>
                                <?php
                                    if(isset($_SESSION['account']) && $_SESSION['account'] != null){ //如果登入，顯示修改資料、登出連結
                                        echo "<td><a class=\"up-link\" href=\"./edit_profile.php\">修改資料</a></td>";
                                        echo "<td><a class=\"up-link\" href=\"./logout.php\">登出</a></td>";
                                    }
                                    else{ //未登入，顯示註冊連結
                                        echo "<td><a class=\"up-link\" href=\"./register.php\">註冊</a></td> <td><a class=\"up-link\" href=\"./login.php\">登入</a></td>";
                                    }
                                ?>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>
 
<?php
if(isset($_GET['id'])){
    $sth = $dbh->prepare('SELECT id, name FROM my_board WHERE id = ?');
    $sthBoard->execute(array((int)$_GET['id']));
    if($sthBoard->rowCount() == 1){
        $row = $sthBoard->fetch(PDO::FETCH_ASSOC)
?>
    
    <div class="container4" style="text-align:left;">
    <?php echo "<font class=\"result1\">".$resultStr."</font>";?><br>
        <form action="viewBoard.php?id=<?php echo (int)$_GET['id'];?>" method="post">
            <font class="text">於<?php echo '<font class="text-big">'.$row['name'].'</font>';?>發表主題：</font><br>
            <font class="text">標題：</font><input name="title"><br>
            <font class="text">內容：</font><textarea name="content"></textarea><br>
            <font class="text">閱讀權限：</font><i  class="far fa-smile"></i>
            <select name="min_point[]">
                <option value="0" selected>0</option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="20">20</option>
                <option value="25">25</option>
                <option value="30">30</option>
            </select><br>

            <input class="submit1" type="submit"><br>
        </form>
    </div>
    <hr class="zi_hr_01" id="hr_01">
    
<div class="container5" style="text-align:left;">

<?php
        //----顯示貼文----//
        $sth = $dbh->prepare('SELECT * from my_thread WHERE board_id = ? ORDER BY id');
        $sth->execute(array((int)$_GET['id']));

        while($row = $sth->fetch(PDO::FETCH_ASSOC)){
            if(isset($_SESSION['account']) && $_SESSION['account'] != null){ //顯示刪除貼文按鈕
                if($_SESSION['is_admin']==1 || $row['account']==$_SESSION['account']){ 
                    echo '<a href="'.
                    basename($_SERVER['PHP_SELF']).'?id='.(int)$_GET['id'].'&del='.$row['id'].
                    '"> <i class="fas fa-trash-alt" style="color:#005CAF; cursor: hand;" ></i></a> &nbsp&nbsp';
                }
            }
            //----顯示點數----//
            echo '<i  class="far fa-smile"></i>&nbsp';
            $sth1 = $dbh->prepare('SELECT point from my_thread where id = ?');
            $sth1->execute(array((int)$row['id']));
            $row1 = $sth1->fetch(PDO::FETCH_ASSOC);
            echo $row1['point']."&nbsp&nbsp";

            echo '<a class="title-link" href="viewThread.php?id='.$row['id'].'">'.htmlspecialchars($row['title']).'</a>';
            echo '<font class="tex"> &nbsp;By '.htmlspecialchars($row['nickname']).'</font><br>';
            echo '<hr class="hr_02">';
        }
    }
    else {
        echo '看板不存在';
    }
}
else {
    echo '未指定看板';
}
 
?>
</div>
</body></html>