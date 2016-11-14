<?php
//--------------------------------------教务系统--------------------------------------------------
class jw
{
    private $count = 0;
    private $nowTime = 0;
    private $PortalCookie;
    private $UidCookie;
    private $UrpCookie;
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
            $this->psw = urlencode($this->psw);
            $url = "http://portal.cnu.edu.cn/userPasswordValidate.portal?Login.Token1={$this->user}&Login.Token2={$this->psw}";
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
    
    private function bk_jw_info()
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
    
    private function getUrpCookie()
    {
        if(!empty($this->UrpCookie)) return $this->UrpCookie;
        
        $url = $this->bk_jw_info();
        if(empty($url)) return '';
        //进入URP,获取cook
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,"chouchang");
        $content = curl_exec($ch);
        // 解析COOKIE
        preg_match_all("/set\-cookie:([^\r\n]*)/i",$content, $matches);
        $this->UrpCookie = implode('',$matches[1]);
        return $this->UrpCookie;
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
        
        return mb_convert_encoding($content,'UTF-8','GBK');
    }
    
    //--------------------绩点-----------------------------------
    public function jidian()
    {
        if(preg_match('/^2\d+/',$this->user)) return array('code'=>0,'msg'=>'不支持研究生查询');
        
        //判、判断保存的账号是否正确
        $isuser = $this->is_user();
        if ($isuser['code'] === 1) $html = $this->bk_cj_all();
        else return $isuser;
        if(empty($html)) return array('code'=>0,'msg'=>'服务器错误');
        if(!preg_match('/500 Servlet Exception/',$html)){
            //解析分数页面
            preg_match_all("/<td valign=\"middle\">&nbsp;<b>([\S\s]*?)<\/b>/i", $html, $matches);//获取方案名称
            $mmmm = $matches[1][0];
            preg_match_all("/<tr class=\"odd([\S\s]*?)<\/tr>/i", $html, $matches);
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
    
    public function yjs_cj()
    {
        $bill = $this->model($this->user,$this->psw,'newjw_yjs');
                //进入URP,获取cook
        $url = "http://202.204.208.67:8082/menHu.do?bill={$bill}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_ENCODING, '');   
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
        $content = curl_exec($ch);
        //获取登陆账号密码j_acegi_login.do
        preg_match_all("/value=\"(.*?)\"/i",$content, $matches);
        $this->psw=$matches[1][2];
        
        $url = "http://202.204.208.67:8082/j_acegi_login.do?j_captcha_response=&j_username={$this->user}&j_password={$this->psw}";
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
    
    private function GetUidCookie()
    {
        if($this->UidCookie) return $this->UidCookie;
        else{
            $this->psw = urlencode($this->psw);
            $url = "http://portal.cnu.edu.cn/userPasswordValidate.portal?Login.Token1={$this->user}&Login.Token2={$this->psw}";
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
                $this->UidCookie = implode('',$matches[1]);
                return $this->UidCookie;
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
    
    function bk_kccj($kch,$kxh,$page=1)
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
        $content = mb_convert_encoding($content,'UTF-8','GBK');
        preg_match('/页号\d+\/(\d+)\|/i',$content, $matches);
        $arr = array('all_page'=>$matches[1]);
        preg_match_all('/<tr height=14 style=[\s\S]*?<\/tr>/i',$content, $matches);
        foreach($matches[0] as $k => $v){
            preg_match_all('/<td.+?\/td>/i',$v, $data);
            if(count($data[0]) < 13) continue;
            foreach($data[0] as $k2 => $v2){
                $data[0][$k2] = strip_tags($v2);
            }
            $arr['data'][] = $data[0];
        }
        preg_match('#总成绩=</td>.*?</td>#s',$content,$matches);
        $arr['score_express']['total'] = strip_tags($matches[0]);
        preg_match('#其中.*?</td>.*?</td>#s',$content,$matches);
        $arr['score_express']['class'] = strip_tags($matches[0]);
        
        preg_match('#<tr height=14.*?</tr>#s',$content,$matches);
        $arr['course_info']['term'] = strip_tags($matches[0]);//学期
        preg_match('#<tr height=16.*?</tr>#s',$content,$matches);
        $arr['course_info']['name'] = strip_tags($matches[0]);//课程名称
        preg_match('#<tr height=17.*?</tr>#s',$content,$matches);
        $arr['course_info']['info'] = strip_tags($matches[0]);//课程号 课序号 学分 任课教师
        preg_match('#应考人数.*?</tr>#s',$content,$matches);
        $arr['course_info']['person_num'] = strip_tags($matches[0]);//考试人数 平均成绩
        $json = json_encode($arr);
        file_put_contents($dir.'/'.$key,$json);
        return $arr;
    }
    
    public function bk_personal_kccj($kch,$kxh,$page=1)
    {
        $kccj = $this->bk_kccj($kch,$kxh,$page);
        foreach($kccj['data'] as $v){
            if($v[1] == $this->user){
                return array('code'=>1,'data'=>$v,'course_info'=>$kccj['course_info'],'score_express'=>$kccj['score_express'],'msg'=>'成功');
            }
        }
        if($page<$kccj[0]) return $this->bk_personal_kccj($kch,$kxh,++$page);
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

        foreach($this->cookieArr as $k => $v){
            $this->cookie .= $k.'='.$v.'; ';
        }
        
    }
}
