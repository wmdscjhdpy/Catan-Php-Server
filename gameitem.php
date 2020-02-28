<?php
require_once './gameroom.php';
//namespace catan;
//定义0-3为 蓝绿红黄
const colornum=array('blue','green','red','yellow');
const colornumzh=array('蓝色','绿色','红色','黄色');
//资源对应数字          0       1       2       3       4       5          6        7              8            9
const kindnum=array('forest','iron','grass','wheat','stone','solders','harvest','monopoly','roadbuilding','winpoint');
//以下是游戏数据库
class gamedata{
    public $room;//存放房间信息
    public $publicdata;//玩家的公有数据
    public $pridata;//玩家的私有数据
    /*
    骰子事件指引对象，键为骰子数，值仍为一个array
    二级键代表玩家编号，值仍为一个对象
    子变量有5个，分别为iron,wheat,forest,stone,grass 其值代表能收获多少。
    */
    public $rollhandle; 
    public $startrolldata=array();//决定谁先放房子的骰子数据

    public function __construct($room){
        $this->room=$room;
    }
    public function calcNodeId($Pos){
        $nodeid;
        $X=($Pos[0]['x']+$Pos[1]['x']+$Pos[2]['x'])/3;
        $Y=($Pos[0]['y']+$Pos[1]['y']+$Pos[2]['y'])/3;
        $nodeid=''.$X.$Y;
        return $nodeid;
    }
    public function getNextPlayer($index){//将当前回合转移给下一个玩家 不输入参数则index为当前玩家
        if($index==null)
        {
            $index=$this->publicdata['status']['turn'];
        }
        do {
            $index++;
            if($index==MaxPlayer)$index-=MaxPlayer;
        } while ($this->publicdata['player'][$index]['status']==null);
        return $index;
    }
    public function calcRoadId($Pos){//给每一个道路一个唯一编号
        $roadid;
        $X=($Pos[0]['x']+$Pos[1]['x'])/2;
        $Y=($Pos[0]['y']+$Pos[1]['y'])/2;
        $roadid=''.$X.$Y;
        return $roadid;
    }
    
    public function getNearPosition($P,$deg){//获取临近六边形坐标
        $newP['x']=$P['x'];
        $newP['y']=$P['y'];
        while($deg<0)$deg+=360;
        switch($deg%360)
        {
            case 0:
                $newP['x']=$P['x']+1;
                if($newP['x']==0 && (abs($P['y']%2)))$newP['x']+=1;
            break;
            case 180:
                $newP['x']=$P['x']-1;
                if($newP['x']==0 && (abs($P['y']%2)))$newP['x']-=1;
            break;
            case 60:
                $newP['y']+=1;
                if((!(abs($P['y']%2)))&& ($P['x']>=0))$newP['x']+=1;
                if((abs($P['y']%2))&& ($P['x']<0))$newP['x']+=1;
            break;
            case 120:
                $newP['y']+=1;
                if((abs($P['y']%2))&& ($P['x']>0))$newP['x']-=1;
                if((!(abs($P['y']%2)))&& ($P['x']<=0))$newP['x']-=1;
            break;
            case 240:
                $newP['y']-=1;
                if((abs($P['y']%2))&& ($P['x']>0))$newP['x']-=1;
                if((!(abs($P['y']%2)))&& ($P['x']<=0))$newP['x']-=1;
            break;
            case 300:
                $newP['y']-=1;
                if((!(abs($P['y']%2)))&& ($P['x']>=0))$newP['x']+=1;
                if((abs($P['y']%2))&& ($P['x']<0))$newP['x']+=1;
            break;
        }
        return $newP;
    }
    
    public function getAllNodeNearby($P)//获取和这个六边形相邻的所有节点
    {
        $P0=$P;
        $ret['val']=array();
        $ret['chk']=array();
        for( $i=0;$i<360;$i+=60)
        {
             $P1=$this->getNearPosition($P,$i);
             $P2=$this->getNearPosition($P,$i+60);
            array_push($ret['val'],[$P0,$P1,$P2]);
            array_push($ret['chk'],$this->calcNodeId([$P0,$P1,$P2]));
        }
        return $ret;
    }
    
