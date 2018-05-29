<?php 
	header('Access-Control-Allow-Origin: *');
	$link = mysqli_connect("localhost", "root", "", "pars");

	if (!$link):
		echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
		echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
		echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
		exit;
	endif;

	if(!isset($_GET['getPhones'])):

			echo 
			'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
			<html>
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
					<title>get phone number</title>
				</head>
				<body>
					<div class="content">
						<div class="form">
							<form method="POST" action="'. 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] .'"> 
								<input type="text" name="uri" placeholder="URI groups">
								<button type="submit">phone numbers</button>
							</form>
						</div>
					</div>
				</body>
			</html>';

			$client_id = '6421402'; // ID приложения
			$client_secret = 'MMVjp4Bk6w18rx9ewpON'; // Защищённый ключ

			$token = null;

			$redirect_uri = 'https://'.$_SERVER['HTTP_HOST'].'/parser/index2.php';
			$url = 'http://oauth.vk.com/authorize';

			$params = array(
					    'client_id'     => $client_id,
					    'redirect_uri'  => $redirect_uri,
					    'response_type' => 'code'
			);

			if( !isset($_GET['code']) ):
				header('Location: '. $url . '?' . urldecode(http_build_query($params)) );
			else:

				$result = mysqli_query($link, "SELECT `code` FROM `attemps`");
				$row = $result->fetch_array(MYSQLI_NUM);
				$old_code = $row[0];
				
				if( $old_code !== $_GET['code'] ):
					
					mysqli_query($link, "UPDATE `attemps` SET `code`='".$_GET['code']."'");

					unset($params['response_type']);

					$params['code'] = $_GET['code'];
					$params['client_secret'] = $client_secret;

					$token = getAccessToken($params);	
					
					if( is_null($token) ):
						echo 'Не удалось получить access token!';
						exit;
					endif;
				endif;
			endif;

			if( $old_code === $_GET['code'] ):
				$result = mysqli_query($link, "SELECT `access_token` FROM `attemps`");
				$row = $result->fetch_array(MYSQLI_NUM);
				$token = $row[0];	

			else:
				mysqli_query($link, "UPDATE `attemps` SET `access_token`='".$token."'");
			endif;
					
			if( isset($_POST['uri']) ):

				$users = null;

				$group_name_reg = Array();
				preg_match('/https:\/\/vk.com\/(.*)/', $_POST['uri'], $group_name_reg);

				if( $curl = curl_init() ):
				    curl_setopt($curl, CURLOPT_URL, 'https://api.vk.com/method/groups.getMembers?');
				    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
				    curl_setopt($curl, CURLOPT_POST, true);
				    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(
				    					array(
				    						'group_id' => $group_name_reg[1],
				    						'v' => '5.16',
				    						'' => 'bdate,city,country,photo_200_orig,photo_max_orig',
				    						'access_token' => $token,		
				    					)
				    				)
					);
				    $out = curl_exec($curl); 
				  
				    $users = json_decode($out)->response->items;

				    curl_close($curl);

				    if( !is_null($users) ):  
				    	$users = json_decode(file_get_contents('https://api.vk.com/method/groups.getMembers?users.get?user_ids=47&v=5.16&access_token='.$token.'&fields=contacts&group_id='.
				    		$group_name_reg[1]))->response->items;
				    	$number = Array();
				    	foreach ($users as $item) :
				    		if(isset($item->home_phone) && isset($item->mobile_phone)):
				    			$phone = $item->home_phone > $item->mobile_phone ? $item->home_phone : $item->mobile_phone; 
				    			preg_match('/^(\s*)?(\+)?([- _():=+]?\d[- _():=+]?){10,14}(\s*)?$/', $phone, $number);		
				    			if(!empty($number[0])):
				    				mysqli_query($link, "INSERT INTO `phones` (`value`) VALUES (`".$number[0]."`)");
				    			endif;	
				    		elseif(!isset($item->home_phone) && isset($item->mobile_phone)): 
				    			preg_match('/^(\s*)?(\+)?([- _():=+]?\d[- _():=+]?){10,14}(\s*)?$/', $item->mobile_phone, $number);
				    			if(!empty($number[0])):
				    			    mysqli_query($link, "INSERT INTO `phones` (`value`) VALUES (`".$number[0]."`)");
				    			endif; 		
				    		elseif(isset($item->home_phone)):
				    			preg_match('/^(\s*)?(\+)?([- _():=+]?\d[- _():=+]?){10,14}(\s*)?$/', $item->home_phone, $number);
				    			$inspect = false;
				    			if(!empty($number[0])):	
				    				$result = mysqli_query($link, "SELECT `value` FROM `phones`");
									$row = mysqli_fetch_all($result, MYSQLI_NUM);
									foreach ($row as $item):
										if($item[0] == $number[0]):
											$inspect = true;
											break;
										endif;
									endforeach;
									if(!$inspect):
				    			    	mysqli_query($link, "INSERT INTO `phones` (`value`) VALUES ('".$number[0]."')");
				    				else:
				    					$inspect = false;
				    				endif;	
				    			endif;
				    		endif;
				    	endforeach;
				    else:
				    	echo 'Участники сообщества не найдены!';
				    	exit;
				    endif;	    
			    endif;
			endif;
	else:
		$result = mysqli_query($link, "SELECT `value` FROM `phones`");
		$row = mysqli_fetch_all($result, MYSQLI_NUM);
		echo json_encode($row);																				
	endif;


	function getAccessToken($params)
	{	
		$data = json_decode( file_get_contents('https://oauth.vk.com/access_token' . '?' . urldecode(http_build_query($params)) ), true);   

		$token = $data['access_token'];			

		if( !is_null($token) ):
			return $token;		
		else:
			return false;
		endif;
	}
?>