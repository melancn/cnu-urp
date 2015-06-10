<?
//--------------------------------------教务系统--------------------------------------------------
class jw
{
    private $count = 0;
    private $PortalCookie;
    private $UidCookie;
    private $PortalBill;
    private $SsoBill;
    
	public function getTime ()  
    {
        list ($msec, $sec) = explode(" ", microtime());  
        return (float)$msec + (float)$sec; 
    }  
	
    public function is_user($user,$psw)
    {
        if(is_numeric($user) && !empty($psw)){
            $psw = urlencode($psw);
            $url = "http://uid.cnu.edu.cn/userPasswordValidate.portal?Login.Token1=$user&Login.Token2=$psw";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
            $content = curl_exec($ch);
            curl_close($ch);
            //判断是否成功
            if(preg_match("/handleLoginSuccessed/i", $content)){
                list($header, $body) = explode("\r\n\r\n", $content);
                preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
                $this->UidCookie = trim($matches[1][0]);
				return 1;
            }
            elseif(preg_match("/handleLoginFailure/i", $content)) return 2;
            else return 3;
        }
        else return 0;
    }
    
    private function bk_jw_info($user,$psw)
    {
        //进入URP,获取cook
        $url = "http://xk.cnu.edu.cn/zdtj.jsp";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        //curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_COOKIE, $this->GetUidCookie($user,$psw));
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $html=curl_exec($ch);
        curl_close($ch);
		preg_match_all('/value="(.*?)"/',$html,$matches);
		return($matches[1]);
    }
    
    public function bk_bxqcj($user,$psw)
    {
        $info = $this->bk_jw_info($user,$psw);
        //进入URP,获取cook
        $url = "http://202.204.208.75/loginAction.do?zjh=$user&mm={$info[2]}&ldap=auth";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        if(empty($content) && $this->count !=2)
        {
            $this->count ++;
            $this->bk_bxqcj($user,$psw);//
        }
        else if(empty($content))return false;
        // 解析COOKIE
        preg_match("/set\-cookie:([^\r\n]*)/i",$content, $matches);
        $cookie = $matches[1];

        $url = 'http://202.204.208.75/bxqcjcxAction.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $html=curl_exec($ch);
        curl_close($ch);
        return mb_convert_encoding($html,'UTF-8','GBK');
    }
    
    public function bk_cj_all($user,$psw)
    {
        $info = $this->bk_jw_info($user,$psw);
        //进入URP,获取cook
        $url = "http://202.204.208.75/loginAction.do?zjh=$user&mm={$info[2]}&ldap=auth";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        // 解析COOKIE
        preg_match("/set\-cookie:([^\r\n]*)/i",$content, $matches);
        $cookie = $matches[1];
		//需要先获取这个页面，不然获取全部成绩会有很大几率页面错误
		$url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=fa';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content=curl_exec($ch);
        
        $url = 'http://202.204.208.75/gradeLnAllAction.do?type=ln&oper=fainfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Connection: keep-alive','DNT: 1','Referer: http://xk.cnu.edu.cn/gradeLnAllAction.do?type=ln&oper=fa','Accept-Encoding: gzip,deflate','Accept-Language: zh-CN')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang"); 
        $content=curl_exec($ch);
        curl_close($ch);
        
