<?php
$db_servers = '127.0.0.1'; //伺服器位址
$db_account = 'root';      //資料庫伺服器帳號
$db_password = 'stars1030';	   //資料庫伺服器密碼
$db_name = 'luhao';      //資料庫名稱

error_reporting(E_ALL ^ E_DEPRECATED); //防止格式過期訊息

$db_link = @mysql_connect($db_servers, $db_account, $db_password);  //連接資料庫伺服器
$db_database = @mysql_select_db($db_name);  //連接指定資料庫

	if(!$db_link) die("與資料庫連線失敗");
	if(!$db_database)die("資料庫不正確");

mysql_query("SET NAMES utf8");


?>