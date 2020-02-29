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
    资源分配表，键为hexagon的index，值为一个index array，index代表用户index，其值是可获得数量
    */
    public $resList;
    public $startrolldata=array();//决定谁先放房子的骰子数据

    public function __construct($room){
        $this->room=$room;
    }
    //////////////////////////////////////地图操作元素函数
    //为了避免node和array索引不对就造成不等的问题而设的sort函数 该函数调用频率极高，有空可以注重此处性能
    public function PosSort(&$input)
    {
        //sort原则,y最大的在前面，如若相等，则x最大的在前面
        for($i=0;$i<count($input)-1;$i++)
        {
            for($j=$i+1;$j<count($input);$j++)
            {
                if($input[$i]['y']<$input[$j]['y'] || ($input[$i]['y']==$input[$j]['y'] && $input[$i]['x']<$input[$j]['x']))
                {
                    $tmp=$input[$i];
                    $input[$i]=$input[$j];
                    $input[$j]=$tmp;
                }
            }
        }
    }
    public function getIndexByPos($Pos)//通过Pos获取index，hexagon node road通用 如果不在地图里则返回null
    {
        if($Pos[1]!=null)
        {
            if(count($Pos)==2)
            {
                for($i=0;$i<count($this->publicdata['road']);$i++)
                {
                    if($Pos==$this->publicdata['road'][$i]['Pos'])return $i;
                }
            }elseif (count($Pos)==3) {
                for($i=0;$i<count($this->publicdata['node']);$i++)
                {
                    if($Pos==$this->publicdata['node'][$i]['Pos'])return $i;
                }
            }else{
                var_dump($Pos);//出问题了
            }
        }else {
            for($i=0;$i<count($this->publicdata['hexagon']);$i++)
            {
                if($Pos==$this->publicdata['hexagon'][$i]['Pos'])return $i;
            }
        }

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
        $ret=array();
        for( $i=0;$i<360;$i+=60)
        {
            $P1=$this->getNearPosition($P,$i);
            $P2=$this->getNearPosition($P,$i+60);
            $it=array($P0,$P1,$P2);
            $this->PosSort($it);
            var_dump($it);
            array_push($ret,$it);
        }
        return $ret;
    }
    public function getAllRoadNearBy($P)//获取该六边形临近的所有道路
    {
        $P0=$P;
        $ret=array();
        for( $i=0;$i<360;$i+=60)
        {
            $P1=$this->getNearPosition($P,$i);
            $it=array($P0,$P1);
            $this->PosSort($it);
            array_push($ret,$it);
        }
        return $ret;
    }
    //返回节点的类型，根据其道路延伸方式有Y型和人型
    public function getNodeType($P)
    {
        if((abs(($P[0]['y']+$P[2]['y'])/2)-0.5)%2)//偶数就是人型，奇数就是Y型
        {
            return 1;//代表y型
        }else{
            return 0;//代表人型
        }
    }
    public function getRoadNearByNode($P)//获取节点附近的三条道路 返回长度3的array
    {
        $R1=$this->PosSort(array($P[0],$P[1]));
        $R2=$this->PosSort(array($P[0],$P[2]));
        $R3=$this->PosSort(array($P[1],$P[2]));
        return array($R1,$R2,$R3);
    }
    public function getNodeConnectRoad($P)//获取道路两边的节点 返回长度2的array 
    //返回值的[0]总是返回以“人”字形节点扩散时的外节点 参考和@getRoadNearByNode连用时返回的点的规则
    {
        //垂直向上的路 sort[0]永远会取到右边的方块
        if($this->getNearPosition($P[0],180)==$P[1])//
        {
            $NP1=$this->getNearPosition($P[0],120);
            $NP2=$this->getNearPosition($P[0],240);
            $N1=array($NP1,$P[0],$P[1]);
            $N2=array($P[0],$P[1],$NP2);
        }else if($this->getNearPosition($P[0],240)==$P[1])//\这样的路，[0]是右上的方块
        {
            $NP1=$this->getNearPosition($P[0],180);
            $NP2=$this->getNearPosition($P[0],300);
            $N2=array($P[0],$NP1,$P[1]);
            $N1=array($P[0],$NP2,$P[1]);
        }else {//这两种情况都不是的话必是/这样的路了 [0]是左上的方块
            $NP1=$this->getNearPosition($P[0],0);
            $NP2=$this->getNearPosition($P[0],240);
            $N2=array($NP1,$P[0],$P[1]);
            $N1=array($P[0],$P[1],$NP2);
        }
        return array($N1,$N2);
    }
    public function chkNodeNear($P)//检查节点附近是否符合“建筑物不能相邻，本身是空地”，如果不符合要求则返回false
    {
        $thisindex=$this->getIndexByPos($P);
        if($this->publicdata['node'][$thisindex]['belongto']!='-1')return false;
        $road=$this->getRoadNearByNode($P);
        $nodetype=$this->getNodeType($P);
        for($i=0;$i<3;$i++)
        {
            $nearnode[$i]=$this->getNodeConnectRoad($road[$i]);
            $chkindex=$this->getIndexByPos($nearnode[i][$nodetype]);
            if($this->publicdata['node'][$chkindex]['belongto']!='-1')return false;
        }
        return true;
    }
    public function AddNodeRes($P,$index)//为一个更新的建筑节点更新资源索引表 index是更新用户的index
    {
        foreach ($P as $key => $hexagonPos) {
            $hexagonindex=$this->getIndexByPos($hexagonPos);
            if($this->resList[$hexagonindex][$index]==null)$this->resList[$hexagonindex][$index]=0;
            $this->resList[$hexagonindex][$index]+=1;
        }
    }
    public function chkNodeHasRoad($P,$index)//检查节点是否被$index的用户铺过来了，如果铺过来了则返回true
    {
        $road=$this->getRoadNearByNode($P);
        foreach ($road as $key => $value) {
            $roadindex=$this->getIndexByPos($road[$key]);
            if($this->publicdata['road'][$roadindex]['belogto']==$index)return true;
        }
        return false;
    }
    public function buildhome($P,$index,$param=0)//为index修建一个村庄，param如果为1则不耗费资源，且不进行道路检查
    {
        $res=&$this->pridata[$index]['resources'];
        if($this->chkNodeNear($P)==false)return false;
        if($param==0)
        {
            if($this->chkNodeHasRoad($P,$index)==false)return false;
            if($res['grass']>=1 
            && $res['wheat']>=1 
            && $res['forest']>=1 
            && $res['iron']>=1 )
            {
                $res['grass']-=1;
                $res['wheat']-=1;
                $res['forest']-=1;
                $res['iron']-=1;
            }else{
                return false;
            }
        }
        $nodeindex=$this->getIndexByPos($P);
        $this->publicdata['node'][$nodeindex]['belongto']=$index;
        $this->publicdata['node'][$nodeindex]['building']='home';
        $this->AddNodeRes($P,$index);
    }
    public function buildroad($P,$index,$param=0)//为index修一条路，param为1则不耗费资源且不检查道路要求
    {
        $roadindex=$this->getIndexByPos($P);
        $res=&$this->pridata[$index]['resources'];
        if($this->publicdata['road'][$roadindex]['belongto']!='-1')return false;
        if($param==0)
        {
            //检查道路要求
            $tmpnode=$this->getNodeConnectRoad($P);//获得道路两边的节点
            $roadnear1=$this->getRoadNearByNode($tmpnode[0]);//通过道路两边的节点搜索相邻道路
            $roadnear2=$this->getRoadNearByNode($tmpnode[1]);
            $roadnear=array_merge($roadnear1,$roadnear2);//合并两个节点的搜索结果
            $flag=0;
            foreach ($roadnear as $key => $road) {
                $roadindex=$this->getIndexByPos($road);
                if($this->publicdata['road'][$roadindex]['belongto']==$index)
                {
                    $flag=1;
                    break;
                }
            }
            if($flag==0)return false;
            //检查资源要求
            if($res['iron']>=1
            && $res['forest']>=1)
            {
                $res['iron']-=1;
                $res['forest']-=1;
            }else{
                return false;
            }
        }
        $this->publicdata['road'][$roadindex]['belongto']=$index;
        //TODO:最大道路检查函数
    }
    //////////////////////////////////地图整体相关函数
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
    public function initMap()
    {
        $it['x']=0;
        $it['y']=0;
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
        //开始生成节点
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $tmpnodelist=$this->getAllNodeNearby($rawhexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmpnodelist[$l],$rawnodelist))//不存在这个node
                {
                    array_push($rawnodelist,$tmpnodelist[$l]);
                }
            }
        }    
        $rawroadlist=array();
        //开始生成道路
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $tmproadlist=$this->getAllRoadNearBy($rawhexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmproadlist[$l],$rawroadlist))//不存在这个road
                {
                    array_push($rawroadlist,$tmproadlist[$l]);
                }
            }
        }
        //地图初始元素已决定，开始分配属性
        $hexagonNumberlist=[2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12,7];
        $hexagonkindlist=['forest','forest','forest','forest','iron','iron','iron','grass','grass','grass','grass','wheat','wheat','wheat','wheat','stone','stone','stone'];//注意这个是除掉了沙漠的
        for($i=0;$i<count($rawhexagon);$i++)
        {
            $this->publicdata['hexagon'][$i]['Pos']=$rawhexagon[$i];
            //先分配数字
            $number=array_splice($hexagonNumberlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
            $this->publicdata['hexagon'][$i]['number']=$number;
            //分配资源
            if($number==7)
            {
                $kind='desert';
                $this->publicdata['hexagon'][$i]['kind']='desert';
            }else{
                $kind=array_splice($hexagonkindlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
                $this->publicdata['hexagon'][$i]['kind']=$kind;
            }
            //至此地区已经布置完成，可以发送到客户端
        }
        //节点与道路属性赋予
        for($j=0;$j<count($rawnodelist);$j++)
        {
            $this->publicdata['node'][$j]['Pos']=$rawnodelist[$j];
            $this->publicdata['node'][$j]['belongto']=-1;
            $this->publicdata['node'][$j]['building']='blank';
        }
        for($k=0;$k<count($rawroadlist);$k++)
        {
            $this->publicdata['road'][$k]['Pos']=$rawroadlist[$k];
            $this->publicdata['road'][$k]['belongto']=-1;
        }
    }
    public function initPlayer($nowplayer)//添加玩家信息和为玩家添加私有数据=
    {
        for($l=0;$l<MaxPlayer;$l++)
        {
            if(in_array($l,$nowplayer))
            {
                $this->publicdata['player'][$l]['status']='online';
                $this->publicdata['player'][$l]['resources']=0;
                $this->publicdata['player'][$l]['card']=0;
                $this->publicdata['player'][$l]['soldier']=0;
                for($i=0;$i<10;$i++)
                    $this->pridata[$l][kindnum[$i]]=0;
            }else{
                $this->publicdata['player'][$l]['status']=null;
            }
        }
        $this->publicdata['status']['process']=1;
        $this->publicdata['status']['turn']=0;
        $this->publicdata['status']['extra']=0;
    }
    public function startgame($nowplayer)//初始化游戏地图
    {
        $this->initMap();
        $this->initPlayer($nowplayer);
        $retdata=$this->publicdata;//准备返回数据
        //随机先手顺序
        $retdata['head']='startgame';//作为数据头
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
        $retdata['private']=$this->pridata[$this->startrolldata[0]];//因为大家的私有数据一开始都是一样的，所以直接以第一个玩家的私有数据作为私有数据发给大家
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
            case 'buildhome':
                if($this->publicdata['status']['process']<=2)//还处于初期放房子的时候
                {//这时候不需要耗用资源和道路要求，只需要位置合法即可
                    if($this->chkNodeNear($msg['Pos']))
                    
                }
            break;
            default:
                var_dump($msg);
            break;
        }
    }
}
