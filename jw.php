<?php
//--------------------------------------教务系统--------------------------------------------------
class jw
{
    const qbinfourl = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=qbinfo'; //全部及格成绩查询
    const fawcqkurl = 'http://202.204.208.75/fawcqkAction.do?oper=ori'; //培养方案完成情况
    const jxpglisturl = 'http://202.204.208.75/jxpgXsAction.do?oper=listWj&pageSize=100'; //学生评估问卷列表
    const jxpgurl = 'http://202.204.208.75/jxpgXsAction.do'; //学生评估问卷列表
    const jxpg_submiturl = 'http://202.204.208.75/jxpgXsAction.do?oper=wjpg'; //学生评估问卷列表

	
    private $count = 0;
    private $nowTime = 0;
    private $PortalCookie;
    private $UidCookie;
    private $UrpCookie;
    public $UrpUrl;
    private $PortalBill;
    private $SsoBill;
    private $user;
    private $psw;
    private $isuser;
    
    public function __construct($user,$psw){
        $this->setUser($user,$psw);
    }
    
    public function setUser($user,$psw) 
    {
        $this->user = $user;
        $this->psw = $psw;
        $this->isuser = false;
        $this->PortalCookie = false;
        $this->UidCookie = false;
        $this->UrpCookie = false;
        $this->UrpUrl = '';
        $this->nowTime = $this->getTime();
    }  
    
    public function getTime(){
        list ($msec, $sec) = explode(" ", microtime());  
        return (float)$msec + (float)$sec; 
    }  
    
    public function is_user()
    {
        if($this->isuser !== false) return $this->isuser;
        if(is_numeric($this->user) && !empty($this->psw)){
            $url = "http://portal.cnu.edu.cn/userPasswordValidate.portal?Login.Token1={$this->user}&Login.Token2=".urlencode($this->psw);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
            $content = curl_exec($ch);
            curl_close($ch);
            if(empty($content)){//校外使用VPN认证
                $postfields = array('method'=>'ldap','uname'=>$this->user,'pwd'=>$this->psw);
                $postfields = http_build_query($postfields);
                $ch = curl_init();
                curl_setopt_array($ch,array(
                    CURLOPT_URL => 'https://vpn.cnu.edu.cn/prx/000/http/localhost/login',
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postfields,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 10,
                ));
                $content = curl_exec($ch);
                if(stripos($content,'https://vpn.cnu.edu.cn/prx/000/http/localhost/welcome') !== false){
                    return $this->isuser = array('code'=>1,'msg'=>'验证成功');
                }
            }else{
                //判断是否成功
                if(preg_match("/handleLoginSuccessed/i", $content)){
                    list($header, $body) = explode("\r\n\r\n", $content);
                    preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
                    $this->UidCookie = implode('',$matches[1]);
                    return $this->isuser = array('code'=>1,'msg'=>'验证成功');
                }elseif(preg_match("/handleLoginFailure/i", $content)) {
                    return $this->isuser = array('code'=>2,'msg'=>'用户不存在或密码错误，请重新绑定');
                }
            }
            return $this->isuser = array('code'=>3,'msg'=>'未知错误，请重试发起请求');
        }else{
            return $this->isuser = array('code'=>0,'msg'=>'用户不存在或密码错误，请重新绑定');
        }
        return $this->isuser;
    }
    
    public function bk_jw_info()
    {
        //进入URP,获取cook
        $url = "http://xk.cnu.edu.cn/";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_COOKIE, $this->GetUidCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $html=curl_exec($ch);
        curl_close($ch);
        preg_match_all('/href="(.*?)"/',$html,$matches);
        if(empty($matches[1][0])) return '';
        else return $matches[1][0];
    }
    
    public function getUrpCookie($url = '')
    {
        if(empty($url) || strpos($url,$this->user) !== false){
            if(!empty($this->UrpCookie)) return $this->UrpCookie;
            elseif(!empty($_COOKIE['urpcookie'])){
                $c = json_decode($_COOKIE['urpcookie']);
                if($this->user == $c->u && $c->t + 300 > $_SERVER['REQUEST_TIME']){
                    $this->UrpCookie = $c->c;
                    return $this->UrpCookie;
                }
            }
        }
        
        $url = $url ? $url : ($this->UrpUrl ? $this->UrpUrl : $this->bk_jw_info());
        $this->UrpUrl = $url;
        if(empty($url)) return '';
        //进入URP,获取cook
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        if(curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200) return false;
        if(stripos($content,'mainFrame') === false) return '';
        // 解析COOKIE
        preg_match_all("/set\-cookie:([^\r\n]*)/i",$content, $matches);
        $this->UrpCookie = implode('; ',$matches[1]);
        setcookie('urpcookie',json_encode(array('u'=>$this->user,'c'=>$this->UrpCookie,'t'=>$_SERVER['REQUEST_TIME'])),0,'/','.cnuer.cn',false,true);
        return $this->UrpCookie;
    }
    
