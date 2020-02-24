# CATAN岛游戏传输协议
## 客户端发送协议
### head:roll
客户端请求要骰子
返回一个相同head的数据，并带特征属性
- roll:是一个长度2的array 代表两个骰子数

## 服务器发送协议
### head:startgame
地图初始化数据
特征属性:
- hexagon:是一个索引array，每一个array是一个对象
    其子属性为：
    - Pos:资源点坐标，有x和y属性
    - kind:地域资源属性
    - number:概率数字编号
- node:是一个索引array，每一个array是一个节点坐标，是一个array，拥有三个元素，每一个元素是一个资源点坐标
- road:是一个索引array，每一个array是一个道路坐标，是一个array，拥有两个元素，每一个元素是一个资源点坐标

### head:startgame
现在欲将startgame的数据包括所有游戏公用元素
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
    - belongto:是一个index，代表谁占领了这个地方。无人占领是-1
    - building:代表建筑物类型 返回值为blank,home,city
- road:是一个索引array，每一个array是一个对象
    其子属性为:
    - Pos:是一个道路坐标，是一个array，拥有两个元素，每一个元素是一个资源点坐标
    - belongto:是一个index，代表谁占领了这个地方。无人占领是-1
- player:是玩家公共信息，是一个array，array的index代表玩家index
    其子属性为:
    - status:代表该玩家的状态，可以是null代表不参与游戏，online代表正处于游戏中，offline代表离线
    - resources:代表该玩家的资源牌数
    - card:代表该玩家目前未使用的发展卡数目
    - soldier:代表该玩家目前已出的兵的数目
- status:游戏状态信息
    其子属性为:
    - process:当前游戏流程，1部署第一个房子，2部署第二个房子，3正常回合等待扔骰子，4正常回合建设
    - turn:当前属于谁的回合。以index标记
