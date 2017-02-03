<?php
require('Mysql_Connect.php');
//require('phpMQTT.php');
/* 輸入申請的Line Developers 資料  */
$channel_id = "1474463460";
$channel_secret = "1d2f0bf0e9d9f3d9c5e00c80565f85b9";
$mid = "ue06f9f8ed24beb97c03c253c632b8dba";
 
/* 將收到的資料整理至變數 */
$receive = json_decode(file_get_contents("php://input"));
$eventType = $receive->result[0]->eventType;//抓取事件指令
$event = substr($eventType,-3,1);

	switch ($event) {
		case "3"://訊息
			$from = $receive->result[0]->content->from;//傳送要求的LINE
			$text = $receive->result{0}->content->text;
			$content_type = $receive->result[0]->content->contentType;
			$message = getBoubouMessage($text,$from);
			break;
		case "4"://操作
			$from = $receive->result[0]->content->params[0];//傳送要求的LINE
			$opType = $receive->result[0]->content->opType;
			$message = getBoubouevent($opType);
			break;
		default:
			break;
	}	

/* 準備Post回Line伺服器的資料 */
$header = ["Content-Type: application/json; charser=UTF-8", "X-Line-ChannelID:" . $channel_id, "X-Line-ChannelSecret:" . $channel_secret, "X-Line-Trusted-User-With-ACL:" . $mid];
sendMessage($header, $from, $message);

 
/* 發送訊息 */
function sendMessage($header, $to, $message) {
 
	$url = "https://trialbot-api.line.me/v1/events";
	$data = ["to" => [$to], "toChannel" => 1383378250, "eventType" => "138311608800106203", "content" => ["contentType" => 1, "toType" => 1, "text" => $message]];
	$context = stream_context_create(array(
	"http" => array("method" => "POST", "header" => implode(PHP_EOL, $header), "content" => json_encode($data), "ignore_errors" => true)
	));
	file_get_contents($url, false, $context);
}

/*處理收到的指令*/

 function getBoubouevent($value){	
 		if($value == 4){
			return "感謝你將本帳號設為好友" ;
 		}
}

/*處理收到的訊息*/

function getBoubouMessage($value,$value2){	

$from = $value2;
$sum = strlen($value);
$instruction = substr($value,-$sum,1);
$text = substr($value,-$sum+1,$sum-1);


switch ($instruction) {

		
		case "#": //輸入機台編號向SERVER獲取KEY
				
				$sql = "select client_ID,use_time from ncut_client_used where client_ID = '$from'";
				$result = mysql_query($sql);
				$row_ = mysql_num_rows($result);
				$row = mysql_fetch_array($result);
				date_default_timezone_set('Asia/Taipei');
				$datetime = date("Y-m-d H:i:s");
				$time = (int)strtotime($datetime);
				$use_time = (int)strtotime($row['use_time']);
				$difference_time = $time - $use_time;

				if($row_ > 0){			//代表曾使用過
					
					

						if ($difference_time > 86400){  //上次使用已經過一天
							$key = substr(md5(uniqid(rand(), true)),0,8); //產生一個8字元的KEY

								$sql = "insert into ncut_advertising_key
											(machine_ID,client_ID,line_key,key_time,key_status)
								    	values
											('$text','$from','$key','$datetime','1')"; //產生KEY並記錄到資料庫
								$result = mysql_query($sql);


							/*$mqtt = new phpMQTT("106.104.5.195", 1883, $text); //Change client name to something unique
						if ($mqtt->connect()) {
							$mqtt->publish($text,$key,0);
							$mqtt->close();
							}*/
							return "請輸入@key";
							
						}
						else{
							return "此用戶今日已使用過優惠服務";
						}
				}
				else{
							$key = substr(md5(uniqid(rand(), true)),0,8); //產生一個8字元的KEY
								$sql = "insert into ncut_advertising_key
											(machine_ID,client_ID,line_key,key_time,key_status)
								    	values
											('$text','$from','$key','$datetime','1')"; //產生KEY並記錄到資料庫
								$result = mysql_query($sql);

								$mqtt = new phpMQTT("106.104.5.195", 1883, $text); //Change client name to something unique
									if ($mqtt->connect()) {
										$mqtt->publish($text,$key,0);
										$mqtt->close();
										}
						
							return "請輸入@key";
					}
				break;

	
		case "@": //輸入KEY的操作
				$sql = "select line_key,machine_ID,client_ID,key_status from ncut_advertising_key where client_ID = '$from' and line_key = '$text'";
				$result = mysql_query($sql);
				$row_ = mysql_num_rows($result);
				$row = mysql_fetch_array($result);
				$machine_ID = $row['machine_ID'];
				$key_status = $row['key_status'];
				$client_ID = $row['client_ID'];
				$line_key = $row['line_key'];
				date_default_timezone_set('Asia/Taipei');
				$datetime = date("Y-m-d H:i:s");
				

			if($row_> 0 and $key_status == 1){ //有這個KEY 且他能用

						$sql = "select client_ID from ncut_client_used where client_ID = '$client_ID'";  //是否曾經使用過優惠
						$result = mysql_query($sql);
						$row_1 = mysql_num_rows($result);

						if($row_1>0){//i有用過的客戶刷新資料
							$sql = "update ncut_advertising_key set key_status = '0'
									where client_ID = '$client_ID' and line_key = '$text'";
							$result = mysql_query($sql);

							$sql = "update ncut_client_used set client_ID = '$client_ID', line_key ='$text' ,use_time = '$datetime'
									where client_ID = '$client_ID'";
							$result = mysql_query($sql);

							$mqtt = new phpMQTT("106.104.5.195", 1883, $machine_ID); //Change client name to something unique
							if ($mqtt->connect()) {
							$mqtt->publish($machine_ID,"true",0);
							$mqtt->close();
							}
						return "感謝您的使用";
						
						}
						else{
								$sql = "update ncut_advertising_key set key_status = '0'
									where client_ID = '$client_ID' and line_key = '$text'";
								$result = mysql_query($sql);

								$sql = "insert into ncut_client_used
											(client_ID,line_key,use_time)
								    	values
											('$client_ID','$text','$datetime')";
								$result = mysql_query($sql);

							$mqtt = new phpMQTT("106.104.5.195", 1883, $machine_ID); //Change client name to something unique

							if ($mqtt->connect()) {
							$mqtt->publish($machine_ID,"true",0);
							$mqtt->close();
							}
						return "感謝您的使用";
						

						}
				}
				else {
					return "無效key";
					
				}
				break;
        default:
			return "陸豪,你好";
			break;
		}

}


?>