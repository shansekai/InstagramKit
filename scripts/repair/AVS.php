<?php  
/**
* Instagram Auto View Story
* Last Update 28 Juni 2020
* Author : Faanteyki
*/
require "../vendor/autoload.php";

use Riedayme\InstagramKit\InstagramAuth;
use Riedayme\InstagramKit\InstagramChecker;
use Riedayme\InstagramKit\InstagramFeedStory;
use Riedayme\InstagramKit\InstagramUserStory;
use Riedayme\InstagramKit\InstagramSeenStory;

date_default_timezone_set('Asia/Jakarta');

Class InputHelper
{
	public function GetInputUsername($data = false) {

		if ($data) return $data;

		$CheckPreviousData = InstagramAutoViewStory::CheckPreviousData();

		if ($CheckPreviousData) {
			echo "[?] Anda Memiliki Cookie yang tersimpan pilih angkanya dan gunakan kembali : ".PHP_EOL;
			foreach ($CheckPreviousData as $key => $cookie) {
				echo "[{$key}] ".$cookie['username'].PHP_EOL;

				$data_cookie[] = $key;
			}
			echo "[x] Masuk menggunakan akun baru".PHP_EOL;

			echo "[?] Pilihan Anda : ";

			$input = strtolower(trim(fgets(STDIN)));			

			if ($input != 'x') {

				if (strval($input) !== strval(intval($input))) {
					die(PHP_EOL."Salah memasukan format, pastikan hanya angka".PHP_EOL);
				}

				if (!in_array($input, $data_cookie)) {
					die("Pilihan tidak ditemukan");
				}

				return [$input];
			}
		}	

		echo "[?] Masukan Username : ";

		return trim(fgets(STDIN));
	}

	public function GetInputPassword($data = false) {

		if ($data) return $data;

		echo "[?] Masukan Password : ";

		return trim(fgets(STDIN));
	}
}

