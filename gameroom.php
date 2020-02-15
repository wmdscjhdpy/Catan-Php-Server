<?php
//namespace catan;
require_once './gameitem.php';
require_once './catanserver.php';

$ser=new SocketService();
$roomdata[]=null;
define("MaxPlayer",4);//定义房间最大游玩数
//catan是一局游戏的数据，对应一个房间
class gameroom{
    public $gameid;//是一个array 座位号作为索引，存放玩家ip 无玩家时值会为NULL 为玩家存在判断主要依据
    public $nicklist;//玩家名字
    public $data;//存放房间游戏信息
    public $ser;//存放服务器信息
    public function __construct($linkserver){
        $this->$data=new gamedata();
        $this->ser=$linkserver;
    }
    public function enterRoom($ip,$nickname)//登记玩家进入房间
    {
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($gameid[i]==null)break;
        }
        if($i!=MaxPlayer)//说明房间有空位
        {
            $nicklist[$i]=$nickname;
            $gameid[$i]=$ip;
            return $i;
        }else{
            return -1;//该房间玩家已满
        }
    }
    public function broadcast($msg){//房间广播数据
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($gameid[i])//存在该玩家
            {
                $this->$ser->send($this->$ser->$clients[$gameid[i]],$msg);
            }
        }
    }
}
///用户离开房间的动作
function leaveroom($ser,$ip)
{
    $roomnum=findRoomByIp($ip);
    $index=array_search($ip,$room->$gameid);//找出该ip的索引号
    $roomdata[$roomnum]->$gameid[$index]=null;//清除该id
    $i=0;
    for(;$i<MaxPlayer;$i++)//判断是不是空房间
    {
        if($roomdata[$roomnum]->$gameid[$i]!=null)break;
    }
    if($i==MaxPlayer)
    {//删除该房间
        delItemByKey($roomdata,$roomnum);
        return;
    }
    $retval['head']='leave';
    $retval['showmsg']="玩家".$roomdata[$roomnum]->$nicklist[$index]."离开了房间";
    $retval['seat']=$index;
    $roomdata[$roomnum]->broadcast($retval);
}
///输入ip，返回房间号
function findRoomByIp($ip)
{
    foreach ($roomdata as $roomnum => $room) {
        if(in_array($ip,$room->$gameid))//刚刚掉线的玩家在这间房
            return $roomnum;
    }
}
//用于处理websocket传过来的游戏数据
function dataHandle($rawmsg,$ip)
{
    $msg=json_decode($rawmsg);
    $retval=NULL;
    $bc=NULL;//当需要广播信息时在此填入值
    switch($msg['head'])
    {
        case 'enter':
            if(!isset($roomdata[(string)$msg['room']]))
            {//如果不存在该房间则创建该房间
                $roomdata[(string)$msg['room']]=new catan();
                $retval['priviliege']=1;
                $retval['showmsg'].="您现在是房主 待所有在场人准备完毕后你可以点击“开始游戏”\n";
            }
            $seat=$roomdata[(string)$msg['room']]->enterRoom($ip,$msg['nickname']);
            if($seat==-1)
            {
                $retval['showmsg'].="当前房间已满！请选择其他房间\n";
                return;
            }else{
                $bc['head']='enter';
                $bc['index']=$seat;
                $bc['showmsg'].="欢迎 ".$msg['nickname']."进入房间\n";
            }
        break;
    }
    $json=json_encode($retval);
    $ser->send($ser->$clients[$ip],$json);
    $jsonbc=json_encode($bc);
    $roomdata[findRoomByIp($ip)]->broadcast($jsonbc);
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
    foreach ($ser->$disconnected_clients as $ip => $value) {
        //删除断线信息
        delItemByKey($ser->$disconnected_clients,$ip);
        delItemByKey($ser->$clients,$ip);
        leaveroom($ser,$ip);
    }
    //处理正常情况
    foreach ($ser->$recv_data as $ip => $data) {
        dataHandle($data,$ip);
    }
}