<?php
//namespace catan;
require_once './gameitem.php';
require_once './catanserver.php';

const MaxPlayer=4;//定义房间最大游玩数
const MinPlayer=1;//定义这种游戏最少玩家数 如果和maxplayer一样则必须满房间的人才可以开局 测试用改成了1
//catan是一局游戏的数据，对应一个房间
class gameroom{
    public $ser;//存放服务器信息
    public $isplaying=0;//表示该房间是否在游玩
    public $gameid;//是一个array 座位号作为索引，存放玩家ip 无玩家时值会为NULL 为玩家存在判断主要依据
    public $nicklist;//玩家名字
    public $gameready;//array 座位号索引 代表玩家准备信息
    public $hostindex;//房主索引
    public $data;//存放房间游戏信息
    public function __construct($linkserver){
        $this->data=new gamedata($this);
        $this->ser=$linkserver;
    }
    public function enterRoom($ip,$nickname)//登记玩家进入房间
    {
        $i=0;
        if($this->isplaying==1)
        {
            return -2;//表示当前桌已经在游玩
        }
        for(;$i<MaxPlayer;$i++)
        {
            if(!isset($this->gameid[$i]))break;
        }
        if($i!=MaxPlayer)//说明房间有空位
        {
            if(isset($this->nicklist))
            foreach ($this->nicklist as $index => $name) {
                if($name==$nickname)
                {
                    $nickname.="(副本)";
                }
            }
            $this->nicklist[$i]=$nickname;
            $this->gameid[$i]=$ip;
            $this->gameready[$i]=0;
            return $i;
        }else{
            return -1;//该房间玩家已满
        }
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
            if(isset($this->gameid[$i]))//存在该玩家
            {
                $this->sendDataByIndex($i,$msg);
            }
        }
    }
    public function broadcastExt($msg,$index)//对除当前index外的人广播
    {
        $i=0;
        for(;$i<MaxPlayer;$i++)
        {
            if($i==$index)continue;
            if($this->gameid[$i])//存在该玩家
            {
                $this->sendDataByIndex($i,$msg);
            }
        }
    }
    public function sendOtherUserInfo($index)//向该用户发送房间其他人的信息
    {
        $ret['head']='info';
        for(;$i<MaxPlayer;$i++)
        {
            if(isset($this->gameid[$i]) && $i!=$index)
            {
                $ret[$i]['nickname']=$this->nicklist[$i];
                $ret[$i]['readystats']=$this->gameready[$i];
            }
        }
        $ret['priviliege']=$this->hostindex;
    }
}

//根据键值删除列表元素 该函数应该是通用函数才对的
function delItemByKey(&$arr, $key){ 
    if(!array_key_exists($key, $arr)){
        return ; 
    } 
    $keys = array_keys($arr); 
    $index = array_search($key, $keys); 
    if($index !== FALSE){ 
        $del=array_splice($arr, $index, 1); 
        var_dump($del);
    }
}