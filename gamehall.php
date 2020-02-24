<?php
require_once './catanserver.php';
require_once './gameroom.php';
$ser=new SocketService();
$hall=new gamehall($ser);
class gamehall{
    public $roomdata=null;
    public $ser;
    public function __construct($ser)
    {
        $this->ser=$ser;
    }
    ///输入ip，返回房间号
    public function getInfoFromIp($ip)
    {
        if(is_array($this->roomdata))
        foreach ($this->roomdata as $roomnum => $room) {
            $i=array_search($ip,$room->gameid);
            if($i!==false)//刚刚掉线的玩家在这间房
            {
                $ret['roomnum']=$roomnum;
                $ret['index']=$i;
                return $ret;
            }
        }
        return null;
    }
    public function leaveroom($ip)
    {
        $roomnum=$this->getInfoFromIp($ip)['roomnum'];
        if($roomnum==null)return;//此人不在任何房间里 直接关闭
        $index=@array_search($ip,$this->roomdata[$roomnum]->gameid);//找出该ip的索引号
        $this->roomdata[$roomnum]->gameid[$index]=null;//清除该id
        $i=0;
        for(;$i<MaxPlayer;$i++)//判断是不是空房间
        {
            if($this->roomdata[$roomnum]->gameid[$i]!=null)break;
        }
        //此时i=一个存活的人的index或Maxplayer
        if($i==MaxPlayer)
        {//删除该房间
            delItemByKey($this->roomdata,$roomnum);
            return;
        }else if($this->roomdata[$roomnum]->hostindex==$index)
        {//转移房主权利
            $this->roomdata[$roomnum]->hostindex=$i;
            $hostmsg['head']='priviliege';
            $hostmsg['showmsg']="【系统提示】由于前房主离开，你现在是新的房主\n";
            $this->roomdata[$roomnum]->sendDataByIndex($i,$hostmsg);
        }
        $retval['head']='leave';
        $retval['showmsg']="【系统提示】玩家".$this->roomdata[$roomnum]->nicklist[$index]."离开了房间\n";
        $retval['index']=$index;
        $this->roomdata[$roomnum]->broadcast($retval);
    }
    public function dataHandle($rawmsg,$ip)
    {
        $msg=json_decode($rawmsg,true);//第二个参数设为true能把msg变成一个数组
        //var_dump($msg);
        $retval=null;//对发信息过来的人返回的具体信息
        $bc=null;//当需要广播信息时在此填入值
        $proessed=null;//判断msg是否被处理
        //房间管理用的switch
        switch($msg['head'])
        {
            case 'enter'://进入房间的事件
                $proessed=1;
                if(!isset($this->roomdata[(string)$msg['room']]))
                {
                    //如果不存在该房间则创建该房间
                    $this->roomdata[$msg['room']]=new gameroom($this->ser);
                    $retval['head']='priviliege';
                    $this->roomdata[$msg['room']]->hostindex=0;//房主是第一位
                    $retval['showmsg']="【系统提示】您现在是房主 待所有在场人准备完毕后你可以点击“开始游戏”\n";//
                }
                //var_dump($this->roomdata[$msg['room']]);
                $seat=$this->roomdata[$msg['room']]->enterRoom($ip,$msg['nickname']);
                if($seat==-1)
                {
                    $retval['head']='error';
                    $retval['showmsg']="【系统提示】当前房间已满！请选择其他房间\n";
                }else if($seat==-2){
                    $retval['head']='error';
                    $retval['showmsg']="【系统提示】当前房间正在游玩！请选择其他房间\n";
                }else{
                    //如果房间有其他人则向该用户给出其他用户的信息
                    $this->roomdata[$msg['room']]->sendOtherUserInfo($seat);
                    $bc['head']='enter';
                    $bc['index']=$seat;
                    $bc['nickname']=$msg['nickname'];
                    $bc['showmsg']="【系统提示】欢迎".$msg['nickname']."进入房间\n";
                }
            break;
            case 'ready':
                $proessed=1;
                $info=$this->getInfoFromIp($ip);
                $this->roomdata[$info['roomnum']]->gameready[$info['index']]=$msg['flag'];
                $bc['head']='ready';
                $bc['index']=$info['index'];
                $bc['flag']=$msg['flag'];
            break;
            case 'leave':
                //注意房主权利转移
                $proessed=1;
                $this->leaveroom($ip);
                $retval['head']='leavesuccess';
            break;
            case 'gameon':
                $proessed=1;
                $nowplayer=array();
                $info=$this->getInfoFromIp($ip);
                $i=0;
                for(;$i<MaxPlayer;$i++)
                {
                    if($this->roomdata[$info['roomnum']]->gameid[$i]!=null)
                    {
                        if($i==$this->roomdata[$info['roomnum']]->hostindex)//
                        {
                            array_push($nowplayer,$this->roomdata[$info['roomnum']]->hostindex);//将房主添加到参与游戏的玩家列表
                            continue;//房主不需要准备
                        }
                        if($this->roomdata[$info['roomnum']]->gameready[$i]==1)array_push($nowplayer,$i);//将该玩家添加到参与游戏的玩家列表内
                        else {
                            $i=-1;
                            break;//有玩家没准备好
                        }
                    }
                }
                if($i==-1)
                {
                    $retval['head']='error';
                    $retval['showmsg']="【系统提示】还有玩家没有准备好！\n";
                    break;
                }else if (count($nowplayer)<MinPlayer) {
                    $retval['head']='error';
                    $retval['showmsg']="【系统提示】人数不足以开启这个游戏！\n";
                    break;
                }
                //检验通过，开始游戏
                $this->roomdata[$info['roomnum']]->data->startgame($nowplayer);
                $this->roomdata[$info['roomnum']]->isplaying=1;//表示该房间进入游玩模式
            break;
        }
        if(!$proessed)
        {//如果还未处理的话 说明是游戏数据，调用房间内的data里的handleGame方法
            $info=$this->getInfoFromIp($ip);
            $this->roomdata[$info['roomnum']]->data->handleGame($msg,$info['index']);
        }
        //信息分发
        if($retval)
        {
            //因为不在房间内用的原生发送 所以需要转码
            $json=json_encode($retval,JSON_UNESCAPED_UNICODE);
            $this->ser->send($this->ser->clients[$ip],$json);
        }
        if($bc)
        {
            $this->roomdata[$this->getInfoFromIp($ip)['roomnum']]->broadcast($bc);
        }
    }
}

while (true) {
    $ser->runOnce();
    //处理断线情况
    if(is_array($ser->disconnected_clients))
    foreach ($ser->disconnected_clients as $ip => $value) {
        //删除断线信息
        $hall->leaveroom($ip);
        delItemByKey($ser->disconnected_clients,$ip);
        delItemByKey($ser->clients,$ip);
    }
    //处理正常情况
    if(is_array($ser->recv_data))
    foreach ($ser->recv_data as $ip => $data) {
        $hall->dataHandle($data,$ip);
    }
}