    public function getAllRoadNearBy($P)//获取该六边形临近的所有节点
    {
        $P0=$P;
        $ret['val']=array();
        $ret['chk']=array();
        for( $i=0;$i<360;$i+=60)
        {
             $P1=$this->getNearPosition($P,$i);
            array_push($ret['val'],[$P0,$P1]);
            array_push($ret['chk'],$this->calcRoadId([$P0,$P1]));
        }
        return $ret;
    }
    //因为思路经过多次改版，因此该函数有很大的提升空间
    public function startgame($nowplayer)//初始化游戏地图
    {
        $ret;
        $it['x']=0;
        $it['y']=0;
        $rawhexagon=null;
        $rawhexagon[0]=$it;
        //大循环
        for($i=1;$i<=2;$i++)
        {
            $it=$this->getNearPosition($it,300);
    
            array_push($rawhexagon,$it);
            for($deg=0;$deg<360;$deg+=60)
            {
                $k=0;
                if($deg==0)$k+=1;//每一次第一次的时候由于突出了一格，所以0°的操作少一个
                while($k<$i)
                {
                    $it=$this->getNearPosition($it,$deg);
                    array_push($rawhexagon,$it);
                    $k++;
                }
            }
        }
        $rawnodelist=array();
        $nodechklist=array();
        //开始生成节点
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $tmpnodelist=$this->getAllNodeNearby($rawhexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmpnodelist['chk'][$l],$nodechklist))//不存在这个node
                {
                    array_push($nodechklist,$tmpnodelist['chk'][$l]);
                    array_push($rawnodelist,$tmpnodelist['val'][$l]);
                }
            }
        }    
        $roadchklist=array();
        $rawroadlist=array();
        //开始生成道路
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $tmproadlist=$this->getAllRoadNearBy($rawhexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmproadlist['chk'][$l],$roadchklist))//不存在这个road
                {
                    array_push($roadchklist,$tmproadlist['chk'][$l]);
                    array_push($rawroadlist,$tmproadlist['val'][$l]);
                }
            }
        }
        //地图初始元素已决定，开始分配属性
        $hexagonNumberlist=[2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12,7];
        $hexagonkindlist=['forest','forest','forest','forest','iron','iron','iron','grass','grass','grass','grass','wheat','wheat','wheat','wheat','stone','stone','stone'];//注意这个是除掉了沙漠的
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $retdata['hexagon'][$i]['Pos']=$rawhexagon[$i];
            //先分配数字
            $number=array_splice($hexagonNumberlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
            $retdata['hexagon'][$i]['number']=$number;
            //分配资源
            if($number==7)
            {
                $kind='desert';
                $retdata['hexagon'][$i]['kind']='desert';
            }else{
                $kind=array_splice($hexagonkindlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
                $retdata['hexagon'][$i]['kind']=$kind;
            }
            //至此地区已经布置完成，可以发送到客户端
        }
        //节点与道路属性赋予
        for($j=0;$j<count($rawnodelist);$j++)
        {
            $retdata['node'][$j]['Pos']=$rawnodelist[$j];
            $retdata['node'][$j]['belongto']=-1;
            $retdata['node'][$j]['building']='blank';
        }
        for($k=0;$k<count($rawroadlist);$k++)
        {
            $retdata['road'][$k]['Pos']=$rawroadlist[$k];
            $retdata['road'][$k]['belongto']=-1;
        }
        //添加玩家信息和为玩家添加私有数据
        for($l=0;$l<MaxPlayer;$l++)
        {
            if(in_array($l,$nowplayer))
            {
                $retdata['player'][$l]['status']='online';
                $retdata['player'][$l]['resources']=0;
                $retdata['player'][$l]['card']=0;
                $retdata['player'][$l]['soldier']=0;
                for($i=0;$i<10;$i++)
                    $this->pridata[$l][kindnum[$i]]=0;
            }else{
                $retdata['player'][$l]['status']=null;
            }
        }
        $retdata['status']['process']=1;
        $retdata['status']['turn']=0;
        $retdata['status']['extra']=0;
        $this->publicdata=$retdata;//存储为公有数据
        //随机先手顺序
        $retdata['showmsg']="系统将进行随机分配房子置放顺序\n";
        for($i=0;$i<MaxPlayer;$i++)
        {
            if($this->publicdata['player'][$i]['status']!=null)array_push($this->startrolldata,$i);//添加存在的玩家的索引号
        }
        shuffle($this->startrolldata);//打乱摇骰子顺序
        for($i=0;$i<count($this->startrolldata);$i++)
        {
            $retdata['showmsg'].="第".($i+1)."个放房子的是".colornumzh[$this->startrolldata[$i]]."玩家\n";
        }
        $this->publicdata['status']['turn']=$this->startrolldata[0];//给第一个玩家放房子
        $retdata['head']='startgame';//作为数据头
        $this->room->broadcast($retdata);
    }

    //核心处理函数
    public function handleGame($msg,$index){
        switch ($msg['head']) {
            case 'roll':
                //接收到扔骰子指令，
                $ret['head']='roll';
                $ret['roll']=array(rand(1,6),rand(1,6));
                $value=$ret['roll'][0]+$ret['roll'][1];
                $ret['showmsg']=$this->room->nicklist[$index]."摇到了点数".$value."\n";
                $this->room->broadcast($msg);
                //收取资源
                $this->publicdata['status']['process']=4;//进入建设环节
                break;
            default:
                # code...
                break;
        }
    }
}
