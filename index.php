<?php
header('Content-type: text/html; charset=utf-8');
require(__DIR__ . '/hack.class.php');

if (PHP_SAPI == 'cli') {
	$action = isset($argv[1]) ? $argv[1] : '';
} else {
	$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
}

$dbconfig = array(
	'host'  =>  '127.0.0.1',
	'user'  =>  'ubox_hack',
	'pass'  =>  'ubox_passpasspass',
	'name'  =>  'ubox_hack',
	'port'  =>  3306,
);

switch ($action) {
case 'login':
case 'request':
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
	
	$obj = new UboxHack($login_as, $login, $password);
	$uid = $obj->login();
	if ($uid) {
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
			if ($action == 'request') {
				$appinfo = $obj->app_newGetAppInfo();

				foreach ($appinfo as $platform=>$apps) {
					$obj->setPlatform($platform);
					$intersect = array_intersect($coupon_ids, $apps['coupon']);
					if (!empty($intersect)) {
						$coupon_ids = $intersect;
					} else {
						$coupon_ids = $apps['coupon'];
					}
					foreach ($apps['task'] as $a) {
						$app_id = $a['app_id'];

						# claim task
						$r = $obj->app_newReceiveTask($app_id); 
		var_dump($r);echo '<br />';
						$coupon_id = $coupon_ids[array_rand($coupon_ids)];
var_dump($coupon_id);echo '<br />';
						$r = $obj->app_newCreateCoupon($app_id, $coupon_id);
		var_dump($r);echo '<br />';
					}
				}
				echo 'Uid: ' . $uid;exit;
			} else {
				die('User added as uid:' . $uid);
			}
		} else {
			die('Failed to add user, already exists? uid:'.$uid);
		}
	} else {
		die('Login failed');
	}
	break;

case 'cron':
	$userobj = new UboxUser($dbconfig);
	$users = $userobj->findAll();
	foreach ($users as $u) {
		$login_as = $u['phone'] ? UboxHack::AS_PHONE : UboxHack::AS_EMAIL;
		$login	= $u['phone'] ? $u['phone'] : $u['email'];
		$obj = new UboxHack($login_as, $login, $u['password'], $u['uid']);
		$obj->login();
		$appinfo = $obj->app_newGetAppInfo();
		foreach ($appinfo as $platform=>$apps) {
			$obj->setPlatform($platform);
			$intersect = array_intersect($u['coupon_ids'], $apps['coupon']);
			if (!empty($intersect)) {
				$coupon_ids = $intersect;
			} else {
				$coupon_ids = $apps['coupon'];
			}
			foreach ($apps['task'] as $a) {
				$app_id = $a['app_id'];

				# claim task
				$r = $obj->app_newReceiveTask($app_id); 
var_dump($r);
				$coupon_id = $coupon_ids[array_rand($coupon_ids)];
var_dump($coupon_id);
				$r = $obj->app_newCreateCoupon($app_id, $coupon_id);
var_dump($r);
			}
		}
		if ($u['gift_phone']) {
			# is user
			$gu = $obj->user_isUser($u['gift_phone']);
			if (isset($gu['user_id'])) {
				echo "GIFT NOW\n";
				# list
				$cl = $obj->coupon_couponNewAdminList('free');
				# loop gift
				if (isset($cl['couponList']['data']) && !empty($cl['couponList']['data'])) {
					foreach ($cl['couponList']['data'] as $coupon) {
						if ($coupon['canPresent'] == 'yes') {
							$r = $obj->coupon_presentCoupon($gu['user_id'], $u['gift_phone'], $coupon['id']);
							var_dump($r);
						}
					}
				}
				
			}
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
<input type="submit" name="action" value="login" /> = 
<input type="submit" name="action" value="request" />
</form>

Notice: <br />
Your ubox login and password will be recorded when you click either 'login' or 'request', i think you know why. <br />
It's recommended to use a weak/low-level password if you're not about to leave some money in ur account. <br />
<a href="http://sskaje.me/">&copy;sskaje</a>
REGISTER;
	break;
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

