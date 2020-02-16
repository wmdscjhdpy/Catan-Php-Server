<?php
class SocketService
{
  private $address = 'localhost';
  private $port = 2333;
  private $_sockets;
  public function __construct($address = '', $port='')
  {
      if(!empty($address)){
        $this->address = $address;
      }
      if(!empty($port)) {
        $this->port = $port;
      }
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

  public function run(){
    $this->service();
    $clients[] = $this->_sockets;
    while (true){
      $changes = $clients;
      $write = NULL;
      $except = NULL;
      socket_select($changes, $write, $except, NULL);
      foreach ($changes as $key => $_sock){
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
          $this->handshaking($newClient, $line);
          //获取client ip
          socket_getpeername ($newClient, $ip);
          $clients[$ip] = $newClient;
          echo "Client ip:{$ip}  \n";
          echo "Client msg:{$line} \n";
        }else {//老客户端的数据
          $byte = socket_recv($_sock, $buffer, 2048, 0);
          if($byte < 7) continue;//断开连接标识符
          $msg = $this->message($buffer);
          //在这里业务代码
          echo "{$key} clinet msg:",$msg,"\n";
          fwrite(STDOUT, 'Please input a argument:');
          $response = trim(fgets(STDIN));
          $this->send($_sock, $response);
          echo "{$key} response to Client:".$response,"\n";
        }
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
    return socket_write($newClient, $upgrade, strlen($upgrade));
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
      $masks = substr($buffer, 10, 4);
      $data = substr($buffer, 14);
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
    $a = str_split($s, 125);
    if (count($a) == 1) {
      return "\x81" . chr(strlen($a[0])) . $a[0];
    }
    $ns = "";
    foreach ($a as $o) {
      $ns .= "\x81" . chr(strlen($o)) . $o;
    }
    return $ns;
  }

  /**
   * 关闭socket
   */
  public function close(){
    return socket_close($this->_sockets);
  }
}

$sock = new SocketService();
$sock->run();