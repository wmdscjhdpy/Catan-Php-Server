# CATAN岛游戏传输协议
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