Class InstagramAutoViewStory
{

	public $username;
	public $cookie;
	public $csrftoken;

	public function Auth($data) 
	{

		if (is_array($data['username'])) {

			echo "[•] Login Menggunakan Cookie".PHP_EOL;

			$results = self::ReadPreviousData($data['username'][0]);

			echo "[•] Check Live Cookie".PHP_EOL;

			$check_cookie = InstagramChecker::CheckLiveCookie($results['cookie']);
			if (!$check_cookie) die("[ERROR] cookie tidak bisa digunakan".PHP_EOL);

		}else{	

			echo "[•] Login Menggunakan Username dan Password".PHP_EOL;

			$results = InstagramAuth::AuthLoginByWebAjax($data['username'],$data['password']);			

			echo "[•] Menyimpan Data Login".PHP_EOL;

			self::SaveLogin($results);
		}

		$this->cookie = $results['cookie'];
		$this->csrftoken = $results['csrftoken'];
		$this->username = $results['username'];
	}

	public function SaveLogin($data){

		$filename = 'data/sc-global.json';

		if (file_exists($filename)) {
			$read = file_get_contents($filename);
			$read = json_decode($read,true);
			$dataexist = false;
			foreach ($read as $key => $logdata) {
				if ($logdata['userid'] == $data['userid']) {
					$inputdata[] = $data;
					$dataexist = true;
				}else{
					$inputdata[] = $logdata;
				}
			}

			if (!$dataexist) {
				$inputdata[] = $data;
			}
		}else{
			$inputdata[] = $data;
		}

		return file_put_contents($filename, json_encode($inputdata,JSON_PRETTY_PRINT));
	}

	public function CheckPreviousData()
	{

		$filename = 'data/sc-global.json';
		if (file_exists($filename)) {
			$read = file_get_contents($filename);
			$read = json_decode($read,TRUE);
			foreach ($read as $key => $logdata) {
				$inputdata[] = $logdata;
			}

			return $inputdata;
		}else{
			return false;
		}
	}

	public function ReadPreviousData($data)
	{

		$filename = 'data/sc-global.json';
		if (file_exists($filename)) {
			$read = file_get_contents($filename);
			$read = json_decode($read,TRUE);
			foreach ($read as $key => $logdata) {
				if ($key == $data) {
					$inputdata = $logdata;
					break;
				}
			}

			return $inputdata;
		}else{
			die("[!] File Config Tidak ditemukan");
		}
	}

	public function GetStory()
	{

		echo "[•] Membaca Feed Story".PHP_EOL;

		$feedstory = new InstagramFeedStory();
		$feedstory->SetCookie($this->cookie);
		$story = $feedstory->Process();

		if (!$story['status']) {
			return 'fail_get_feed_story';
		}

		$results = $feedstory->Extract($story);

		foreach ($results as $userdata) {
			$user_ids[] = $userdata['id'];
		}

		echo "[•] Membaca Story User".PHP_EOL;

		$read = new InstagramUserStory();
		$read->SetCookie($this->cookie);
		$story = $read->Process($user_ids);

		if (!$story['status']) {
			return 'fail_get_story_user';
		}

		$StoryUser = $read->Extract($story);

		return self::SyncStory($StoryUser);
	}

	public function SeenStory($story)
	{
		echo "[•] Proses Seen Story {$story['username']}||{$story['id']}".PHP_EOL;

		$seenstory = new InstagramSeenStory();
		$seenstory->SetCookie($this->cookie);
		$process = $seenstory->Process($story);

		if ($process['status'] != false) {
			echo "[+++] Sukses Seen Story {$story['id']}".PHP_EOL;
			echo "[•] Time : ".date('d-m-Y H:i:s').PHP_EOL;
			self::SaveLog($this->username,$story['id']);
		}else{
			echo "[---] Gagal Seen Story {$story['id']}".PHP_EOL;
			echo "[•] Time : ".date('d-m-Y H:i:s').PHP_EOL;
			echo "[•] Response : {$process['response']}".PHP_EOL;			
		}
	}

	public function SyncStory($storydata)
	{

		echo "[•] Sync Story User".PHP_EOL;

		$ReadLog = self::ReadLog($this->username);

		$results = array();
		$freshstory = array();
		foreach ($storydata as $story) {
			if (is_array($ReadLog) AND in_array($story['id'], $ReadLog)) {
				echo "Skip {$story['id']}, Story sudah dilihat. ".PHP_EOL;
				$freshstory[] = $story['id'];
				continue;
			}

			$results[] = $story;
		}

		/* Update Log Data Fresh Story */
		if (count($storydata) != count($ReadLog) - 1) {
			echo "[•] Update Log Story".PHP_EOL;
			self::SaveLog($this->username,implode(PHP_EOL, $freshstory),false);
		}

		return $results;
	}

	public function ReadLog($identity)
	{		

		$logfilename = "log/story-data-{$identity}";
		$log_url = array();
		if (file_exists($logfilename)) 
		{
			$log_url = file_get_contents($logfilename);
			$log_url  = explode(PHP_EOL, $log_url);
		}

		return $log_url;
	}

	public function SaveLog($identity,$datastory,$append = true)
	{
		if ($append) {
			return file_put_contents("log/story-data-{$identity}", $datastory.PHP_EOL, FILE_APPEND);
		}else{			
			return file_put_contents("log/story-data-{$identity}", $datastory.PHP_EOL);
		}
	}
}

Class Worker
{
	public function Run()
	{

		echo "Instagram Auto View Story".PHP_EOL;

		$account['username'] = InputHelper::GetInputUsername();

		if (!is_array($account['username'])) {
			$account['password'] = InputHelper::GetInputPassword();
		}

		$delay_default = 30;
		$delay = 30;
		$delaystory_default = 60;
		$delaystory = 60;

		/* Call Class */
		$Working = new InstagramAutoViewStory();
		$Working->Auth($account);

		$nostorystatus = 0;
		$seenstory = 0;
		$nogetstory = 0;
		while (true) {

			/* when nostorystatus 5 reset sleep value to default */
			if ($nostorystatus >= 5) {
				$delaystory = $delaystory_default;
				$nostorystatus = 0;
			}

			/* when nogetstory 3 die because the cookie death */
			if ($nogetstory >= 3) die("[!] cookie sudah mati");

			$StoryList = $Working->GetStory();

			if ($StoryList == 'fail_get_feed_story' OR $StoryList == 'fail_get_story_user') {
				$nogetstory++;
			}else{
				$nogetstory = 0; /* reset value */
			}

			if (empty($StoryList)) {

				echo "[•] Tidak ditemukan Story, Coba lagi setelah {$delaystory} detik".PHP_EOL;
				sleep($delaystory);

				$delaystory = $delaystory*rand(2,3);
				$nostorystatus++;

				continue;
			}

			foreach ($StoryList as $key => $story) {

				/* when seenstory 5 reset sleep value to default */
				if ($seenstory >= 5) {
					$delay = $delay_default;
					$seenstory = 0;
				}	

				$Working->SeenStory($story);

				echo "[•] Delay {$delay}".PHP_EOL;
				sleep($delay);

				$delay = $delay+5;
				$seenstory++;
			}

		}		

	}
}

Worker::Run();
// use at you own risk