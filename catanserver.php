<?php
class SocketService
{
  //private $address = '127.0.0.1';
  private $address = '192.168.2.4';
  private $port = 2333;
  private $_sockets;
  //以下的变量需要自己管理
  public $recv_sock=null;//正在往服务端发数据的客户端列表
  public $recv_data=null;//接收到的数据，索引和recv_sock对齐
  public $send_sock=null;
  public $clients=null;
  public $disconnected_clients=null;
  public function __construct($address = '', $port='')
  {
      if(!empty($address)){
        $this->address = $address;
      }
      if(!empty($port)) {
        $this->port = $port;
      }
      $this->service();
      $this->clients['server'] = $this->_sockets;
  }

  public function service(){
    //获取tcp协议号码。
    $tcp = getprotobyname("tcp");
    $sock = socket_create(AF_INET, SOCK_STREAM, $tcp);
    socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
    if($sock < 0)
    {
      throw new Exception("failed to create socket: ".socket_strerror($sock)."\n");
    }
    socket_bind($sock, $this->address, $this->port);
    socket_listen($sock, $this->port);
    echo "listen on $this->address $this->port ... \n";
    $this->_sockets = $sock;
  }
  /*
  处理一次websocket信息，不包括接收和发送标准信息
  该函数将自动处理新加入的客户端并将其加至客户端列表中
  如果有任意客户端向本服务器发送了数据将会转存在recv_data中，键值是clikey
  如果有任意客户端终止连接，将在disconnected_clients中标记为1
  */
  public function runOnce(){
      $except = NULL;
      $this->recv_data=NULL;
      $this->recv_sock=$this->clients;
      $this->send_sock=NULL;
      socket_select($this->recv_sock,$this->send_sock, $except, NULL);
      foreach ($this->recv_sock as $key => $_sock){
        if($this->_sockets == $_sock){ //判断是不是新接入的socket
          if(($newClient = socket_accept($_sock)) === false){
            die('failed to accept socket: '.socket_strerror($_sock)."\n");
          }
          ///在此处读入socket客户端数据 是初次获取客户端的数据
          $line = trim(socket_read($newClient, 1024));
          if($line === false){
             socket_shutdown($newClient);
             socket_close($newClient);
             continue;
          }  
          $clikey=$this->handshaking($newClient, $line);
          //获取client ip 在服务器因为被代理所以ip不能作为唯一手段
          //socket_getpeername ($newClient, $ip);
          $this->clients[$clikey] = $newClient;
          echo "Client clikey:{$clikey}  \n";
        } else {//老客户端的数据
          $byte = socket_recv($_sock, $buffer, 2048, 0);//TODO:如果发生接受错误，将得到null，记得处理
          if($byte < 9 && $byte!=0)//断开连接标识符 记得处理 如果发现断联不成功可以把7改成9
          {
            $this->disconnected_clients[$key]=1;//标记断线
            echo "$key disconnected. exit msg:".bin2hex($buffer)."\n";
            socket_shutdown($_sock);
            socket_close($_sock);
            continue;
          }else if($byte==0){//在测试时发现客户端断开连接后还会发送一个空包，因此在此过滤
            continue;
          }
          $this->recv_data[$key] = $this->message($buffer);
        }
      }
  }

  /**
   * 握手处理
   * @param $newClient socket
   * @return int 接收到的信息
   */
  public function handshaking($newClient, $line){

    $headers = array();
    $lines = preg_split("/\r\n/", $line);
    foreach($lines as $line)
    {
      $line = rtrim($line);
      if(preg_match('/^(\S+): (.*)$/', $line, $matches))
      {
        $headers[$matches[1]] = $matches[2];
      }
    }
    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
      "Upgrade: websocket\r\n" .
      "Connection: Upgrade\r\n" .
      "WebSocket-Origin: $this->address\r\n" .
      "WebSocket-Location: ws://$this->address:$this->port/websocket/websocket\r\n".
      "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    if(socket_write($newClient, $upgrade, strlen($upgrade)))
    {
      return $secKey;
    }
  }

  /**
   * 解析接收数据
   * @param $buffer
   * @return null|string
   */
  public function message($buffer){
    $len = $masks = $data = $decoded = null;
    $len = ord($buffer[1]) & 127;
    if ($len === 126) {
      $masks = substr($buffer, 4, 4);
      $data = substr($buffer, 8);
    } else if ($len === 127) {
      $masks = substr($buffer, 6, 4);
      $data = substr($buffer, 10);
    } else {
      $masks = substr($buffer, 2, 4);
      $data = substr($buffer, 6);
    }
    for ($index = 0; $index < strlen($data); $index++) {
      $decoded .= $data[$index] ^ $masks[$index % 4];
    }
    return $decoded;
  }

  /**
   * 发送数据
   * @param $newClinet 新接入的socket
   * @param $msg  要发送的数据
   * @return int|string
   */
  public function send($newClinet, $msg){
    $msg = $this->frame($msg);
    socket_write($newClinet, $msg, strlen($msg));
  }
  

  public function frame($s) {
    if(strlen($s)<126)
    {
      return "\x81" . chr(strlen($s)) . $s;
    }else if(strlen($s)<0xffff){
      return "\x81" . chr(126) . chr(strlen($s) >>8) . chr(strlen($s) & 0xff) .$s;
    }
  }

  /**
   * 关闭socket
   */
  public function close(){
    return socket_close($this->_sockets);
  }
}

//$sock = new SocketService();
//$sock->run();