    public function testUrpCookie($cookie = ''){
        $cookie = $cookie ? $cookie : $this->getUrpCookie();
        if(empty($cookie)) return false;
        //进入URP,获取cook
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://202.204.208.75');
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        if(curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200) return false;
        return stripos($content,'mainFrame') !== false;
    }
    
    public function bk_bxqcj()
    {
        $url = 'http://202.204.208.75/bxqcjcxAction.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $html=curl_exec($ch);
        curl_close($ch);
        if(preg_match("/数据库忙/",mb_convert_encoding($html,'UTF-8','GBK')) && $this->count !=2){
            $this->count ++;
            return $this->bk_bxqcj();//
        }
        return mb_convert_encoding($html,'UTF-8','GBK');
    }

    //分学期获取成绩
    public function bk_cj_qbinfo()
    {
        //需要先获取这个页面，不然获取全部成绩会有很大几率页面错误
        $url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=fa';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content=curl_exec($ch);

        $url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=qbinfo&lnxndm=2';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        curl_close($ch);
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        if(preg_match("/数据库忙/",$content) && $this->count <=2){
            $this->count ++;
            return $this->bk_cj_qbinfo();//
        }
        preg_match_all('/<a name="(\d{4}-\d{4}.+?)".+?display(.+?)<\/table>/s',$content,$tab);
        $data = array();
        foreach($tab[2] as $k => $v){
            preg_match_all('/<tr class="odd".+?<\/tr>/s',$v,$td);
            foreach($td[0] as $vd){
                preg_match_all('/<td align="center">(.+?)<\/td>/s',$vd,$m);
                $key = trim($m[1][0]).'_'.trim($m[1][1]);
                $data[$key] = $tab[1][$k];
            }
        }
        return $data;
    }

    
    public function bk_cj_all()
    {
        //需要先获取这个页面，不然获取全部成绩会有很大几率页面错误
        $url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=fa';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content=curl_exec($ch);
        
        $url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=fainfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Connection: keep-alive','DNT: 1','Referer: http://xk.cnu.edu.cn/gradeLnAllAction.do?type=ln&oper=fa','Accept-Encoding: gzip,deflate','Accept-Language: zh-CN')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content=curl_exec($ch);
        curl_close($ch);
        
        $content = mb_convert_encoding($content,'UTF-8','GBK');
		
		if(preg_match('/500 Servlet Exception/',$content)){
			return array('code'=>0,'matches'=>0,'mmmm'=>0);
		}
		
		preg_match_all("/<td valign=\"middle\">&nbsp;<b>([\S\s]*?)<\/b>/i", $content, $matches);//获取方案名称
		$mmmm = $matches[1][0];
		preg_match_all("/<tr class=\"odd([\S\s]*?)<\/tr>/i", $content, $matches);
		$count = count($matches[0]);
		if(!$count){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, self::fawcqkurl);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
			curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
			curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
			$content=curl_exec($ch);
                        $content = mb_convert_encoding($content,'UTF-8','GBK');
			if(preg_match('/500 Servlet Exception/',$content)){
				return array('code'=>0,'matches'=>0,'mmmm'=>0);
			}
			preg_match('/<input type="radio" name="fajhh"[^>]+(.*?)<\/td>/is', $content, $matche);//获取方案名称
			$mmmm = $matche[1];
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, self::qbinfourl);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
			curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
			curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
			$content=curl_exec($ch);
                        $content = mb_convert_encoding($content,'UTF-8','GBK');
			if(preg_match('/500 Servlet Exception/',$content)){
				return array('code'=>0,'matches'=>0,'mmmm'=>0);
			}
			preg_match_all("/<tr class=\"odd([\S\s]*?)<\/tr>/i", $content, $matches);
			$count = count($matches[0]);
			
