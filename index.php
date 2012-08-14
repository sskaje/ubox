<?php
header('Content-type: text/html; charset=utf-8');
require(__DIR__ . '/config.php');
require(__DIR__ . '/hack.class.php');

if (PHP_SAPI == 'cli') {
	$action = isset($argv[1]) ? $argv[1] : '';
} else {
	$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
}

switch ($action) {
case 'login':
case 'gift':
	if (!isset($_REQUEST['login_as']) || !$_REQUEST['login_as']) {
		$login_as = UboxHack::AS_PHONE;
	} else {
		$login_as = UboxHack::AS_EMAIL;
	}
	if (!isset($_REQUEST['login']) || !isset($_REQUEST['password'])) {
		die('Missing fields');
	}
	if (!isset($_REQUEST['gift_phone'])) {
		$_REQUEST['gift_phone'] = '';
	}
	if (!isset($_REQUEST['coupon_ids'])) {
		$_REQUEST['coupon_ids'] = array();
	}
	$login = $_REQUEST['login'];
	$password = $_REQUEST['password'];
	$gift_phone = $_REQUEST['gift_phone'];
	if ($login == $gift_phone) {
		$gift_phone = '';
	}
	
	$coupon_ids = array_map('intval', (array) $_REQUEST['coupon_ids']);
	if ($action == 'login') {
		$test_flag = hack($login_as, $login, $password, $uid, $coupon_ids) && $uid;
	} else {
		$test_flag = gift($login_as, $login, $password, $uid, $gift_phone) && $uid;
	}
	
	if ($test_flag) {
		$userobj = new UboxUser($dbconfig);
		$ret = $userobj->add(
			$uid, 
			$login_as == UboxHack::AS_PHONE ? $login : '',
			$login_as == UboxHack::AS_PHONE ? '' : $login ,
			$password, 
			$coupon_ids,
			$gift_phone
		);
		if ($ret) {
			die('User added as uid:' . $uid);
		} else {
			die('Failed to add user, already exists? uid:'.$uid);
		}
	} else {
		die('Login failed');
	}
	break;

case 'cron':
case 'cron_gift':
	$userobj = new UboxUser($dbconfig);
	$users = $userobj->findAll();
	foreach ($users as $u) {
		$login_as = $u['phone'] ? UboxHack::AS_PHONE : UboxHack::AS_EMAIL;
		$login	= $u['phone'] ? $u['phone'] : $u['email'];
		
		if ($action == 'cron') {
			hack($login_as, $login, $u['password'], $u['uid'], $u['coupon_ids']);
		}
		
		if ($u['gift_phone']) {
			gift($login_as, $login, $u['password'], $uid['uid'], $u['gift_phone']);
		}
	}
	break;

default:
	echo <<<REGISTER

<form action="" method="post">
Phone: <input type="text" name="login" /> <br />
Password: <input type="text" name="password" /> <br />
<label><input type="checkbox" name="coupon_ids[]" value="6" />可乐</label>
<label><input type="checkbox" name="coupon_ids[]" value="15" />雪碧</label>
<label><input type="checkbox" name="coupon_ids[]" value="18" />冰红茶</label>
<label><input type="checkbox" name="coupon_ids[]" value="19" />柚子茶</label>
<label><input type="checkbox" name="coupon_ids[]" value="20" />鲜橙多</label>

<br />
Send Gift To: <input type="text" name="gift_phone" />（phone no）
<br />
<input type="hidden" name="login_as" value="0" />
<input type="submit" name="action" value="login" />
<input type="submit" name="action" value="gift" />
</form>

Click 'login' to: claim a new task and finish it.<br />
Click 'gift' to: send all coupons to 'gift phone' if possible.<br />
Notice: <br />
Your ubox login and password will be recorded when you click either 'login' or 'gift', i think you know why. <br />
It's recommended to use a weak/low-level password if you're not about to leave some money in ur account. <br />
<a href="http://sskaje.me/">&copy;sskaje</a>
REGISTER;
	break;
}

