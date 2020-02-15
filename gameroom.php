<?php
//namespace catan;
require_once './gameitem.php';
require_once './catanserver.php';
$roomdata=array();
define("MaxPlayer",4);//定义房间最大游玩数
//catan是一局游戏的数据，对应一个房间
class gameroom{
    public $gamerindex=0;//房间人数，默认为0，
    public $gameid;//是一个array 座位号作为索引，存放玩家sock
    public $nicklist;
    public $data;
    public function __construct(){
        $data=new gamedata();
    }
    public function enterRoom($sock,$nickname)//登记玩家进入房间
    {
        if($gamerindex<MaxPlayer-1)
        {
            $nicklist[$gamerindex]=$nickname;
            $gameid[$gamerindex++]=$sock;
            return 1;
        }else{
            return -1;//该房间玩家已满
        }
    }

}
//用于处理websocket传过来的游戏数据
function dataHandle($rawmsg,$usersock)
{
    $msg=json_decode($rawmsg);
    $retval=NULL;
    $broadcast=NULL;//当需要广播信息时在此填入值
    switch($msg['head'])
    {
        case 'enter':
        {   //该数据包带有 room nickname 
            //返回数据包带有showmsg priviliege
            if(!isset($roomdata[$msg['room']]))
            {//如果不存在该房间则创建该房间
                $roomdata[$msg['room']]=new catan();
                $retval['priviliege']=1;
            }
            if($roomdata[$msg['room']]->enterRoom($usersock,$msg['nickname'])==-1)
            {
                $retval['showmsg']="当前房间已满！请选择其他房间\n";
                return;
            }else{
                $broadcast['showmsg']="欢迎 ".$msg['nickname']."进入房间\n";
                $retval['showmsg']="您现在是房主 待所有在场人准备完毕后你可以点击“开始游戏”";
            }
        }
    }
}

//根据键值删除列表元素
function delItemByKey($arr, $key){ 
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