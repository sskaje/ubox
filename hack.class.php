<?php
/**
 * Ubox Client Protocol
 *
 * @author sskaje
 */
class UboxHack 
{
    protected $curl;
    protected function init_curl()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $this->curl = $ch;
    }

    protected $uid;
    protected $phone;
    protected $password;
    protected $md5_password;
    protected $timestamp = '1364040473';
    protected $device_no = 'FFFFFFFFFFFD';
    protected $email;
    /**
     * 平台号
     * 1 iphone, 2 android, 3 kjava
     */
    protected $platform = 2;
    protected $all_platforms = array(
        1   =>  'iphone',
        2   =>  'android',
        3   =>  'kjava',
    );
    protected $login_as;
    const AS_PHONE = 'phone';
    const AS_EMAIL = 'email';

    public function __construct($login_as, $login, $password, $uid='') 
    {
        if ($login_as == self::AS_PHONE) {
            $this->phone = $login;
            $this->login_as = self::AS_PHONE;
        } else {
            $this->email = $login;
            $this->login_as = self::AS_EMAIL;
        }

        $this->password = $password;
        $this->md5_password = md5($password);
        $this->uid = $uid;

        $this->init_curl();
        $this->setPlatform(1);
    }

    public function login()
    {
        if (!$this->uid) {
            $array = array(
                'password'  =>  $this->md5_password,
            );
            if ($this->login_as == self::AS_PHONE) {
                $array['phone']     =  $this->phone;
            } else {
                $array['email']     =  $this->email;
            }
            # Login
            $ret = $this->request('user/login', $array, false);
            //var_dump($ret);
            if (isset($ret['user_id'])) {
                $this->uid = $ret['user_id'];
                return $this->uid;
            } else {
                return false;
            }
        } else {
            return null;
        }
    }

    public function register()
    {
        $array = array(
            'password'  =>  $this->md5_password,
        );
        if ($this->login_as == self::AS_PHONE) {
            $array['phone']     =  $this->phone;
        } else {
            $array['email']     =  $this->email;
        }

        # Login
        $ret = $this->request('user/register', $array, false);
        var_dump($ret);
        if (isset($ret['user_id'])) {
            $this->uid = $ret['user_id'];
        } else {
            var_dump($ret);exit;
        }
    }

    public function setPlatform($platform) 
    {
        if (isset($this->all_platforms[$platform])) {
            $this->platform = $platform;
            $this->device_no = substr(md5(uniqid('ss', true)), 0, 12);
        }
    }

    public function request($api, $array, $insert_uid=true) 
    {
        # deviceid: 1 iphone, 2 android
        $url = 'http://api.ubox.cn/'.$api.'.json?version=v1&deviceid='.$this->platform.'&clientversion=5.1.0';
        _log('POST: ' . $url);

        # TODO: ?
        if ($insert_uid) {
            $array['uid'] = $this->uid;
        }
        $sign = $this->getSignValue($array, $this->md5_password, $this->uid, $this->timestamp);
        $postfields = "sign={$sign}&" . $this->build_post_fields($array) . '&timestamp='.$this->timestamp;

        _log('DATA: ' . $postfields);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
        $ret = curl_exec($this->curl);
        //var_dump($ret);
        return json_decode($ret, true);
    }

    protected function getSignValue($array) {
        $secret = md5($this->md5_password. $this->timestamp);
        $array['appsecret'] = $secret;
        $array['timestamp'] = $this->timestamp;
        $array['uid'] = $this->uid;

        ksort($array);
        $r = array();
        foreach ($array as $k=>$v) {
            if (empty($v)) continue;
            $r[] = $k.'='.urlencode($v);
        }

        return strtoupper(md5(implode('&', $r)));
    }

    protected function build_post_fields($in)
    {
        $ret = array();
        foreach ($in as $k=>$v) {
            $ret[] = $k . '=' . urlencode($v);
        }
        return implode('&', $ret);
    }

    public function getAppInfo()
    {
        $ret = array();
        foreach ($this->all_platforms as $platform=>$platform_name) {
            # DEBUG LOG
            $this->setPlatform($platform);
			$array = array('device_no'=>$this->device_no);
            $r = $this->request('app/getAppInfo', $array);
            if (isset($r['data']) && !empty($r['data'])) {
                $ret[$platform] = $r['data'];
            }
        }
        return $ret;
    }

    public function app_receiveTask($app_id)
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'app_id'    =>  $app_id,
            'device_token'  =>  'token',
        );
        return $this->request('app/receiveTask', $array);
    }

    public function app_createCoupon($app_id)
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'app_id'    =>  $app_id,
            'device_token'  =>  'token',
        );
        return $this->request('app/createCoupon', $array);
    }
	
    public function app_newGetAppInfo()
    {
		/*
		{"data":[
		{"app_id":"app_42","icon_url":"http:\/\/img.ubox.cn\/misc\/img\/app\/yihaodian.png","download_url":"http:\/\/itunes.apple.com\/cn\/app\/id427457043?cid=1","app_name":"wccbyihaodian","app_title":"\u638c\u4e0a1\u53f7\u5e97","app_desc":"\u4f53\u9a8c\u5b8c\u7f8e\u62c7\u6307\u8d2d\u7269","present_desc":"\u4e0b\u8f7d\u5e76\u5b89\u88c5\u7acb\u9001\u53cb\u5b9d\u996e\u6599\u5238\u4e00\u5f20!","is_now":"1","use_count":"9119","receive_count":"10232","receive_state":0},
		{"app_id":"app_41","icon_url":"http:\/\/img.ubox.cn\/misc\/img\/app\/youku.png","download_url":"http:\/\/itunes.apple.com\/cn\/app\/id336141475?mt=8","app_name":"youku","app_title":"\u4f18\u9177","app_desc":"\u66f4\u6d41\u7545\u7684\u64ad\u653e\u4f53\u9a8c","present_desc":"\u4e0b\u8f7d\u5e76\u5b89\u88c5\u7acb\u9001\u53cb\u5b9d\u996e\u6599\u5238\u4e00\u5f20!","is_now":"0","use_count":"4088","receive_count":"4660","receive_state":0},
		{"app_id":"app_39","icon_url":"http:\/\/img.ubox.cn\/misc\/img\/app\/dreamzoo.png","download_url":"http:\/\/itunes.apple.com\/cn\/app\/dream-zoo\/id468606296?mt=8","app_name":"dreamzoo","app_title":"\u68a6\u60f3\u52a8\u7269\u56ed Dream Zoo","app_desc":"\u53ef\u7231\u52a8\u7269\u517b\u6210\u6e38\u620f","present_desc":"\u4e0b\u8f7d\u5e76\u5b89\u88c5\u7acb\u9001\u53cb\u5b9d\u996e\u6599\u5238\u4e00\u5f20!","is_now":"0","use_count":"4280","receive_count":"4848","receive_state":"1"}
		],
		"data_count":3,
		"template_list":
		[{"template_id":"6","template_title":"\u514d\u8d39\u53ef\u4e50\u4f18\u60e0\u5238","icon_url":"http:\/\/img.ubox.cn\/coupon\/kele.png"},
		{"template_id":"15","template_title":"\u514d\u8d39\u96ea\u78a7\u4f18\u60e0\u5238","icon_url":"http:\/\/img.ubox.cn\/coupon\/xuebi.png"},
		{"template_id":"18","template_title":"\u514d\u8d39\u51b0\u7ea2\u8336\u4f18\u60e0\u5238","icon_url":"http:\/\/img.ubox.cn\/coupon\/binghongcha.png"},
		{"template_id":"19","template_title":"\u514d\u8d39\u67da\u5b50\u8336\u4f18\u60e0\u5238","icon_url":"http:\/\/img.ubox.cn\/coupon\/youzicha.png"},
		{"template_id":"20","template_title":"\u514d\u8d39\u9c9c\u6a59\u591a\u4f18\u60e0\u5238","icon_url":"http:\/\/img.ubox.cn\/coupon\/xianchengduo.png"}],
		"head":{"status":200,"message":"HTTP\/1.1 200 OK","update":"0","new_version":null,"u_img":"http:\/\/img.ubox.cn\/misc\/img\/u.png","url":"","curtime":"1340177512"}}
		*/
        
        $ret = array();
        foreach ($this->all_platforms as $platform=>$platform_name) {
            # DEBUG LOG
            $this->setPlatform($platform);
			$array = array('device_no'=>$this->device_no);
            $r = $this->request('app/newGetAppInfo', $array);
            file_put_contents('/tmp/ubox_gift.log', json_encode($r) . "\n", FILE_APPEND);
            if (isset($r['data']) && !empty($r['data'])) {
                $ret[$platform]['task'] = $r['data'];
				$coupons = array();
				foreach ($r['template_list'] as $c) {
					$coupons[] = $c['template_id'];
				}
				$ret[$platform]['coupon'] = $coupons;
            }
        }
        return $ret;
    }
	
	public function app_newReceiveTask($app_id) 
	{
        $array = array(
            'device_no' =>  $this->device_no,
            'app_id'    =>  $app_id,
        );
        return $this->request('app/newReceiveTask', $array);
	}

    public function app_newCreateCoupon($app_id, $coupon_id)
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'app_id'    =>  $app_id,
			'template_id'	=>	$coupon_id,
        );
        return $this->request('app/newCreateCoupon', $array);
    }
    
    public function user_isUser($phone)
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'phone'		=>	$phone,
        );
        return $this->request('user/isUser', $array);
    }
    
    public function coupon_couponNewAdminList($gift_status='free')
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'page'		=>	1,
            'count'		=>	50,
            'coupon_status'	=>	$gift_status, # free,voucher
        );
        return $this->request('coupon/couponNewAdminList', $array);
    }
    
    
    
    public function coupon_presentCoupon($fuid, $phone, $coupon_id)
    {
        $array = array(
            'device_no' =>  $this->device_no,
            'phone'		=>	$phone,
            'friend_id'	=>	$fuid,
            'couponId'	=>	$coupon_id,
            'friend_name'	=>	'aaa',
        );
        return $this->request('coupon/presentCoupon', $array);
    }
}

function _log($msg)
{
#    fwrite(STDERR, $msg."\n");
	file_put_contents(__DIR__ . '/__.log.txt', date('[Y-m-d H:i:s]').$msg."\n", FILE_APPEND);
}

