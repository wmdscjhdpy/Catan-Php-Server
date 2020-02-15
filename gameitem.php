<?php
//namespace catan;
//资源对应数字          0       1       2       3       4
const kindnum=array('forest','iron','grass','wheat','stone');
//以下是游戏元素
class hexagon{
    public $Pos;
    public $kind='desert';//资源种类
    public $isBlock=false;//是否被强盗压住
    public $number;//资源数字
    public function __construct($_Pos,$_Number)
    {
        $this.$Pos=$_Pos;
        $this.$number=$_Number;
    }
}
class node{
    public $Pos;//是三元素数组
    public $belongto='nobody';//判断被谁占有
    public $building='blank';
    public $port='none';//港口代号
    public function __construct($_Pos)
    {
        $this.$Pos=$_Pos;
    }
}
class road{
    public $Pos;//是二元素数组
    public $belongto='nobody';//判断被谁占有
    public function __construct($_Pos)
    {
        $this.$Pos=$_Pos;
    }
}
//以下是游戏数据库
//定义0-4为 蓝绿红黄
class gamedata{
    public $hexagonlist;//存储六边形对象
    public $nodelist;//存储结点对象
    public $roadlist;//存储道路对象
    /*
    骰子事件指引对象，键为骰子数，值仍为一个array
    二级键代表玩家编号，值仍为一个对象
    子变量有5个，分别为iron,wheat,forest,stone,grass 其值代表能收获多少。
    */
    public $rollhandle; 
    public function calcNodeId($Pos){
        $nodeid;
        $X=($Pos[0]->$x+$Pos[1]->$x+$Pos[2]->$x)/3;
        $Y=($Pos[0]->$y+$Pos[1]->$y+$Pos[2]->$y)/3;
        $nodeid=''.$X.$Y;
        return $nodeid;
    }
    
    public function calcRoadId($Pos){//给每一个道路一个唯一编号
        $roadid;
        $X=($Pos[0]->$x+$Pos[1]->$x)/2;
        $Y=($Pos[0]->$y+$Pos[1]->$y)/2;
        $roadid=''.$X.$Y;
        return $roadid;
    }
    
    public function getNearPosition($P,$deg){//获取临近六边形坐标
        while($deg<0)$deg+=360;
        $newP;
        switch($deg%360)
        {
            case 0:
                $newP->$x=$P->x+1;
                if($newP->$x==0 && (abs($P->$y%2)))$newP->$x+=1;
            break;
            case 180:
                $newP->$x=$P->$x-1;
                if($newP->$x==0 && (abs($P->$y%2)))$newP->$x-=1;
            break;
            case 60:
                $newP->$y+=1;
                if((!(abs($P->$y%2)))&& ($P->$x>=0))$newP->$x+=1;
                if((abs($P->$y%2))&& ($P->$x<0))$newP->$x+=1;
            break;
            case 120:
                $newP->$y+=1;
                if((abs($P->$y%2))&& ($P->$x>0))$newP->$x-=1;
                if((!(abs($P->$y%2)))&& ($P->$x<=0))$newP->$x-=1;
            break;
            case 240:
                $newP->$y-=1;
                if((abs($P->y%2))&& ($P->x>0))$newP->$x-=1;
                if((!(abs($P->y%2)))&& ($P->x<=0))$newP->$x-=1;
            break;
            case 300:
                $newP->$y-=1;
                if((!(abs($P->y%2)))&& ($P->x>=0))$newP->$x+=1;
                if((abs($P->y%2))&& ($P->x<0))$newP->$x+=1;
            break;
        }
        return $newP;
    }
    
    public function getAllNodeNearby($P)//获取和这个六边形相邻的所有节点
    {
        $P0=$P;
        for( $i=0;$i<360;$i+=60)
        {
             $P1=getNearPosition($P,$i);
             $P2=getNearPosition($P,$i+60);
            array_push($ret->$val,[$P0,$P1,$P2]);
            array_push($ret->$chk,calcNodeId([$P0,$P1,$P2]));
        }
        return $ret;
    }
    
    public function getAllRoadNearBy($P)//获取该六边形临近的所有节点
    {
        $P0=$P;
        for( $i=0;$i<360;$i+=60)
        {
             $P1=getNearPosition($P,$i);
            array_push($ret->$val,[$P0,$P1]);
            array_push($ret->$chk,calcRoadId([$P0,$P1]));
        }
        return $ret;
    }
    public function initMap()//初始化游戏地图
    {
        $ret;
        $it->$x=0;
        $it->$y=0;
        array_push($ret->$hexagon,$it);
        //大循环
        for($i=1;$i<=2;$i++)
        {
            $it=getNearPosition($it,300);
    
            array_push($ret->$hexagon,$it);
            for($deg=0;$deg<360;$deg+=60)
            {
                $k=0;
                if($deg==0)$k+=1;//每一次第一次的时候由于突出了一格，所以0°的操作少一个
                while($k<$i)
                {
                    $it=getNearPosition($it,$deg);
                    array_push($ret->$hexagon,$it);
                    $k++;
                }
            }
        }
        //开始生成节点
        for($i=0;$i<count($ret->$hexagon);$i++)
        {
            $tmpnodelist=getAllNodeNearby($ret->$hexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmpnodelist->$chk[$l],$nodechklist))//不存在这个node
                {
                    array_push($nodechklist,$tmpnodelist->$chk[$l]);
                    array_push($ret->$node,$tmpnodelist->$val[$l]);
                }
            }
        }    
        //开始生成道路
        for($i=0;$i<count($ret->$hexagon);$i++)
        {
            $tmproadlist=getAllRoadNearBy($ret->$hexagon[$i]);//获取节点
            for( $l=0;$l<6;$l++)
            {   
                if(!in_array($tmproadlist->$chk[$l],$roadchklist))//不存在这个road
                {
                    array_push($roadchklist,$tmproadlist->$chk[$l]);
                    array_push($ret->$road,$tmproadlist->$val[$l]);
                }
            }
        }
        //至此所有地图元素已集齐但未初始化
        $hexagonNumberlist=[2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12,7];
        $hexagonkindlist=['forest','forest','forest','forest','iron','iron','iron','grass','grass','grass','grass','wheat','wheat','wheat','wheat','stone','stone','stone'];//注意这个是除掉了沙漠的
        for($i=0;$i<count($ret->$hexagon);$i++)
        {
            $this->$hexagonlist[$i]=new hexagon($ret->$hexagon[i]);
            //先分配数字
            $this->$hexagonlist[$i]->$number=array_splice($hexagonNumberlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
            //分配资源
            if($this->$hexagonlist[$i]->$number==7)
            {
                $this->$hexagonlist[$i]->$kind='desert';
            }else{
                $this->$hexagonlist[$i]->$kind=array_splice($hexagonkindlist,rand(0,count($hexagonNumberlist)-1),1)[0];//随机调出一个元素并从列表中删掉
            }
            //至此地区已经布置完成，可以发送到客户端
        }
        ///TODO :港口
        return;
    }
}


