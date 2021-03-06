<?php
require_once './gameroom.php';
//namespace catan;
//定义0-3为 蓝绿红黄
const colornum=array('blue','green','red','yellow','purple','sky');
const colornumzh=array('蓝色','绿色','红色','黄色',  '紫色', '天蓝');
//资源对应数字          0       1       2       3       4       5          6        7              8            9
const kindnum=array('forest','iron','grass','wheat','stone','solders','harvest','monopoly','roadbuilding','winpoint');
const kindnumzh=array('木头','铁'   ,'羊毛'   ,'小麦' ,'石头' ,'士兵'    ,'丰收之年','垄断'   ,'道路建设'     ,'胜利点');
//20个士兵，5个胜利点，3个道路建设，3个垄断，3个丰收之年，
//以下是游戏数据库
class gamedata{
    private $room;//存放房间信息
    private $nick;//存放玩家名字，是一个对应index的数组
    public $publicdata;//玩家的公有数据
    private $pridata;//玩家的私有数据
    /*
    资源分配表，键为hexagon的index，值为一个index array，index代表用户index，其值是可获得数量
    */
    private $resList;
    private $devcardpool=array(5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,5,6,6,6,7,7,7,8,8,8,9,9,9,9,9);
    private $robindex=7;//确定盗贼位置的变量
    private $startrolldata=array();//决定谁先放房子的骰子数据 其count是游戏玩家数
    private $robberchklist=array();//以$startrolldata值作为顺序的待抢卡表，0则为不需要扔或已扔完，index和startrolldata的index保持一致，其余值代表等待扔牌数
    private $tmpvalue=0;//在开始时代表当前正在放起始房的序号 是上面array的index 游戏进行时作为道路建设卡的临时变量，当修了第一条路后其值会变为1
    private $maxsoldiersnum=3-1;
    private $maxroadsnum=5-1;
    //和最大道路检查相关的变量
    private $activeroad=array();//活跃中的检查道路，其值为活跃代号，数字越大代表越处于分支的末尾