			if(!$count) return array('code'=>0,'matches'=>0,'mmmm'=>$mmmm);
		}
		return array('code'=>1,'matches'=>$matches,'mmmm'=>$mmmm);
    }
    
    //--------------------绩点-----------------------------------
    public function jidian()
    {
        if(preg_match('/^2\d+/',$this->user)) return array('code'=>0,'msg'=>'不支持研究生查询');
        
        //判、判断保存的账号是否正确
        $isuser = $this->is_user();
        if ($isuser['code'] === 1) $cjarr = $this->bk_cj_all();
        else return $isuser;
        if($cjarr['code'] === 0) return array('code'=>0,'msg'=>'服务器错误');
        elseif($cjarr['code'] === 1){
            //解析分数页面
            $mmmm = $cjarr['mmmm'];//获取方案名称
			$matches = $cjarr['matches'];
            $count = count($matches[0]);
            if(!$count) return array('code'=>1,'gpa'=>0,'name'=>$mmmm);
            $cache = array();
            foreach($matches[0] as $k => $v) preg_match_all('/<td align="center">(\s|.)*?<\/td>/i', $v, $cache[$k]);
            $matches = array();
            foreach($cache as $k => $v) $matches[$k]=$v[0];
            foreach($matches as $i => $v){
                foreach($v as $j => $k){
                    $s[$i][$j]=preg_replace('/<([\S\s]*?)>|<\/([\S\s]*?)>|\s|&nbsp;/i','',$k);
                }
            }
            unset($matches);
            //含有选修的绩点
            for($i=0,$jidian=0,$xuefen=0;$i<count($s);$i++)
            {
                if($s[$i][6]=='优秀')$s[$i][6]=90;
                elseif($s[$i][6]=='良好')$s[$i][6]=80;
                elseif($s[$i][6]=='中等')$s[$i][6]=70;
                elseif($s[$i][6]=='及格')$s[$i][6]=60;
                elseif($s[$i][6]<60)$s[$i][6]=50;
                $jidian+=(float)(($s[$i][6]/10-5)*(float)$s[$i][4]);
                $xuefen+=(float)$s[$i][4];
            }
                
            $jd = round($jidian/$xuefen*100)/100;
            
            return array('code'=>1,'gpa'=>$jd,'name'=>$mmmm);
        }else return array('code'=>0,'msg'=>'服务器繁忙');
    }
    
    public function get_yjsjw()
    {
        static $times = 0;
        $url = 'http://202.204.208.108/login.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);   
        curl_setopt($ch, CURLOPT_ENCODING, '');   
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        $this->listCookie($content);
        
        $url = "http://202.204.208.108/jinzhiNew_LoginAction.do?uid={$this->user}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);   
        curl_setopt($ch, CURLOPT_ENCODING, '');   
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        $this->listCookie($content);
        $info = curl_getinfo($ch);
        if(strpos($info['redirect_url'],'code.timeout.do') !== false){
            $times++;
            if($times <= 3)return $this->get_yjsjw();
            return 0;
        }
        return $info['redirect_url'];
    }
    
    public function yjs_cj()
    {
        $url = $this->get_yjsjw();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);   
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_ENCODING, '');   
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive')); 
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        //检测是否登陆成功
        if(empty($content) || preg_match("/login_error=error/i",$content))return false;
        // 解析COOKIE
        $this->listCookie($content);

        $url = 'http://202.204.208.108/cjgl.v_allcj_yjs.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive'));   
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)"); 
        $html=curl_exec($ch);
        curl_close($ch);
        $html = mb_convert_encoding($html,'UTF-8','GBK');
        
        preg_match('/gridData =(.+]);/is',$html,$s);
    
        return json_decode($s[1]);
    }
    
    private function GetUidCookie()
    {
        if($this->UidCookie) return $this->UidCookie;
        else{
            $url = "http://portal.cnu.edu.cn/userPasswordValidate.portal?Login.Token1={$this->user}&Login.Token2=".urlencode($this->psw);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
            $content = curl_exec($ch);
            curl_close($ch);
            //判断是否成功
            if(stripos($content, 'handleLoginSuccessed')){
                list($header, $body) = explode("\r\n\r\n", $content);
                preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
                $this->UidCookie = implode('; ',$matches[1]);
                return $this->UidCookie;
            }elseif(strpos($content, '用户不存在或密码错误')){
                return '';
            }
            else $this->GetUidCookie();
        }
    } 
     
    public function this_score_result()
    {
        //研究生
        if(preg_match('/^2\d+/',$this->user)) 
        {
            $yjs = $this->yjs_cj();
            if(is_array( $yjs ) && !empty($yjs))return $yjs;
            else return array('code'=>0,'msg'=>'研究生教务获取失败');
        }
        //判、判断保存的账号是否正确
            
        $re = $this->is_user();
        if($re['code'] === 1) {//已绑定，获取学号，获取分数
            $content = $this->bk_bxqcj();
            if($content == false) return array('code'=>0,'msg'=>'学校服务器认证错误，请过几分钟再重新查询');
            if(strpos($content,'数据库忙')) return array('code'=>0,'msg'=>'请重新查询');
            if(strpos($content,'本学期成绩查询列表')){//解析本学期分数页面
                $result = array();
                preg_match_all("/<tr class=\"odd\".*?>([\S\s]*?)<\/tr>/i", $content, $matches);
                foreach($matches[1] as $key => $val){
                    preg_match_all('/<td align="center">([\S\s]*?)<\/td>/i', $val, $match);
                    foreach($match[1] as $key1 => $val1){
                        $match[1][$key1] = trim($val1);
                    }
                    $result[] = $match[1];
                }
                return array('code'=>1,'msg'=>'查询成功','content'=>$result);
            }
            elseif( ($this->getTime() - $this->nowTime) >=15 ) return array('code'=>0,'msg'=>'请求超时，请重试');
            else return $this->this_score_result();
        }else return $re;
    }
            
    public function card(){
        $cookie = $this->GetUidCookie();
        //携带cookie访问信息门户,获取bill
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://uid.cnu.edu.cn/index.portal');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        $content=curl_exec($ch);
        preg_match("/line-height:25px\">([\s\S]+)<br>/i",$content,$match);
        return str_replace('&nbsp;','',strip_tags(trim($match[1])));
    }
    
    public function get_school_term()
    {
        $month = date('m');
        $year = date('Y');
        if($month<5){
            $xq = ($year-1).'-'.$year.'-1-1';
        }elseif($month>11){
            $xq = $year.'-'.($year+1).'-1-1';
        }else{
            $xq = ($year-1).'-'.$year.'-2-1';
        }
        return $xq;
    }
    
    public function bk_kccj_file_exists($kch,$kxh,$page=1){
        $xq = $this->get_school_term();
        $key = md5($xq.'_'.$kch.'_'.$kxh.'_'.$page);
        return file_exists('/tmp/scoretable/'.$xq.'/'.$key);
    }
    
    public function bk_kccj($kch,$kxh,$page=1,$xq = '')
    {
        $xq = $xq ? $xq : $this->get_school_term();
        $key = md5($xq.'_'.$kch.'_'.$kxh.'_'.$page);
        //进入URP,获取cook
        $dir = '/tmp/scoretable/'.$xq;
        if(!is_dir($dir)) mkdir($dir,0777,true);
        if(file_exists($dir.'/'.$key)) {
            $json = file_get_contents($dir.'/'.$key);
            return json_decode($json,true);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"CHOUCHANG"); 
        $content=curl_exec($ch);
        if(curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200) return array();
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        if(!preg_match('/页号\d+\/(\d+)\|/i',$content, $matches)) return array();
        $arr = array('all_page'=>$matches[1]);
        preg_match_all('/<tr height=14 style=[\s\S]*?<\/tr>/i',$content, $matches);
        foreach($matches[0] as $k => $v){
            preg_match_all('/<td.+?\/td>/i',$v, $data);
            if(count($data[0]) < 13) continue;
            foreach($data[0] as $k2 => $v2){
                $data[0][$k2] = strip_tags($v2);
            }
            if(empty($data[0][2])) continue;
            $arr['data'][] = $data[0];
        }
        if(empty($arr['data'])) return array();
        preg_match('#总成绩=</td>.*?</td>#s',$content,$matches);
        $arr['score_express']['total'] = strip_tags($matches[0]);
        preg_match('#其中.*?</td>.*?</td>#s',$content,$matches);
        $arr['score_express']['class'] = strip_tags($matches[0]);
        
        preg_match('#<tr height=14.*?</tr>#s',$content,$matches);
        $arr['course_info']['term'] = trim(strip_tags($matches[0]));//学期
        if(empty($arr['course_info']['term'])) return array();
        
        preg_match('#<tr height=16.*?</tr>#s',$content,$matches);
        $arr['course_info']['name'] = trim(strip_tags($matches[0]));//课程名称
        if(empty($arr['course_info']['name'])) return array();
        
        preg_match('#<tr height=17.*?</tr>#s',$content,$matches);
        $arr['course_info']['info'] = trim(strip_tags($matches[0]));//课程号 课序号 学分 任课教师
        if(empty($arr['course_info']['info'])) return array();
        
        preg_match('#应考人数.*?</tr>#s',$content,$matches);
        $arr['course_info']['person_num'] = trim(strip_tags($matches[0]));//考试人数 平均成绩
        if(empty($arr['course_info']['person_num'])) return array();
        if( preg_match('/0\.00$/',$arr['course_info']['person_num'])) return array();

        $json = json_encode($arr);
        return $arr;
    }
    
    public function bk_personal_kccj($kch,$kxh,$page=1)
    {
        $kccj = $this->bk_kccj($kch,$kxh,$page);
        if(empty($kccj)) return array('code'=>0,'msg'=>'未知错误，查询失败！');
        foreach($kccj['data'] as $v){
            if($v[1] == $this->user){
                return array('code'=>1,'data'=>$v,'course_info'=>$kccj['course_info'],'score_express'=>$kccj['score_express'],'msg'=>'成功');
            }
        }
        if($page<$kccj['all_page']) return $this->bk_personal_kccj($kch,$kxh,++$page);
        else return array('code'=>0,'msg'=>'失败');
    }
    
    private function listCookie($content){
        list($header, $body) = explode("\r\n\r\n", $content);
        preg_match_all("/set\-cookie:\s([^\r\n]*)/i", $header, $c);
        
        foreach($c[1] as $v){
            preg_match('/[^;]+/i',$v,$matches);
            $temp = explode('=',$matches[0],2);
            $this->cookieArr[$temp[0]] = $temp[1];
        }

        $this->cookie = '';
        foreach($this->cookieArr as $k => $v){
            $this->cookie .= $k.'='.$v.'; ';
        }
        
    }
    public function bk_jxpg_list()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::jxpglisturl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content = curl_exec($ch);
        curl_close($ch);
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        if(preg_match("/数据库忙/",$content) && $this->count !=2){
            $this->count ++;
            return $this->bk_jxpg_list();//
        }
        if(empty($content)) return array('code'=>0,'msg'=>'null content');
