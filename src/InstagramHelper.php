<?php namespace Riedayme\InstagramKit;

Class InstagramHelper
{

	public static function curl($url, $postdata = 0, $header = 0, $cookie = 0, $useragent = 0, $proxy = array()) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, false);
		curl_setopt($ch, CURLOPT_HEADER, 1);

		if($header) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		}

		if($postdata) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($postdata != 'empty') {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
			}
		}

		if($cookie) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		}

		if ($useragent) {
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		}

		if (!empty($proxy['proxy']['ip'])){
			curl_setopt($ch, CURLOPT_PROXY, $proxy['proxy']['ip']);
		}

		if (!empty($proxy['proxy']['userpwd'])){
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['proxy']['userpwd']);
		}

		if (!empty($proxy['proxy']['socks5'])){
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		}

		$response = curl_exec($ch);

		$httpcode = curl_getinfo($ch);
		if(!$httpcode) {
			curl_close($ch);	
			die("Response header not found"); 
		}
		else{

			$header = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
			$body = substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));

			curl_close($ch);

			return [
			'header' => $header,
			'body' => $body
			];
		}
	}	

	public static function FindStringOnArray($arr, $string) {
		return array_filter($arr, function($value) use ($string) {
			return strpos($value, $string) !== false;
		});
	}

	public static function GetStringBetween($string,$start,$end){
		$str = explode($start,$string);
		if (empty($str[1])) return false;
		$str = explode($end,$str[1]);
		return $str[0];
	}

	public static function ParseAccessToken($data)
	{
		$data = str_replace(['view-source:','#'], '', $data);
		parse_str(parse_url($data, PHP_URL_QUERY), $output);;

		if (empty($output['access_token'])) {
			die("[ERROR] Token tidak ditemukan");
		}

		$token = $output['access_token'];

		return $token;
	}

	public static function GetSleepTimeByLimit($limit,$type = 'day'){

		switch ($type) {
			case 'day':
			$dayinseconds = 86400;
			$sleep = $dayinseconds/$limit;
			break;

			case 'hours':
			$hoursinseconds = 3600;
			$sleep = $hoursinseconds/$limit;
			break;			

			case '12hours';
			$twelvehoursinseconds = 43200;
			$sleep = $twelvehoursinseconds/$limit;
			break;
		}


		return ceil($sleep);
	}

	public static function GetSleepTime($choice = '30minutes'){

		switch ($choice) {
			case '10minutes':
			$seconds = 600;
			break;

			case '20minutes':
			$seconds = 1200;
			break;			

			case '30minutes';
			$seconds = 1800;
			break;

			case '60minutes';
			$seconds = 3600;
			break;			
		}

		return $seconds;
	}	

	public static function DownloadByURL($url,$dir = './'){
		$ch		=	curl_init($url);
		$fileName		=	explode('?', basename($url))[0];
		$saveFilePath	=	$dir . $fileName;
		$fp				=	fopen($saveFilePath, 'wb');
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		curl_close($ch);	
		fclose($fp);

		return $fileName;
	}	

	/**
	 * @param string $source (accepted jpg, gif & png filenames)
	 * @param string $destination
	 * @param int $quality [0-100]
	 * @throws \Exception
	 * https://stackoverflow.com/questions/14549446/how-can-i-convert-all-images-to-jpg
	 */
	public static function convertToJpeg($source, $destination, $quality = 100) {

		if ($quality < 0 || $quality > 100) {
			throw new \Exception("Param 'quality' out of range.");
		}


		if (!file_exists($source)) {
			throw new \Exception("Image file not found.");
		}

		$ext = pathinfo($source, PATHINFO_EXTENSION);

		if (preg_match('/jpg|jpeg/i', $ext)) {
			$image = imagecreatefromjpeg($source);
		} else if (preg_match('/png/i', $ext)) {
			$image = imagecreatefrompng($source);
		} else if (preg_match('/gif/i', $ext)) {
			$image = imagecreatefromgif($source);
		} else {
			throw new \Exception("Image isn't recognized.");
		}

		$result = imagejpeg($image, $destination, $quality);

		if (!$result) {
			throw new \Exception("Saving to file exception.");
		}

		imagedestroy($image);

		return $destination;
	}

	public static function GetCSRF($source)
	{

		if (empty($source)) return false;

		switch ($source) {

			case 'web':

			$fetch = InstagramHelper::curl('https://www.instagram.com/data/shared_data/');
			$header = $fetch['header'];

			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);

			$cookies = array();
			foreach($matches[1] as $item) {
				parse_str($item, $cookie);
				$cookies = array_merge($cookies, $cookie);
			}

			$buildcookie = '';
			foreach ($cookies as $key => $read) {
				$buildcookie .= "{$key}={$read};";
			}

			return [
			'csrftoken' => $cookies['csrftoken'],
			'all' => $buildcookie
			];

			break;

			case 'api':

			$fetch = InstagramHelper::curl('https://i.instagram.com/api/v1/si/fetch_headers/?challenge_type=signup');
			$header = $fetch['header'];

			if (!preg_match('/^Set-Cookie:\s*([^;]*)/mi', $header, $token)) {
				die("[ERROR] Tidak ditemukan csrftoken");
			} else {
				return substr($token[0], 22);
			}

			break;

		}
	}

	/**
	 * https://stackoverflow.com/questions/19420715/check-if-specific-array-key-exists-in-multidimensional-array-php
	 */
	function findKey($key,$arr) {

		if (!is_array($arr)) return false;

    // is in base array?
		if (array_key_exists($key, $arr)) {
			return true;
		}

    // check arrays contained in this array
		foreach ($arr as $element) {
			if (is_array($element)) {
				if (multiKeyExists($element, $key)) {
					return true;
				}
			}

		}

		return false;
	}

	/**
	 * https://stackoverflow.com/questions/29184063/how-to-sort-array-list-in-zig-zag-in-php
	 */
	public function BuildShufflePost($post_arr)
	{

		$groups = array_map(null, ...$post_arr);

		$out = array();
		foreach($groups as $arr)
		{
			foreach($arr as $key => $val){    	
				if ($val) {
					$out[] = $val;
				}
			}
		}

		return $out;
	}		
}