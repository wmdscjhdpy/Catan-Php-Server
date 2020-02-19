<?php
//namespace catan;
require_once './gameitem.php';
require_once './catanserver.php';

$ser=new SocketService();
$roomdata=null;
const MaxPlayer=4;//定义房间最大游玩数
const MinPlayer=2;//定义这种游戏最少玩家数 如果和maxplayer一样则必须满房间的人才可以开局
//catan是一局游戏的数据，对应一个房间
class gameroom{
    public static $ser;//存放服务器信息
    public $gameid;//是一个array 座位号作为索引，存放玩家ip 无玩家时值会为NULL 为玩家存在判断主要依据
    public $nicklist;//玩家名字
    public $gameready;//array 座位号索引 代表玩家准备信息
    public $hostindex;//房主索引
    public $data;//存放房间游戏信息
    public function __construct($linkserver){
        $this->data=new gamedata();
        $this->ser=$linkserver;
    }
    public function enterRoom($ip,$nickname)//登记玩家进入房间
    {
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($this->gameid[$i]==null)break;
        }
        if($i!=MaxPlayer)//说明房间有空位
        {
            $this->nicklist[$i]=$nickname;
            $this->gameid[$i]=$ip;
            $this->gameready[$i]=0;
            return $i;
        }else{
            return -1;//该房间玩家已满
        }
    }
    ///用户离开房间的动作
    static public function leaveroom($ip)
    {
        global $roomdata;
        $roomnum=getInfoFromIp($ip)['roomnum'];
        $index=@array_search($ip,$roomdata[$roomnum]->gameid);//找出该ip的索引号
        $roomdata[$roomnum]->gameid[$index]=null;//清除该id
        $i=0;
        for(;$i<MaxPlayer;$i++)//判断是不是空房间
        {
            if($roomdata[$roomnum]->gameid[$i]!=null)break;
        }
        //此时i=一个存活的人的index或Maxplayer
        if($i==MaxPlayer)
        {//删除该房间
            delItemByKey($roomdata,$roomnum);
            return;
        }else if($roomdata[$roomnum]->hostindex==$index)
        {//转移房主权利
            $roomdata[$roomnum]->hostindex=$i;
            $hostmsg['head']='priviliege';
            $hostmsg['showmsg']="【系统提示】由于前房主离开，你现在是新的房主\n";
            $roomdata[$roomnum]->sendDataByIndex($i,$hostmsg);
        }
        $retval['head']='leave';
        $retval['showmsg']="【系统提示】玩家".$roomdata[$roomnum]->nicklist[$index]."离开了房间\n";
        $retval['index']=$index;
        $roomdata[$roomnum]->broadcast($retval);
    }
    public function sendDataByIndex($index,$msg)//通过index发送数据，内含jsonencode
    {
        $json=json_encode($msg,JSON_UNESCAPED_UNICODE);
        $this->ser->send($this->ser->clients[$this->gameid[$index]],$json);
    }
    public function broadcast($msg){//房间广播数据
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($this->gameid[$i])//存在该玩家
            {
                $this->sendDataByIndex($i,$msg);
            }
        }
    }
    public function broadcastExt($msg,$ip)//对除当前ip地址外的人广播
    {
        $ext=getInfoFromIp($ip)['index'];
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($i==$ext)continue;
            if($this->gameid[$i])//存在该玩家
            {
                $this->sendDataByIndex($i,$msg);
            }
        }
    }
    public function sendOtherUserInfo($ip)//向该用户发送房间其他人的信息
    {
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($this->gameid[$i] && $this->gameid[$i]!==$ip)
            {
                $retval['head']='enter';
                $retval['index']=$i;
                $retval['nickname']=$this->nicklist[$i];
                $this->sendDataByIndex(getInfoFromIp($ip)['index'],$retval);
                if($this->gameready[$i]==1)//还需要发准备状态包
                {
                    $ret2['head']='ready';
                    $ret2['index']=$i;
                    $ret2['flag']=1;
                    $this->sendDataByIndex(getInfoFromIp($ip)['index'],$ret2);
                }
            }
        }
    }
}