//        if(preg_match("/未开放/",$content)) return array('code'=>0,'msg'=>'null content');
        preg_match('/class="titleTop2">(.*?)<\/table>/is', $content, $match);
        preg_match_all("/<tr class=\"odd\".*?>(.*?)<\/tr>/is", $match[1], $matches);
        $data = array();
        foreach($matches[1] as $k => $v){
            $temp = array();
            preg_match_all('/<td align="center">(.*?)<\/td>/is', $v, $m);
            $temp = $m[1];
            preg_match('/name="(.*?)"/', $temp[4], $m2);
            $temp[4] = $m2[1];
            $data[] = $temp;
        }

        return array('code'=>1,'msg'=>'success','data'=>$data);
    }
    
    public function bk_jxpg_wj($wjbm,$bpr,$bprm,$wjmc,$pgnrm,$pgnr,$oper = 'wjResultShow')
    {
        $data = array(
            'wjbm'=>$wjbm,
            'bpr'=>$bpr,
            'pgnr'=>$pgnr,
            'oper'=>$oper,
            'wjmc'=>$wjmc,
            'bprm'=>$bprm,
            'pgnrm'=>$pgnrm,
            'pageSize'=>20,
            'page'=>1,
            'pageNo'=>'',
            'currentPage'=>1
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::jxpglisturl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content = curl_exec($ch);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::jxpgurl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content = curl_exec($ch);
        curl_close($ch);
        $content = str_replace("\t",'',$content);
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        if(preg_match("/数据库忙/",$content) && $this->count <=2){
            $this->count ++;
            return $this->bk_jxpg_list($wjbm,$bpr,$bprm,$wjmc,$pgnrm,$pgnr,$oper);
        }
        preg_match_all('/<table align="left" border="0" cellspacing="0".+?<\/table>/is', $content, $match);

        return array('code'=>1,'data'=>array('wjbm'=>$wjbm,'bpr'=>$bpr,'pgnr'=>$pgnr,'table'=>$match[0]));
    }
    
    public function bk_jxpg_wj_submit($data)
    {
        $data['zgpj'] = mb_convert_encoding($data['zgpj'],'GBK','UTF-8');
        $data['zgpj1'] = mb_convert_encoding($data['zgpj1'],'GBK','UTF-8');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::jxpg_submiturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
        curl_setopt($ch, CURLOPT_COOKIE, $this->getUrpCookie());
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content = curl_exec($ch);
        curl_close($ch);
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        preg_match('/alert\("(\.+?)"\);/',$content,$m);
        if(preg_match("/数据库忙/",$content)){
            return array('code'=>0,'msg'=>'数据库忙！');
        }

        return array('code'=>1,'msg'=>'提交成功！ '.$m[1]);
    }
}