        return mb_convert_encoding($content,'UTF-8','GBK');
    }
    
    //--------------------绩点-----------------------------------
    public function jidian($user,$psw)
	{
        if(preg_match('/^2\d+/',$user))
            return false;
        
        //判、判断保存的账号是否正确
        if ($this->is_user($user,$psw) === 1){         
            $html = $this->bk_cj_all($user,$psw);
        }
        else return false;
        if(empty($html)) return '';
		if(!preg_match('/500 Servlet Exception/',$html))
		{
			//解析分数页面
            preg_match_all("/<td valign=\"middle\">&nbsp;<b>([\S\s]*?)<\/b>/i", $html, $matches);//获取方案名称
            $mmmm = $matches[1][0];
			preg_match_all("/<tr class=\"([\S\s]*?)<\/tr>/i", $html, $matches);
            $count = count($matches[0]);
			for ($i=0;$i<$count;$i++)
			{
				preg_match_all('/<td align="center">(\s|.)*?<\/td>/i', $matches[0][$i], $cache[$i]);
			} 
            $matches = null;
            $count = count($cache);
			for ($i=0;$i<$count;$i++)$matches[$i]=$cache[$i][0];
            $count = count($matches);
			for ($i=0;$i<$count;$i++)
			{
				for ($j=0;$j<7;$j++)
				{
					$s[$i][$j]=preg_replace('/<([\S\s]*?)>|<\/([\S\s]*?)>|\s|&nbsp;/i','',$matches[$i][$j]);
				}
			}
		unset($matches);
		//含有选修的绩点
		for($i=0,$jidian=0,$xuefen=0;$i<count($s);$i++)
		{
            if($s[$i][6]=='优秀')$s[$i][6]=90;
		    if($s[$i][6]=='良好')$s[$i][6]=80;
            if($s[$i][6]=='中等')$s[$i][6]=70;
            if($s[$i][6]=='及格')$s[$i][6]=60;
		    if($s[$i][6]<60)$s[$i][4]=0;
		    $jidian+=(float)(($s[$i][6]/10-5)*(float)$s[$i][4]);
		    $xuefen+=(float)$s[$i][4];
		}
            
		$jd[0] = floor($jidian/$xuefen*100)/100;
		
		$jd[1] = $mmmm;
        
        return $jd;
        }
        else return false;
	}
    
    public function yjs_cj($user,$psw)
    {
        $bill = $this->model($user,$psw,'newjw_yjs');
                //进入URP,获取cook
        $url = "http://202.204.208.67:8082/menHu.do?bill=$bill";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
		curl_setopt($ch, CURLOPT_ENCODING, '');   
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        //获取登陆账号密码j_acegi_login.do
		preg_match_all("/value=\"(.*?)\"/i",$content, $matches);
		$psw=$matches[1][2];
		
		$url = "http://202.204.208.67:8082/j_acegi_login.do?j_captcha_response=&j_username=$user&j_password=$psw";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_TIMEOUT,2);
		curl_setopt($ch, CURLOPT_ENCODING, '');   
		curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive')); 
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        //检测是否登陆成功
        if(empty($content) || preg_match("/login_error=error/i",$content))return false;
        // 解析COOKIE
        preg_match("/set\-cookie:([^\r\n]*)/i",$content, $matches);
        $cookie = $matches[1];

        $url = 'http://202.204.208.67:8082/cjgl.v_allcj_yjs.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Language: zh-cn','Connection: Keep-Alive'));   
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)"); 
        $html=curl_exec($ch);
        curl_close($ch);
        $html = mb_convert_encoding($html,'UTF-8','GBK');
		
		preg_match('/gridData =[\s\S]*?<\/script>/',$html,$s);
		$s=preg_replace('/<\/script>|\];|\n|\s/','',$s);
		$s=preg_replace('/gridData=\[|,"<ahref[\s\S]*?<\/a>"|"/','',$s);
		preg_match_all('/\[[\s\S]*?\]/',$s[0],$matchs);
		$s=array();
		for($i=0;$i<count($matchs[0]);$i++)
		{
			$matchs[0][$i]=preg_replace('/\[|\]/',"",$matchs[0][$i]);
			$matchs[0][$i]=explode(',',$matchs[0][$i]);
			$s[$i]=$matchs[0][$i];
		}
	
		return $s;
    }
    
    private function GetUidCookie($user,$psw)
    {
        if($this->UidCookie) return $this->UidCookie;
		else{
			$psw = urlencode($psw);
            $url = "http://uid.cnu.edu.cn/userPasswordValidate.portal?Login.Token1=$user&Login.Token2=$psw";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
            $content = curl_exec($ch);
            curl_close($ch);
            //判断是否成功
            if(preg_match("/handleLoginSuccessed/i", $content)){
                list($header, $body) = explode("\r\n\r\n", $content);
                preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
				$this->UidCookie = trim($matches[1][0]);
                return $this->UidCookie;
			}
			else $this->GetUidCookie($user,$psw);
		}
    }
    private function GetPortalCookie($user,$psw)
    {
        $info = $this->bk_jw_info($user,$psw);
        $url = "http://202.204.208.141/Login?userName=$user&password={$info[2]}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Maxthon/4.1 Chrome/30.0.1599.101 Safari/537.36");
        $content = curl_exec($ch);
        // 解析COOKIE
        list($header, $body) = explode("\r\n\r\n", $content);
        preg_match_all("/set\-cookie:([^\r\n]*)/i", $header, $matches);
        $cookie = explode("Set-Cookie:", $matches[0][1]);
        if($cookie[1] == null) $this->GetPortalCookie($user,$psw);
        $this->PortalCookie = $cookie[1];
    }
    
    private function GetPortalBill($user,$psw)
    {
        if(is_string($this->PortalCookie))$cookie = $this->PortalCookie;
        else 
        {
            $this->GetPortalCookie($user,$psw);
            $this->GetPortalBill($user,$psw);
        }

        //携带cookie访问信息门户,获取bill
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://202.204.208.141/mhpd/xnyy/xnyy.jsp');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Maxthon/4.1 Chrome/30.0.1599.101 Safari/537.36"); 
        $content=curl_exec($ch);
        preg_match("/\/test\.jsp\?bill=(\w+)&/i",$content,$matches);
        
        if(empty($matches))$this->PortalBill = null;
        else $this->PortalBill = $matches[1];
        if($this->PortalBill == null && $this->count != 2) 
        {
            $this->count ++;
            $this->GetPortalBill($user,$psw);
        }
        
    }
    
    private function model($user,$psw,$appid)
    {
        $this->GetPortalBill($user,$psw);
        if ($this->PortalBill == NULL && $this->count ==2) return false;
        else $bill = $this->PortalBill;
        
        //获取漫游票据
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://202.204.208.141/mhpd/xnyy/test.jsp?bill=$bill&userip=null&userid=$user&appid=$appid");
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Maxthon/4.1 Chrome/30.0.1599.101 Safari/537.36"); 
        $html=curl_exec($ch);
        curl_close($ch);
        preg_match("/bill=(\w+)\"/i",$html,$matches);
        if($matches[1] == 'null' && $this->count !=4)
        {
            $this->count ++;
            $this->model($user,$appid);//
        }
        else if(empty($matches) || $matches[1] == 'null' )
        {
            return false;
        }
        else return $matches[1];
    } 
    
                      
     
    public function this_score_result($user,$psw)
    {
		//研究生
		if(preg_match('/^2\d+/',$user)) 
		{
			$yjs = $this->yjs_cj($user,$psw);
			if(is_array( $yjs ) && !empty($yjs))return $yjs;
			else return '研究生教务获取失败';
		}
		//判、判断保存的账号是否正确
        	
        $re = $this->is_user($user,$psw);
		if ($re === 0) return '学号错误，请联系我们';
		elseif ($re === 1)
		{//已绑定，获取学号，获取分数
			$html = $this->bk_bxqcj($user,$psw);
			if($html == false) return '学校服务器认证错误，请过几分钟再重新查询';
			if(preg_match('/数据库/',$html)) return '请重新查询';
			if(preg_match('/本学期成绩查询列表/',$html))
			{
			//解析本学期分数页面
				preg_match_all("/<tr class=\"([\S\s]*?)<\/tr>/i", $html, $matches);
				for ($i=0;$i<count($matches[0]);$i++)
					preg_match_all('/<td align="center">(\s|.)*?<\/td>/i', $matches[0][$i], $cache[$i]);
				for ($i=0;$i<count($cache);$i++)
					$matches[$i]=$cache[$i][0];
				for ($i=0;$i<count($matches);$i++)
					for ($j=0;$j<7;$j++)
						$s[$i][$j]=preg_replace('/<([\S\s]*?)>|<\/([\S\s]*?)>|\s/i','',$matches[$i][$j]);
				return $s;
			}
			elseif( ($this->getTime() - $this->nowTime) >=4.5 )
				return '请求超时，请重试';
			else $this->this_score_result($user,$psw);
		}
		elseif ($re === 2) return '用户不存在或密码错误，请重新绑定';
		elseif ($re === 3) return '未知错误，请重试发起请求';
        else return '请重试';
    }
            
	public function card($user,$psw){
		$cookie = $this->GetUidCookie($user,$psw);
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
    
}
