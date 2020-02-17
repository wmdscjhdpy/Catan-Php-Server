<?php
//namespace catan;
require_once './gameitem.php';
require_once './catanserver.php';

$ser=new SocketService();
$roomdata=null;
const MaxPlayer=4;//定义房间最大游玩数
//catan是一局游戏的数据，对应一个房间
class gameroom{
    public $gameid;//是一个array 座位号作为索引，存放玩家ip 无玩家时值会为NULL 为玩家存在判断主要依据
    public $nicklist;//玩家名字
    public $gameready;//array 座位号索引 代表玩家准备信息
    public $data;//存放房间游戏信息
    public $ser;//存放服务器信息
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
        if($i==MaxPlayer)
        {//删除该房间
            delItemByKey($roomdata,$roomnum);
            return;
        }
        $retval['head']='leave';
        $retval['showmsg']="玩家".$roomdata[$roomnum]->nicklist[$index]."离开了房间";
        $retval['seat']=$index;
        $roomdata[$roomnum]->broadcast($retval);
    }
    public function broadcast($msg){//房间广播数据
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($gameid[$i])//存在该玩家
            {
                $this->ser->send($this->ser->clients[$gameid[i]],$msg);
            }
        }
    }
    public function broadcastExt($msg,$ip)//对除当前ip地址外的人广播
    {
        $ext=getInfoFromIp($ip)['roomnum'];
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($i==$ext)continue;
            if($this->gameid[$i])//存在该玩家
            {
                $this->ser->send($this->ser->clients[$this->gameid[$i]],$msg);
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
        case 'enter':
            $proessed=1;
            $retval['head']='enter';
            if(!isset($roomdata[(string)$msg['room']]))
            {//如果不存在该房间则创建该房间
                $roomdata[$msg['room']]=new gameroom($ser);
                $retval['priviliege']=1;
                $retval['showmsg']="您现在是房主 待所有在场人准备完毕后你可以点击“开始游戏”\n";
            }
            //还应该有房间其他人的信息
            $seat=$roomdata[$msg['room']]->enterRoom($ip,$msg['nickname']);
            if($seat==-1)
            {
                $retval['head']='error';
                $retval['showmsg']="当前房间已满！请选择其他房间\n";
                return;
            }else{
                $ext['head']='enter';
                $ext['index']=$seat;
                $ext['nickname']=$msg['nickname'];
                $ext['showmsg']="欢迎 ".$msg['nickname']."进入房间\n";
            }
        break;
        case 'ready':
            $proessed=1;
            $index=getInfoFromIp($ip)['index'];
            $this->gameready[]=$msg['flag'];
            $bc['head']='ready';
            $bc['index']=$index;
            $bc['flag']=$msg['flag'];
        break;
        case 'leave':
            $proessed=1;
            gameroom::leaveroom($ip);
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
        $json=json_encode($retval,JSON_UNESCAPED_UNICODE);
        $ser->send($ser->clients[$ip],$json);
        var_dump($json);
    }
    if($bc)
    {
        $jsonbc=json_encode($bc,JSON_UNESCAPED_UNICODE);
        $roomdata[getInfoFromIp($ip)['roomnum']]->broadcast($jsonbc);
    }
    if($ext)
    {
        $jsonext=json_encode($ext,JSON_UNESCAPED_UNICODE);
        $roomdata[getInfoFromIp($ip)['roomnum']]->broadcastExt($jsonext,$ip);
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