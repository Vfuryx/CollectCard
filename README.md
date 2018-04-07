## 配置

1. 下载配置文件

```
composer update
```

2. 创建.env文件

3. 将.env.example文件的内容复制到之前创建的,nev文件

3.1 .env添加小程序配置

```
MP_APPID=
MP_SECRET=
```

4. 配置密钥

```
php artisan key:generate
```


## 接口

中间件 判断是否关注

#### 登陆获取token
```
/v1/user
```
reqest
```
{
    js_code: "js_code",
    nick_name: "nickName",
    gender: 1,
    city: "广州",
    province: "广东省",
}
```
response
```
{
  "token": "token",
  "get_cards": [
      1,0,1,2,3
  ],
  "level": 0
}
```

#### 有token直接获取信息
```
/v1/resources
```
reqest
```
{
    "token": "token" 
}
```
response
```
{
    "get_cards": [
          1,0,1,2,3
      ],
    "level": 0
}
```


#### 扫描获取卡片（后期增加判断调用接口次数redis）
```
/v1/card
```
reqest
```
{
    "token": "token"，
    //上传的图片
    "image": img
}
```
response


卡片标识

<!-- 
这个版本不用看 

一卡：00001 -> 1  = 1

二卡：00010 -> 2  = 2

三卡：00100 -> 3  = 4

四卡：01000 -> 4  = 8

五卡：10000 -> 5  = 16

二张卡: 第一张 和 第三张 00101 = 5 
二张卡: 第一张 和 第五张 10001 = 17  -->

```
{
  "card": "1",
  "get_cards": [
      1,0,1,2,3
  ]
}

card: 获得新卡牌
get_cards: 卡片的存量 顺序1-5

```


#### 合成卡片获得奖励
```
/v1/reward
```
reqest
```
{
    "token": "token"
}
```
response
```
{
    "code": "1",
    "msg": ""
}
```


## 数据库

### member表

```
id          mediumint(8)  auto_increment    comment id
name        varchar(15)                     comment 用户昵称
open_id     varchar(30)                     comment 用户openID 
card1       int                             comment 卡片1  
card2       int                             comment 卡片2 
card3       int                             comment 卡片3 
card4       int                             comment 卡片4 
card5       int                             comment 卡片5 
level       tinyint(1)                      comment 合成次数（等级）
```

### member_info表

```
id          mediumint(8)  auto_increment    comment id
uid         int                             comment 用户id
sex         tinyint(1)                      comment 性别（1:男，2:女,0:未知）
city        varchar(5)                      comment 城市
province    varchar(5)                      comment 省份
```

### reward表

```
id          mediumint(8)  auto_increment    comment id 
prize       varchar(10)                     comment 奖励内容 


```

### member_reward表

```
id          mediumint(8)  auto_increment    comment id  
reward_id   int(8)                          comment reward表id 
uid         int(8)                          comment member表id

```


## error 代码

code | msg
---|---
1001 | 无法获取token
1002 | 卡片没有集齐一套
1003 | token已过期
1004 | 请去合成


```
{
    'err' : 1001, 
    'msg' : '无法获取token'
}


```
