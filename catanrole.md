# CATAN岛游戏传输协议
## 客户端发送协议
### head:roll
客户端请求要骰子
广播一个相同head的数据，并带特征属性
- roll:是一个长度2的array 代表两个骰子数

### head:buildhome || head:buildcity || head:buildroad
客户端请求建村/建城
特征属性:
- index:请求的位置数据在list中的index
如果条件成立，服务器将会广播建村的位置，不成立则返回提示

### head:change
客户端申请换资源
特征属性：
- lost:交换比例，即交换时需要用多少个来交换
- input:用来交换的资源 资源索引
- output:交换给予的资源 资源索引
成功后会更新牌数据，不成功将会返回提示

### head:trade
客户端进行贸易通讯
- flag:操作标志可能的值有
    - open:打开贸易
    - accepted:接受并关闭贸易 服务器接收到后会处理贸易，并广播贸易信息并关闭贸易
    - rejected:拒绝并关闭贸易 服务器向交易主提示拒绝，该玩家将自己的gamemap设置为贸易关闭
- tradelist:贸易详情 服务器接受到后会填充gamemap的trade使其符合要求 仅当flag=open时可用

### head:endturn
客户端结束回合
无特征属性
将会轮到下一个人投骰子

### head:discard
客户端响应强盗逻辑进行弃牌
特征属性：键为资源index，值为丢弃数量
如：[0]=1 代表丢弃资源索引是0的资源1个
弃牌后本地的extra将会变为0
所有客户端响应后extra将会改变为下一阶段

### head:moverob
客户端进行强盗的移动
特征属性
- index:将移动的hexagon的index
客户端响应后extra将会改变

### head:robacard
客户端通过强盗抽取其他玩家的牌
特征属性
- index:将抽取的玩家的index
响应后extra将会改变

### head:getcard
客户端抽卡
成功后将会分发一张卡到数据表上

### head:usecard
客户端使用卡片
- index:使用的卡片index
成功后将会返回对应的extra标志位以进行接下来所需的数据传输

### head:cardevent
客户端执行所使用的卡片的流程
分所使用卡的不同而返回的情况不同。
#### 丰收之年（extra==6）特征属性：
键为资源index，值为获得数量
如：[0]=1 代表获得资源索引是0的资源1个
#### 垄断（extra==7）特征属性：
- index:代表需要垄断的资源类型
#### 道路建设（extra==8）特征属性：
- index:代表修的路的index
**注意：道路建设的cardevent会请求两次以修两条路**

### head:chkwin
客户端请求进行胜利检查
如果成功就返回胜利咯，还有啥好说的

## 服务器发送协议
### head:startgame
地图初始化数据
会包括publicdata和自己的privatedata 合并发送
### head:msg
纯消息
带一个showmsg

### head:update
地图更新数据
特征属性:
- type:类型，值可以是private和public
- key:不定长array,从0开始每个值就是递进的键名 注意 可以为空，为空则表示全部数据刷新
- data:需要更新的数据的值

## 服务器数据存储格式
### publicdata
**所有游戏公用元素 所有玩家在游戏开始时都会接收到**
特征属性:
- hexagon:是一个索引array，每一个array是一个对象
    其子属性为：
    - Pos:资源点坐标，有x和y属性
    - kind:地域资源属性 为字符串
    - number:概率数字编号   int
    - robber:是否被强盗压住 boolen
- node:是一个索引array，每一个array是一个对象
    其子属性为:
    - Pos:是一个array，拥有三个元素，每一个元素是一个资源点坐标
    - belongto:是一个index，代表谁占领了这个地方。无人占领是-1，
    - building:代表建筑物类型 返回值为blank,home,city
    - port:代表港口类型 -1为无效，0-4对应资源索引 5代表任意3:1港口
- road:是一个索引array，每一个array是一个对象
    其子属性为:
    - Pos:是一个道路坐标，是一个array，拥有两个元素，每一个元素是一个资源点坐标
    - belongto:是一个index，代表谁占领了这个地方。无人占领是-1，
- player:是玩家公共信息，是一个array，array的index代表玩家index
    其子属性为:
    - status:代表该玩家的状态，可以是null代表不参与游戏，online代表正处于游戏中，offline代表离线
    - resources:代表该玩家的资源牌数
    - card:代表该玩家目前未使用的发展卡数目
    - soldier:代表该玩家目前已出的兵的数目
- status:游戏状态信息 应有一个完整的状态机
    其子属性为:
    - process:当前游戏流程，1部署初期房子，2部署初期路，3正常回合等待扔骰子，4正常回合建设，0游戏结束
    - extra:附加事件，0代表无附加事件，1代表强盗扔牌阶段，2代表移动强盗，3代表强盗抽牌阶段，4代表玩家贸易中，8道路建设铺路中，6代表丰收之年判定中，7代表垄断判定中，发展卡判定事件index和资源index保持一致
    - turn:当前属于谁的回合。以index标记
    - maxsoldiers:拥有最大士兵的玩家索引号，初始值-1
    - maxroads:拥有最大道路的玩家索引号，初始值-1
- trade:交易信息
    - tradelist:是一个**数字索引**array，数字代表资源index，值代表想交换的数量，正数代表获得，负数代表交出，tradelist永远以当前回合玩家作为第一人称。
### pridata
**玩家自身的私有数据，包括资源牌数量及类型，发展卡类型即数量**
- sources:是一个键值array，其键代表类型，值代表数量
    - forest
    - wheat
    - grass
    - stone
    - iron
    - solders:士兵
    - harvest:丰收之年
    - monopoly:垄断
    - roadbuilding:道路建设
    - winpoint:胜利点
- score:代表该玩家目前的胜利点得分
- port:代表玩家拥有的港口类型，是一个array