function getAnswers()
{
	static $answers = array();
	if (empty($answers)) {
		global $answer_file;
		$examdb = file($answer_file);
		if (empty($examdb)) {
			die('please place your answers to ' . $answer_file . ', content: app_xx|A|xxxxxxxxxxxx (app_id|answer|sn)');
		}
		
		foreach ($examdb as $f) {
			$t = explode('|', trim($f));
			if (isset($t[2])) {
				$answers[$t[0]][$t[2]] = $t[1];
			}
		}
	}
	
	return $answers;
}

function hack($login_as, $login, $password, & $uid=0, $user_coupon_list=array())
{
	$obj = new UboxHack($login_as, $login, $password, $uid);
	$uid = $obj->login();
	if (empty($uid)) {
		echo "$login failed\n";
		return false;
	}
	$r = $obj->app_newGetAppList();
	$answers = getAnswers();

	foreach ($r as $platform=>$app_coupons) {
		$obj->setPlatform($platform);
		if (isset($app_coupons['no'])) {
			foreach ($app_coupons['no'] as $app_id => $coupon_ids) {
				if (!empty($user_coupon_list)) {
					$intersect = array_intersect($user_coupon_list, $coupon_ids);
				} else {
					$intersect = $coupon_ids;
				}
				$coupon_id = $intersect[array_rand($intersect)];
				
				$r = $obj->app_exchangeCoupon($app_id , $coupon_id);
				var_dump($r);
			}
		}
		if (isset($app_coupons['yes'])) {
			foreach ($app_coupons['yes'] as $app_id => $coupon_ids) {
				if (!empty($user_coupon_list)) {
					$intersect = array_intersect($user_coupon_list, $coupon_ids);
				} else {
					$intersect = $coupon_ids;
				}
				$coupon_id = $intersect[array_rand($intersect)];
			
				$r = $obj->app_receTask($app_id);
				$r = $obj->app_getExam($app_id);
				if (!isset($answers[$app_id][$r['sn']])) {
					die('Answer not found: app_id='.$app_id.', sn='.$r['sn']);
				}
				$r = $obj->app_answerExam($app_id, $r['sn'], $answers[$app_id][$r['sn']]);
				$r = $obj->app_exchangeCoupon($app_id, $coupon_id);

				var_dump($r);
			}
		}
	}
	
	return true;
}

function gift($login_as, $login, $password, & $uid=0, $gift_phone)
{
	$obj = new UboxHack($login_as, $login, $password, $uid);
	$uid = $obj->login();
	if (empty($uid)) {
		echo "$login failed\n";
		return false;
	}
	if ($gift_phone) {
		# is user
		$gu = $obj->user_isUser($gift_phone);
		if (isset($gu['user_id'])) {
			echo "GIFT NOW\n";
			# list
			$cl = $obj->coupon_couponNewAdminList('free');
			# loop gift
			if (isset($cl['couponList']['data']) && !empty($cl['couponList']['data'])) {
				foreach ($cl['couponList']['data'] as $coupon) {
					if ($coupon['canPresent'] == 'yes') {
						$r = $obj->coupon_presentCoupon($gu['user_id'], $gift_phone, $coupon['id']);
						var_dump($r);
					}
				}
			}
		}
		return true;
	}
	return false;
}

class UboxUser
{
	protected $db;

	public function __construct($config)
	{
		$this->db = new mysqli($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
	}

	public function add($uid, $phone, $email, $password, array $coupon_ids=array(), $gift_phone='')
	{
		$coupon_ids = json_encode($coupon_ids);
		$stmt = $this->db->prepare('REPLACE INTO `ubox_user` SET `uid`=?, `phone`=?, `email`=?, `password`=?, `coupon_ids`=?, `gift_phone`=?');
		$stmt->bind_param('isssss', $uid, $phone, $email, $password, $coupon_ids, $gift_phone);
		$ret = $stmt->execute();
		return $ret;
	}

	public function findAll() 
	{
		$result = $this->db->query('SELECT * FROM `ubox_user`');
		$ret = array();
		while(($row = $result->fetch_assoc())) {
			$row['coupon_ids'] = json_decode($row['coupon_ids'], true);
			$ret[] = $row;
		}
		return $ret;
	}
}