///输入ip，返回房间号
function getInfoFromIp($ip)
{
    global $roomdata;
    if(is_array($roomdata))
    foreach ($roomdata as $roomnum => $room) {
        $i=array_search($ip,$room->gameid);
        if($i!==false)//刚刚掉线的玩家在这间房
        {
            $ret['roomnum']=$roomnum;
            $ret['index']=$i;
            return $ret;
        }
    }
    echo "error: no ip in the room\n";
}
//用于处理websocket传过来的游戏数据
function dataHandle($rawmsg,$ip)
{
    global $ser;
    global $roomdata;
    $msg=json_decode($rawmsg,true);//第二个参数设为true能把msg变成一个数组
    //var_dump($msg);
    $retval=NULL;//对发信息过来的人返回的具体信息
    $bc=NULL;//当需要广播信息时在此填入值
    $ext=NULL;//当需要对除发信息者外的人广播信息时用的数据包
    $proessed=0;//判断msg是否被处理
    //房间管理用的switch
    switch($msg['head'])
    {
        case 'enter'://进入房间的事件
            $proessed=1;
            if(!isset($roomdata[(string)$msg['room']]))
            {//如果不存在该房间则创建该房间
                $roomdata[$msg['room']]=new gameroom($ser);
                $retval['head']='priviliege';
                $roomdata[$msg['room']]->hostindex=0;//房主是第一位
                $retval['showmsg']="【系统提示】您现在是房主 待所有在场人准备完毕后你可以点击“开始游戏”\n";//
            }
            //var_dump($roomdata[$msg['room']]);
            $seat=$roomdata[$msg['room']]->enterRoom($ip,$msg['nickname']);
            if($seat==-1)
            {
                $retval['head']='error';
                $retval['showmsg']="【系统提示】当前房间已满！请选择其他房间\n";
            }else{
                //如果房间有其他人则向该用户给出其他用户的信息
                $roomdata[$msg['room']]->sendOtherUserInfo($ip);
                $bc['head']='enter';
                $bc['index']=$seat;
                $bc['nickname']=$msg['nickname'];
                $bc['showmsg']="【系统提示】欢迎".$msg['nickname']."进入房间\n";
            }
        break;
        case 'ready':
            $proessed=1;
            $info=getInfoFromIp($ip);
            $roomdata[$info['roomnum']]->gameready[$info['index']]=$msg['flag'];
            $bc['head']='ready';
            $bc['index']=$info['index'];
            $bc['flag']=$msg['flag'];
        break;
        case 'leave':
            //注意房主权利转移
            $proessed=1;
            gameroom::leaveroom($ip);
            $retval['head']='leavesuccess';
        break;
        case 'gameon':
            $proessed=1;
            $bc=$this->data->startgame();//此处还没实现
            //调用游戏初始化引擎
        break;
    }
    //信息分发
    if($retval)
    {
        //因为不在房间内用的原生发送 所以需要转码
        $json=json_encode($retval,JSON_UNESCAPED_UNICODE);
        $ser->send($ser->clients[$ip],$json);
    }
    if($bc)
    {
        $roomdata[getInfoFromIp($ip)['roomnum']]->broadcast($bc);
    }
    if($ext)
    {
        $roomdata[getInfoFromIp($ip)['roomnum']]->broadcastExt($ext,$ip);
    }
}
//根据键值删除列表元素
function delItemByKey(&$arr, $key){ 
    if(!array_key_exists($key, $arr)){
        return $arr; 
    } 
    $keys = array_keys($arr); 
    $index = array_search($key, $keys); 
    if($index !== FALSE){ 
        array_splice($arr, $index, 1); 
    } 
    return $arr; 
}

while (true) {
    $ser->runOnce();
    //处理断线情况
    if(is_array($ser->disconnected_clients))
    foreach ($ser->disconnected_clients as $ip => $value) {
        //删除断线信息
        gameroom::leaveroom($ip);
        delItemByKey($ser->disconnected_clients,$ip);
        delItemByKey($ser->clients,$ip);
    }
    //处理正常情况
    if(is_array($ser->recv_data))
    foreach ($ser->recv_data as $ip => $data) {
        dataHandle($data,$ip);
    }
}