    //交易检查量
    private $rejectednum=0;
    public function __construct(&$room){
        $this->room=&$room;
        $this->nick=&$this->room->nicklist;
    }
    //调试用函数
    public function printPos($Pos)
    {
        echo "Pos:";
        foreach ($Pos as $num => $point) {
            echo " (".$point['x'].",".$point['y'].")";
        }
        echo "\n";
    }
    //通信操作函数
    public function updatePublicData($key,$data,$msg=null,$extra=null)//通过将需要更改的publicdata数据转写为key+data的组合 通过这个函数在更改时自动广播更改数据 msg是可选广播消息 如果key==null则只广播消息 $extra是特殊值，如果为'+'则data使用+=,其他符号类似
    {
        if($key===null)
        {
            $send['head']='msg';
            $send['showmsg']=$msg;
            $this->room->broadcast($send);
            return;
        }
        $it=&$this->publicdata;
        for($i=0;$i<count($key);$i++)
        {
            $it=&$it[$key[$i]];//循环调用引用到最终层
        }
        if($extra)
        {
            switch($extra)
            {
                case '+':
                    $it+=$data;
                break;
                case '-':
                    $it-=$data;
                break;
            }
        }else {
            $it=$data;//更改数据
        }
        $send['head']='update';
        $send['type']='public';
        $send['key']=$key;
        $send['data']=$it;
        if($msg!==null)$send['showmsg']=$msg;
        $this->room->broadcast($send);
    }
    public function flushPrivateData($index,$msg=null)//这个是将服务器的对应数据全部推送过去，如果数据变化比较多的话就这么干
    {
        $send['head']='update';
        $send['type']='private';
        $send['key']=null;
        $send['data']=$this->pridata[$index];
        if($msg)$send['showmsg']=$msg;
        $this->room->sendDataByIndex($index,$send);
        for($i=0,$resnum=0;$i<5;$i++)$resnum+=$this->pridata[$index]['resources'][kindnum[$i]];
        $this->updatePublicData(['player',$index,'resources'],$resnum);//更新该玩家手牌数量
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
        if(isset($Pos[1]))
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
                //输入参数类型不对
                debug_print_backtrace();
                var_dump($Pos);//出问题了
            }
        }else {
            for($i=0;$i<count($this->publicdata['hexagon']);$i++)
            {
                if($Pos==$this->publicdata['hexagon'][$i]['Pos'])return $i;
            }
        }
        //如果执行到了这里说明没找到对应索引
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
        if($P[0]['y']==$P[1]['y'])return 0;//代表人型
        if($P[1]['y']==$P[2]['y'])return 1;//代表Y型
        //执行到这里说明传入参数有问题
        debug_print_backtrace();
        var_dump($P);
    }
    public function getRoadNearByNode($P)//获取节点附近的三条道路 返回一个array，长度代表有效道路数量
    {
        $R[0]=array($P[0],$P[1]);
        $R[1]=array($P[0],$P[2]);
        $R[2]=array($P[1],$P[2]);
        $output=array();
        foreach ($R as $road) {
            $this->PosSort($road);
            if($this->getIndexByPos($road)!==null)
            {//道路合法
                array_push($output,$road);
            }
        }
        return $output;
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
    public function chkNodeNear($P)//检查节点附近是否符合“建筑物不能相邻”，如果不符合要求则返回false
    {
        $road=$this->getRoadNearByNode($P);
        $nodetype=$this->getNodeType($P);
        for($i=0;$i<count($road);$i++)
        {
            $nearnode[$i]=$this->getNodeConnectRoad($road[$i]);
            $chkindex=$this->getIndexByPos($nearnode[$i][$nodetype]);
            if($this->publicdata['node'][$chkindex]['belongto']!='-1')return false;
        }
        return true;
    }
    public function AddNodeRes($P,$index)//为一个更新的建筑节点更新资源索引表 index是更新用户的index 注意该函数不会自动刷新私有数据
    {
        foreach ($P as $key => $hexagonPos) {
            $hexagonindex=$this->getIndexByPos($hexagonPos);
            if($hexagonindex===null)continue;//不在地图内的hexagon
            if(!isset($this->resList[$hexagonindex][$index]))$this->resList[$hexagonindex][$index]=0;
            $this->resList[$hexagonindex][$index]+=1;
        }
        $this->pridata[$index]['score']+=1;
    }
    public function chkNodeHasRoad($P,$index)//检查节点是否被$index的用户铺过来了，如果铺过来了则返回节点接壤的道路数量
    {
        $road=$this->getRoadNearByNode($P);
        $sum=0;
        foreach ($road as $value) {
            $roadindex=$this->getIndexByPos($value);
            if($this->publicdata['road'][$roadindex]['belongto']==$index)
            {
                $sum++;
            }
        }
        return $sum;
    }
    public function buildhome($P,$index,$param=0)//为index修建一个村庄，param如果为1则不耗费资源，且不进行道路检查，param为2的话则根据该村庄分配初始资源
    {
        $res=&$this->pridata[$index]['resources'];
        if($this->chkNodeNear($P)==false)return false;
        if($param==0)
        {
            if($this->chkNodeHasRoad($P,$index)==false)return false;
            $res['grass']-=1;
            $res['wheat']-=1;
            $res['forest']-=1;
            $res['iron']-=1;
        }else if($param==2)//是第二个村，给予初始资源
        {
            foreach ($P as  $hexPos) {
                $hexindex=$this->getIndexByPos($hexPos);
                if($hexindex===null)continue;
                $hexkind=$this->publicdata['hexagon'][$hexindex]['kind'];
                if($hexkind!='desert')$this->pridata[$index]['resources'][$hexkind]++;
            }
        }
        $nodeindex=$this->getIndexByPos($P);
        $this->AddNodeRes($P,$index);
        if($this->publicdata['node'][$nodeindex]['port']!=-1)//如果这是一个港口
        {
            array_push($this->pridata[$index]['port'],$this->publicdata['node'][$nodeindex]['port']);
            $this->pridata[$index]['port']=array_unique($this->pridata[$index]['port']);
        }
        $this->flushPrivateData($index);
        $this->updatePublicData(['node',$nodeindex,'belongto'],$index);
        $this->updatePublicData(['node',$nodeindex,'building'],'home',$this->nick[$index]."修起了一座村庄\n");
        return true;
    }
    public function buildroad($P,$index,$param=0)//为index修一条路，param为1则不耗费资源且按初期要求检查道路，2为道路建设的免费路
    {
        $roadindex=$this->getIndexByPos($P);
        $res=&$this->pridata[$index]['resources'];
        if($this->publicdata['road'][$roadindex]['belongto']!='-1')return false;
        if($param==0 || $param==2)
        {
            //检查道路要求
            $tmpnode=$this->getNodeConnectRoad($P);//获得道路两边的节点
            $roadnear1=$this->getRoadNearByNode($tmpnode[0]);//通过道路两边的节点搜索相邻道路
            $roadnear2=$this->getRoadNearByNode($tmpnode[1]);
            $roadnear=array_merge($roadnear1,$roadnear2);//合并两个节点的搜索结果
            $flag=0;
            foreach ($roadnear as $key => $road) {
                $tmproadindex=$this->getIndexByPos($road);
                if($this->publicdata['road'][$tmproadindex]['belongto']==$index)
                {
                    $flag=1;
                    break;
                }
            }
            if($flag==0)return false;
            if($param==0)
            {
                $res['iron']-=1;
                $res['forest']-=1;
            }
        }else{//检查初始道路要求:周围必须有自己的村且村旁边不能再有自己的路
            $tmpnode=$this->getNodeConnectRoad($P);//获得道路两边的节点
            $nodepos[0]=$this->getIndexByPos($tmpnode[0]);
            $nodepos[1]=$this->getIndexByPos($tmpnode[1]);
            if($this->publicdata['node'][$nodepos[0]]['belongto']==$index)
            {
                $tmproad=$this->getRoadNearByNode($tmpnode[0]);
                foreach ($tmproad as $key => $value) {
                    $tmproadindex=$this->getIndexByPos($value);
                    if($this->publicdata['road'][$tmproadindex]['belongto']==$index)return false;
                }
            }else if($this->publicdata['node'][$nodepos[1]]['belongto']==$index)
            {
                $tmproad=$this->getRoadNearByNode($tmpnode[1]);
                foreach ($tmproad as $key => $value) {
                    $tmproadindex=$this->getIndexByPos($value);
                    if($this->publicdata['road'][$tmproadindex]['belongto']==$index)return false;
                }
            }else{
                return false;
            }
        }
        $this->updatePublicData(['road',$roadindex,'belongto'],$index,$this->nick[$index]."修建了一条道路\n");
        //最大道路检查
        $maxroad=$this->maxRoadChk($index);
        if($maxroad>$this->maxroadsnum)
        {
            $this->maxroadsnum=$maxroad;
            if($this->publicdata['status']['maxroads']!=-1)
            {
                $this->pridata[$this->publicdata['status']['maxroads']]['score']-=2;
                $this->flushPrivateData($this->publicdata['status']['maxroads'],"你失去了最大道路成就\n");
            }
            $this->pridata[$index]['score']+=2;
            $this->updatePublicData(['status','maxroads'],$index,"".$this->nick[$index]."获得了最大道路成就\n");
        }
        $this->flushPrivateData($index);
        return true;
    }
    public function maxRoadChk($userindex)
    {
        $ref=&$this->publicdata['road'];
        $maxroad=0;//用于本次分析的最大道路检测
        for($i=0;$i<count($ref);$i++)
        {//开始试图延展道路 先找出一个端点路线
            if($ref[$i]['belongto']!=$userindex)continue;//该道路不属于当前用户
            $tmpnode=$this->getNodeConnectRoad($ref[$i]['Pos']);
            $flag=0;
            $rootnode;
            foreach ($tmpnode as $nearnodepos) {
                if($this->chkNodeHasRoad($nearnodepos,$userindex)%2==1
                && $flag==0)//通过判断周边路径数量是否为奇数
                {
                    $flag=1;//是奇数
                }else{
                    $rootnode=$nearnodepos;//如果是端点的话这个值就是朝有路的方向的节点，后面会用到。
                }
            }
            if($flag===0)continue;//不是端点，不进行探索
            $mainlong=0;//用于局部的最大道路检测
            $activetag=1;//代表分支活跃级别，越小越往前
            $deepbox=array();//关键迭代器 除了根节点只有1个元素，其余情况都是两个元素
            $deepbox[1][0]=$rootnode;//根节点不需要比较两边长度而进行取舍
            $deepbox[1][1]=0;//方便进入循环迭代用的
            $this->activeroad[$i]=$activetag;//代表分支活跃级别，越小越往前;
            while($activetag>0)
            {
                foreach ($deepbox[$activetag] as $boxindex => $data) {
                    if(is_int($data) || $boxindex==='root')//数据已经被处理过了或者是子根节点信息
                    {
                        continue;
                    }
                    if($activetag!=1)//如果是一个子树的话 处理第一条道路的标志和导出发展节点
                    {
                        $tmpnode=$this->getNodeConnectRoad($data);
                        foreach ($tmpnode as $tmp) {
                            if($tmp!=$deepbox[$activetag]['root'])
                            {
                                $forwardnode=$tmp;//取出和子树根节点不同的节点即为发展节点
                            }
                        }
                        $this->activeroad[$this->getIndexByPos($data)]=$activetag;//给子道路树初始道路添加标记
                    }else{//根节点传送的直接就是节点，不需要通过道路进行转换
                        $forwardnode=$data;
                    }
                    echo "$activetag.start search on ";
                    $this->printPos($data);
                    $ret=$this->roadTraceNextBranch($forwardnode,$userindex,$activetag);
                    if($ret!=null)//存在新分支
                    {
                        $activetag++;
                        $deepbox[$activetag]=$ret;//向下一级迭代
                        break; //马上退出，继续处理下一个迭代的事件
                    }else{//该路径到此结束
                        //统计本次子路径长度
                        $sum=0;
                        for($j=0;$j<count($this->publicdata['road']);$j++)
                        {
                            if(isset($this->activeroad[$j]))
                            if($this->activeroad[$j]==$activetag)
                            {
                                $sum++;
                                $this->activeroad[$j]=0;//清空active标志
                            }
                        }
                        echo "branch $activetag has long $sum \n";
                        $deepbox[$activetag][$boxindex]=$sum;
                    }
                }
                while((is_int($deepbox[$activetag][0]) && is_int($deepbox[$activetag][1])))
                {//该层次的道路都已清算完毕 向上一层结算 优先填充[0]在填充[1]
                    if(is_int($deepbox[1][0]))//已经回溯到根了
                    {
                        $mainlong+=$deepbox[1][0];
                        echo "get long ".$deepbox[1][0]."\n";
                        $activetag--;
                        break;
                    }
                    $sum=0;
                    for($j=0;$j<count($this->publicdata['road']);$j++)
                    {
                        if(isset($this->activeroad[$j])
                        && $this->activeroad[$j]==$activetag-1)
                        {
                            $sum++;
                            $this->activeroad[$j]=0;//清空active标志
                        }
                    }
                    echo "father branch $activetag has long $sum \n";
                    if($deepbox[$activetag][0]>$deepbox[$activetag][1])
                    {
                        if(is_int($deepbox[$activetag-1][0]))$deepbox[$activetag-1][1]=$deepbox[$activetag][0]+$sum;
                        else $deepbox[$activetag-1][0]=$deepbox[$activetag][0]+$sum;
                    }else{
                        if(is_int($deepbox[$activetag-1][0]))$deepbox[$activetag-1][1]=$deepbox[$activetag][1]+$sum;
                        else $deepbox[$activetag-1][0]=$deepbox[$activetag][1]+$sum;
                    }
                    //var_dump($deepbox);
                    $activetag--;
                }
            }
            if($maxroad<$mainlong)$maxroad=$mainlong;
        }
        if($maxroad==0)//到现在还没有找到奇数端点，是全偶数道路地图
        {//因为是全偶数道路地图 所以有多少条路就是多少条最大道路
            for($i=0;$i<count($ref);$i++)
            {
                if($ref[$i]['belongto']==$userindex)$maxroad++;
            }
        }
        echo "Max road: $maxroad \n";
        return $maxroad;
    }
    public function roadTraceNextBranch($P,$userindex,$branchtag)//根据node确定的方向一直搜寻道路直到下个分支 遇到分支时会将两个分支道路作为array返回
    {
        $ref=&$this->publicdata['road'];
        while(1)
        {
            $nearroad=$this->getRoadNearByNode($P);
            $usenum=0;
            $branch=array();
            foreach ($nearroad as $Pos) {
                $roadindex=$this->getIndexByPos($Pos);
                if(isset($this->activeroad[$roadindex])
                && ($this->activeroad[$roadindex]!=0 ))
                {
                    $usenum+=1;
                }else if($ref[$roadindex]['belongto']==$userindex){
                    array_push($branch,$Pos);//将还没登记的道路推送到分支上
                }
            }
            if(count($branch)==2)//存在新分支
            {
                $tmp1=$this->getNodeConnectRoad($branch[0]);
                $tmp2=$this->getNodeConnectRoad($branch[1]);
                foreach ($tmp1 as $value1) {//寻找tmp中相同的node即为子树的根
                    foreach ($tmp2 as $value2) {
                        if($value1===$value2)
                        {
                            $branch['root']=$value1;
                        }
                    }
                }
                echo "found branch:\n";
                $this->printPos($branch[0]);
                $this->printPos($branch[1]);
                return $branch;
            }else if(count($branch)==1)//存在继续延展的方向
            {
                echo "fetch road ";
                $this->printPos($branch[0]);
                $roadindex=$this->getIndexByPos($branch[0]);//获得新路的index
                $this->activeroad[$roadindex]=$branchtag;//将该条道路标记为活跃
                $nodePos=$this->getNodeConnectRoad($branch[0]);
                foreach ($nodePos as $Pos) {
                    if($P===$Pos)
                    {
                        continue;
                    }
                    $P=$Pos;//刷新要继续探路的nodeindex
                    break;
                }
            }else{//没有新路，该分支已探索完成
                echo "end node ";
                $this->printPos($P);
                return null;
            }
        }
    }
    //地图整体相关函数
    public function getNextPlayer($index){//将当前回合正常转移给下一个玩家 不输入参数则index为当前玩家
        if($index==null)
        {
            $index=$this->publicdata['status']['turn'];
        }
        do {
            $index++;
            if($index==MaxPlayer)$index-=MaxPlayer;
        } while ($this->publicdata['player'][$index]['status']==null);
        $this->updatePublicData(['status','turn'],$index);
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
        $hexagonNumberlist=[11,3,6,5,4,9,10,8,4,11,12,9,10,8,3,6,2,5];
        $hexagonkindlist=['forest','forest','forest','forest','iron','iron','iron','grass','grass','grass','grass','wheat','wheat','wheat','wheat','stone','stone','stone','desert'];//注意这个是除掉了沙漠的
        for($i=0;$i<count($rawhexagon);$i++)
        {
            //先分配资源
            $this->publicdata['hexagon'][$i]['Pos']=$rawhexagon[$i];
            $kind=array_splice($hexagonkindlist,rand(0,count($hexagonkindlist)-1),1)[0];//随机调出一个元素并从列表中删掉
            $this->publicdata['hexagon'][$i]['kind']=$kind;
            $this->publicdata['hexagon'][$i]['robber']=false;
            //然后分配数字
            if($kind!='desert')
            {
                $this->publicdata['hexagon'][$i]['number']=array_splice($hexagonNumberlist,0,1)[0];
            }else{
                $this->publicdata['hexagon'][$i]['number']=7;
            }
        }
        //节点与道路属性赋予
        for($j=0;$j<count($rawnodelist);$j++)
        {
            $this->publicdata['node'][$j]['Pos']=$rawnodelist[$j];
            $this->publicdata['node'][$j]['belongto']=-1;
            $this->publicdata['node'][$j]['building']='blank';
            $this->publicdata['node'][$j]['port']=-1;
        }
        for($k=0;$k<count($rawroadlist);$k++)
        {
            $this->publicdata['road'][$k]['Pos']=$rawroadlist[$k];
            $this->publicdata['road'][$k]['belongto']=-1;
        }
        //分配港口
        //港口分配表，键为港口index，值为控制的两个node节点
        $portindextable=array([24,25],[28,29],[30,31],[33,35],[37,38],[40,41],[44,45],[47,48],[50,51]);
        //港口类型表
        $portkindtable=array(0,1,2,3,4,5,5,5,5);
        foreach ($portindextable as $key => $table) {
            $kind=array_splice($portkindtable,rand(0,count($portkindtable)-1),1)[0];
            $this->publicdata['node'][$table[0]]['port']=$kind;
            $this->publicdata['node'][$table[1]]['port']=$kind;
        }
        $this->publicdata['trade']=null;
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
                    $this->pridata[$l]['resources'][kindnum[$i]]=5;//TODO:调试方便改的初始资源 记得改回来
                $this->pridata[$l]['score']=0;
                $this->pridata[$l]['port']=array();
            }else{
                $this->publicdata['player'][$l]['status']=null;
            }
        }
        $this->publicdata['status']['process']=1;
        $this->publicdata['status']['turn']=0;
        $this->publicdata['status']['extra']=0;
        $this->publicdata['status']['maxsoldiers']=-1;
        $this->publicdata['status']['maxroads']=-1;
    }
    public function startgame($nowplayer)//初始化游戏地图
    {
        $this->initMap();
        $this->initPlayer($nowplayer);
        $retdata=$this->publicdata;//数据准备完毕，准备加上数据头，返回数据
        //随机先手顺序
        $retdata['head']='startgame';//作为数据头
        $retdata['showmsg']="系统将进行随机分配房子置放顺序\n";
        for($i=0;$i<MaxPlayer;$i++)
        {
            if($this->publicdata['player'][$i]['status']!=null)
            {
                array_push($this->startrolldata,$i);//添加存在的玩家的索引号
            }
        }
        shuffle($this->startrolldata);//打乱摇骰子顺序
        for($i=0;$i<count($this->startrolldata);$i++)
        {
            $retdata['showmsg'].="第".($i+1)."个放房子的是".$this->nick[$this->startrolldata[$i]]."\n";
        }
        $this->room->broadcast($retdata);
        $this->updatePublicData(['status','turn'],$this->startrolldata[$this->tmpvalue]);//分配第一个建房子的人
        for($i=0;$i<MaxPlayer;$i++)
        {
            if($this->publicdata['player'][$i]['status']!=null)
            {
                $this->flushPrivateData($i);//给存在的玩家发送私有数据
            }
        }
    }

    //核心处理函数
    public function handleGame($msg,$index){
        switch ($msg['head']) {
            case 'roll':
                if($this->publicdata['status']['process']!=3)break;
                //接收到扔骰子指令，
                $ret['head']='roll';
                $ret['roll']=array(rand(1,6),rand(1,6));
                $rollnum=$ret['roll'][0]+$ret['roll'][1];
                $ret['showmsg']=$this->room->nicklist[$index]."摇到了点数".$rollnum."\n";
                $this->room->broadcast($ret);
                if($rollnum==7)
                {
                    $showmsg="";
                    foreach ($this->startrolldata as $listindex => $usrindex) {
                        if($this->publicdata['player'][$usrindex]['resources']>7)
                        {
                            $this->robberchklist[$listindex]=floor($this->publicdata['player'][$usrindex]['resources']/2);
                            $showmsg.=$this->nick[$usrindex]."需要丢弃".$this->robberchklist[$listindex]."张牌\n";
                        }else{
                            $showmsg.=$this->nick[$usrindex]."由于未超过7张牌，不需要为此付出代价\n";
                        }
                    }
                    $this->updatePublicData(['status','extra'],1,"强盗来袭！！！！！！！\n".$showmsg);
                    //检查是否所有玩家都已弃牌
                    $flag=1;
                    foreach ($this->robberchklist as $value) {
                        if($value)$flag=0;
                    }
                    if($flag)
                    {//所有玩家都已完成强盗丢牌工作，进入下一环节
                        $showmsg="没有人需要弃牌，由".$this->nick[$this->publicdata['status']['turn']]."移动强盗\n";
                        $this->updatePublicData(['status','extra'],2,$showmsg);
                    }
                }else{
                    $flushflag=array();//记录改变的数据
                    foreach ($this->resList as $hexindex => $value) {
                        if($rollnum==$this->publicdata['hexagon'][$hexindex]['number']
                        && $this->publicdata['hexagon'][$hexindex]['robber']!=true)
                        {
                            $reskind=$this->publicdata['hexagon'][$hexindex]['kind'];
                            foreach ($value as $userindex => $resnum) {
                                $this->pridata[$userindex]['resources'][$reskind]+=$resnum;
                                $flushflag[$userindex]=1;
                            }
                        }
                    }
                    foreach($flushflag as $userindex => $value)
                    {
                        $this->flushPrivateData($userindex);
                    }
                    $this->updatePublicData(['status','process'],4,''.$this->nick[$index]."进入建设阶段\n");
                }
            break;
            case 'discard':
                $ret['head']='msg';
                $ret['showmsg']=$this->nick[$index]."丢弃了:";
                for($i=0;$i<5;$i++)
                {
                    if($msg[$i]!=null)
                    {
                        $this->pridata[$index]['resources'][kindnum[$i]]-=$msg[$i];
                        $ret['showmsg'].=$msg[$i]."个".kindnumzh[$i].",";
                    }
                }
                substr($ret['showmsg'], 0, -1);
                $ret['showmsg'].="\n";
                $this->robberchklist[array_search($index,$this->startrolldata)]=0;
                //检查是否所有玩家都已弃牌
                $flag=1;
                foreach ($this->robberchklist as $value) {
                    if($value)$flag=0;
                }
                if($flag)
                {//所有玩家都已完成强盗丢牌工作，进入下一环节
                    $ret['showmsg'].="所有玩家都已弃牌，由".$this->nick[$this->publicdata['status']['turn']]."移动强盗\n";
                    $this->updatePublicData(['status','extra'],2);
                }
                $this->flushPrivateData($index);
                $this->room->broadcast($ret);
            break;
            case 'moverob':
                if($this->publicdata['status']['extra']!=2)break;
                $this->updatePublicData(['hexagon',$this->robindex,'robber'],false);
                $this->updatePublicData(['hexagon',$msg['index'],'robber'],true);
                $this->robindex=$msg['index'];
                $this->updatePublicData(['status','extra'],3);
                $ret['head']='msg';
                $ret['showmsg']="请选择强盗占领地附近任意一个玩家的村落进行掠夺\n";
                $this->room->sendDataByIndex($index,$ret);
            break;
            case 'robacard':
                if($this->publicdata['status']['extra']!=3)break;
                $nearbynode=$this->getAllNodeNearby($this->publicdata['hexagon'][$this->robindex]['Pos']);
                $flag=0;
                foreach ($nearbynode as $value) {
                    $nodeindex=$this->getIndexByPos($value);//进行掠夺合法性检查
                    if($this->publicdata['node'][$nodeindex]['belongto']==$msg['index'])
                    {
                        $flag=1;
                    }
                }
                if($flag==1 //所选玩家在附近
                && $msg['index']!=-1    //所选的不是空地
                && $this->publicdata['player'][$msg['index']]['resources']>=1)//已选中玩家有卡
                {
                    $getindex=rand(1,$this->publicdata['player'][$msg['index']]['resources']);
                    foreach ($this->pridata[$msg['index']]['resources'] as $resindex => $resnum) {
                        if($getindex>$resnum)
                        {
                            $getindex-=$resnum;
                            continue;
                        }
                        $this->pridata[$msg['index']]['resources'][$resindex]-=1;
                        $this->pridata[$index]['resources'][$resindex]+=1;
                        $this->flushPrivateData($msg['index'],"你被抢走了一个".kindnumzh[array_search($resindex,kindnum)]."\n");
                        
                        $this->flushPrivateData($index,"你掠夺来了一个".kindnumzh[array_search($resindex,kindnum)]."\n");
                        break;
                    }
                }
                $this->updatePublicData(['status','extra'],0);
                if($this->publicdata['status']['process']==3)
                {
                    $this->updatePublicData(['status','process'],4,''.$this->nick[$index]."进入建设阶段\n");
                }
            break;
            case 'buildhome':
                if($this->publicdata['status']['process']==1)//还处于初期放房子的时候
                {//这时候不需要耗用资源和道路要求，只需要位置合法即可
                    $buildparam=1;
                    if($this->tmpvalue>=count($this->startrolldata))$buildparam=2;//判断是不是第二间屋子，是就给初始资源
                    if($this->buildhome($this->publicdata['node'][$msg['index']]['Pos'],$index,$buildparam)==false)
                    {
                        $ret['head']='error';
                        $ret['showmsg']="这个地方和其他房子太过临近了！\n";
                        $this->room->sendDataByIndex($index,$ret);
                    }else{
                        //已成功部署房子
                        $this->updatePublicData(['status','process'],2);//切换到预部署道路状态
                    }
                }else{
                    //处于平时建村
                    if($this->buildhome($this->publicdata['node'][$msg['index']]['Pos'],$index)==false)
                    {
                        $ret['head']='error';
                        $ret['showmsg']="无法在这里建村，请确认你的资源是否足够，或有没有修好路，或邻近是否有其他村庄\n";
                        $this->room->sendDataByIndex($index,$ret);
                    }
                }
            break;
            case 'buildcity':
                $res=&$this->pridata[$index]['resources'];
                $res['wheat']-=2;
                $res['stone']-=3;
                $this->AddNodeRes($this->publicdata['node'][$msg['index']]['Pos'],$index);
                $this->flushPrivateData($index);
                $this->updatePublicData(['node',$msg['index'],'building'],'city',$this->nick[$index]."的一座大城市拔地而起！\n");
            break;
            case 'buildroad':
                if($this->publicdata['status']['process']==2)//处于预置放路阶段
                {
                    if($this->buildroad($this->publicdata['road'][$msg['index']]['Pos'],$index,1)==false)
                    {
                        $ret['head']='error';
                        $ret['showmsg']="无法在这里修路，一开始的路必须放在刚放的村子的附近\n";
                        $this->room->sendDataByIndex($index,$ret);
                    }else{//路已经成功放下
                        $this->tmpvalue++;
                        if($this->tmpvalue<count($this->startrolldata))
                        {
                            $this->updatePublicData(['status','turn'],$this->startrolldata[$this->tmpvalue]);//控制权转交给下一位玩家
                            $this->updatePublicData(['status','process'],1);
                        }else if($this->tmpvalue<count($this->startrolldata)*2){//处于第二次放房子了
                            $this->updatePublicData(['status','turn'],$this->startrolldata[2*count($this->startrolldata)-1-$this->tmpvalue]);//控制权转交给下一位玩家
                            $this->updatePublicData(['status','process'],1);
                        }else{//大家都放完了
                            $this->tmpvalue=0;//复位tmp变量以便后期使用
                            $this->updatePublicData(['status','process'],3);//切换第一个玩家到准备扔骰子的状态
                        }
                    }
                }else{
                    if($this->buildroad($this->publicdata['road'][$msg['index']]['Pos'],$index)==false)
                    {
                        $ret['head']='error';
                        $ret['showmsg']="这里无法修路！请检查是否有道路连接到此处";
                        $this->room->sendDataByIndex($index,$ret);
                    }
                }
            break;
            case 'getcard':
                if(count($this->devcardpool))
                {
                    $res=&$this->pridata[$index]['resources'];
                    $res['wheat']-=1;
                    $res['stone']-=1;
                    $res['grass']-=1;
                    $cardindex=array_splice($this->devcardpool,rand(0,count($this->devcardpool)-1),1)[0];
                    $res[kindnum[$cardindex]]++;
                    if($cardindex==9)//如果抽到的是胜利点
                    {
                        $this->pridata[$index]['score']++;
                    }
                    $this->flushPrivateData($index,"你获得了一张".kindnumzh[$cardindex]."\n");
                    $this->updatePublicData(['player',$index,'card'],1,"".$this->nick[$index]."抽取了一张发展卡\n",'+');
                }else{
                    $ret['head']='error';
                    $ret['showmsg']="发展卡已被抽取空了。。。";
                    $this->room->sendDataByIndex($index,$ret);
                }
            break;
            case 'usecard':
                $this->pridata[$index]['resources'][kindnum[$msg['index']]]-=1;//扣除卡
                $this->updatePublicData(['player',$index,'card'],1,null,'-');
                switch($msg['index'])
                {
                    case 5:$tips="请选择强盗挪动的位置\n";break;
                    case 6:$tips="请选择需要的资源，决定好后按“获得”键完成\n";break;
                    case 7:$tips="请选择一种你想垄断的资源\n";break;
                    case 8:$tips="请选择你要修的路\n";break;
                }
                if($msg['index']==5)//是出兵
                {
                    $this->updatePublicData(['player',$index,'soldier'],'1',null,'+');
                    $this->updatePublicData(['status','extra'],2,"".$this->nick[$index]."使用了".kindnumzh[$msg['index']]."！！\n");//更新特殊事件
                    if($this->publicdata['player'][$index]['soldier']>$this->maxsoldiersnum)
                    {
                        if($this->publicdata['status']['maxsoldiers']!=-1)
                        {
                            $this->pridata[$this->publicdata['status']['maxsoldiers']]['score']-=2;
                            $this->flushPrivateData($this->publicdata['status']['maxsoldiers'],"你的最大士兵成就被抢走了");
                        }
                        $this->updatePublicData(['status','maxsoldiers'],$index,"".$this->nick[$index]."夺得了最大士兵成就\n");
                        $this->maxsoldiersnum=$this->publicdata['player'][$index]['soldier'];
                        $this->pridata[$index]['score']+=2;
                    }
                }else{
                    $this->updatePublicData(['status','extra'],$msg['index'],"".$this->nick[$index]."使用了".kindnumzh[$msg['index']]."！！\n");//更新特殊事件
                }
                $this->flushPrivateData($index,$tips);
            break;
            case 'cardevent':
                switch($this->publicdata['status']['extra'])
                {
                    case 6://丰收之年
                        $showmsg="".$this->nick[$index]."选择获得了";
                        for($i=0;$i<5;$i++)
                        {
                            $this->pridata[$index]['resources'][kindnum[$i]]+=$msg[$i];
                            if($msg[$i]!=0)$showmsg.="".$msg[$i]."个".kindnumzh[$i].",";
                        }
                        substr($showmsg, 0, -1);
                        $showmsg.="\n";
                        $this->flushPrivateData($index);
                        $this->updatePublicData(['status','extra'],0,$showmsg);//事件完成
                    break;
                    case 7://垄断
                        $ressum=0;
                        for($i=0;$i<count($this->startrolldata);$i++)
                        {
                            if($this->startrolldata[$i]==$index)continue;//不抢自己的
                            $resnum=$this->pridata[$this->startrolldata[$i]]['resources'][kindnum[$msg['index']]];
                            $ressum+=$resnum;
                            $this->pridata[$this->startrolldata[$i]]['resources'][kindnum[$msg['index']]]=0;
                            $this->flushPrivateData($this->startrolldata[$i]);
                            $this->updatePublicData(null,null,"".$this->nick[$this->startrolldata[$i]]."由于垄断损失了".$resnum."个".kindnumzh[$msg['index']]."\n");
                        }
                        $this->pridata[$index]['resources'][kindnum[$msg['index']]]+=$ressum;
                        $this->flushPrivateData($index,"你这次垄断获得了 $ressum 张牌");
                        $this->updatePublicData(['status','extra'],0);//恢复正常状态
                    break;
                    case 8://道路建设
                        if($this->buildroad($this->publicdata['road'][$msg['index']]['Pos'],$index,2))
                        {
                            
                            if($this->tmpvalue==0)
                            {
                                $this->tmpvalue=1;//标记已经修了一条
                                $this->updatePublicData(null,null,$this->nick[$index]."修好了TA的第一条免费路\n");
                                break;
                            }
                            if($this->tmpvalue==1)//两条都修完了
                            {                                
                                $this->updatePublicData(['status','extra'],0,"".$this->nick[$index]."修好了TA的第二条免费路\n");
                                $this->tmpvalue=0;
                            }
                        }else{
                            $ret['head']='error';
                            $ret['showmsg']="这里无法修路！请检查是否有道路连接到此处";
                            $this->room->sendDataByIndex($index,$ret);
                        }
                }
            break;
            case 'change'://与系统进行交换
                $this->pridata[$index]['resources'][kindnum[$msg['input']]]-=$msg['lost'];
                $this->pridata[$index]['resources'][kindnum[$msg['output']]]+=1;
                $this->flushPrivateData($index);
                $ret['head']='msg';
                $ret['showmsg']=$this->nick[$index]."使用".$msg['lost']."个".kindnumzh[$msg['input']]."换取了一个".kindnumzh[$msg['output']]."\n";
                $this->room->broadcast($ret);
            break;
            case 'trade'://玩家贸易
                switch($msg['flag'])
                {
                    case 'open':
                        $this->publicdata['trade']['tradelist']=$msg['tradelist'];
                        $this->updatePublicData(['trade'],$this->publicdata['trade'],"".$this->nick[$index]."发起了交易请求，请查看资源板以决定是否交易\n");
                        $this->rejectednum=0;
                    break;
                    case 'accepted':
                        foreach ($this->publicdata['trade']['tradelist'] as $resindex => $resnum) {
                            $this->pridata[$this->publicdata['status']['turn']]['resources'][kindnum[$resindex]]+=$resnum;
                            $this->pridata[$index]['resources'][kindnum[$resindex]]-=$resnum;
                        }
                        $this->flushPrivateData($index);
                        $this->flushPrivateData($this->publicdata['status']['turn']);
                        $this->updatePublicData(['trade'],null,"".$this->nick[$this->publicdata['status']['turn']]."与".$this->nick[$index]."达成交易，交易关闭\n");
                    break;
                    case 'rejected':
                        $ret['head']='msg';
                        $ret['showmsg']="".$this->nick[$index]."拒绝了你的交易请求\n";
                        $this->room->sendDataByIndex($this->publicdata['status']['turn'],$ret);
                        $this->rejectednum++;
                        if($this->rejectednum==count($this->startrolldata)-1)//所有人都拒绝了这次贸易
                        {
                            $this->updatePublicData(['trade'],null,"没有人愿意交易，交易关闭\n");
                        }
                    break;
                    case 'close':
                        $this->updatePublicData(['trade'],null,"交易被主动关闭\n");
                    break;
                }
            break;
            case 'endturn':
                if($this->publicdata['status']['process']==4)
                {
                    $this->getNextPlayer($index);
                    $this->updatePublicData(['status','process'],3);
                    $ret['head']='msg';
                    $ret['showmsg']=$this->nick[$index]."结束了建设，请".$this->nick[$this->publicdata['status']['turn']]."投骰子\n";
                    $this->room->broadcast($ret);
                }
            break;
            case 'chkwin':
                if($this->pridata[$index]['score']>=10)
                {
                    $this->updatePublicData(['status','process'],0,"游戏结束！胜利者是".$this->nick[$index]."！！！\n该玩家拥有".$this->pridata[$index]['resources']['winpoint']."张胜利点卡\n");
                }else{
                    $this->updatePublicData(null,null,"大家快来看看啊！".$this->nick[$index]."不要脸啊！才".$this->pridata[$index]['score']."分就想宣告胜利了！！！\n");
                }
            break;
            default:
                var_dump($msg);
            break;
        }
    }
}
