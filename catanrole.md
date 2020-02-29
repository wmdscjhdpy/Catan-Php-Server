# CATAN岛游戏传输协议
## 客户端发送协议
### head:roll
客户端请求要骰子
广播一个相同head的数据，并带特征属性
- roll:是一个长度2的array 代表两个骰子数

### head:buildhome || head:buildcity
客户端请求建村/建城
特征属性:
- Pos:请求的位置
如果条件成立，服务器将会广播建村的位置，不成立则返回提示

## 服务器发送协议
### head:startgame
地图初始化数据
会包括publicdata和自己的privatedata 合并发送

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
    - kind:地域资源属性
    - number:概率数字编号
    - robber:是否被强盗压住
- node:是一个索引array，每一个array是一个对象
    其子属性为:
    - Pos:是一个array，拥有三个元素，每一个元素是一个资源点坐标
    - belongto:是一个index，代表谁占领了这个地方。无人占领是-1，
    - building:代表建筑物类型 返回值为blank,home,city
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
    - process:当前游戏流程，1部署第一个房子，2部署第二个房子，3正常回合等待扔骰子，4正常回合建设
    - extra:附加事件，0代表无附加事件，1代表强盗扔牌阶段，2代表移动强盗阶段，3代表选择抽牌阶段
    - turn:当前属于谁的回合。以index标记

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
