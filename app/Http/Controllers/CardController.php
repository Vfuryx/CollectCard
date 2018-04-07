<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\MPController;
use Illuminate\Support\Facades\Redis;
use App\Model\Member;
use App\Model\MemberInfo;
use App\Model\Reward;
use App\Model\MemberReward;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    const HOST_REDIS = 'collect_redis';

    /**
     * 登陆获取token
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {

        $js_code = $nick_name = $gender = $city = $province = 0;

        //验证body
        $this->validate($request, [
            "js_code" => "required",
            "nick_name" => "required",
            "gender" => ['required', Rule::in([0, 1, 2])],
            "city" => "required",
            "province" => "required"
        ]);

        extract($request->input());

        //获取openid
        $mp = new MPController();
        $appid = env('MP_APPID');
        $secret = env('MP_SECRET');
        $js_code = $js_code;
        $apiUrl = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$js_code}&grant_type=authorization_code";
        $res = $mp->httpGet($apiUrl);
        //获取openID
        if (!isset(json_decode($res)->openid))
            return json_encode(['err' => 1001, 'msg' => '无法获取token']);

        $openId = json_decode($res)->openid;

        //查询数据库
        $member = Member::where('open_id', $openId);
        if ($member->count() === 1) {
            $member = $member->first();
        } else {
            $member = Member::create(['name' => $nick_name, 'open_id' => $openId,])->first();
            $memberId = $member->id;
            MemberInfo::create(['uid' => $memberId, 'sex' => $gender, 'city' => $city, 'province' => $province]);
        }
        $token = bcrypt($js_code . time());
        $tokenInfo = serialize(['id' => $member->id, 'cards' => [$member->card1, $member->card2, $member->card3, $member->card4, $member->card5, $member->level]]);
        //设置token缓存
        Redis::setex(self::HOST_REDIS . $token, 3 * 24 * 60 * 60, $tokenInfo);

        $res = [
            'token' => $token,
            'get_cards' => [
                $member->card1, $member->card2, $member->card3, $member->card4, $member->card5
            ],
            'level' => $member->level
        ];

        return json_encode($res);
    }


    /**
     * 有token直接获取信息
     * @param Request $request
     * @return string
     */
    public function show(Request $request)
    {
        //获取token
        $token = '';

        $this->validate($request, [
            'token' => 'required'
        ]);

        extract($request->input());

        //验证token 获得数据
        if (!$redis = Redis::get(self::HOST_REDIS . $token)) {
            //0 返回错误信息 token过期
            return json_encode(['err' => 1003, 'msg' => 'token已过期']);
        }
        $redis = unserialize($redis);

        $res = [
            "get_cards" => [
                $redis['cards'][0], $redis['cards'][1], $redis['cards'][2], $redis['cards'][3], $redis['cards'][4]
            ],
            "level" => $redis['cards'][5]
        ];

        return response()->json($res);
    }


    /**
     * 扫描获取卡片
     * @param Request $request
     * @return string
     */
    public function card(Request $request)
    {

        //获取token img
        $token = $image = '';

        $this->validate($request, [
            'token' => 'required',
            'image' => 'file|image|max:2048'
        ]);

        extract($request->input(), EXTR_OVERWRITE);
        extract($request->file(), EXTR_OVERWRITE);


        //验证token 获得 uid
        if (!$redis = Redis::get(self::HOST_REDIS . $token)) {
            //0 返回错误信息 token过期
            return json_encode(['err' => 1003, 'msg' => 'token已过期']);
        }

        //查库获取用户信息
        $redis = unserialize($redis);

        //调用识别图片API
        $obj = new SearchCarSDK();
        $res = $obj->getCarData($image);
        $res = json_decode($res,1);

        if (!(isset($res['results'][0]['name'])&&$res['results'][0]['name']=='fu' && $res['results'][0]['score'] > 0.9)){
            //无法识别
            return json_encode(['err' => 1004, 'msg' => '图片扫描失败']);
        }

        $image->storeAs('fu',md5($image->getClientOriginalName().time().rand()).'.'.$image->getClientOriginalExtension());

        //计算获取图片
        $data = [
            0 => ['id' => 1, 'name' => 'card1', 'weight' => 50 + 10 * $redis['cards'][5]],
            1 => ['id' => 2, 'name' => 'card2', 'weight' => 50 + 10 * $redis['cards'][5]],
            2 => ['id' => 3, 'name' => 'card3', 'weight' => 50 + 10 * $redis['cards'][5]],
            3 => ['id' => 4, 'name' => 'card4', 'weight' => 50 + 10 * $redis['cards'][5]],
            4 => ['id' => 5, 'name' => 'card5', 'weight' => 5 - 1 * $redis['cards'][5]]
            ];
        $data2 = [
            0 => ['id' => 1, 'name' => 'card1', 'weight' => 1],
            1 => ['id' => 2, 'name' => 'card2', 'weight' => 1],
            2 => ['id' => 3, 'name' => 'card3', 'weight' => 1],
            3 => ['id' => 4, 'name' => 'card4', 'weight' => 1],
        ];

        $myCardSum = $redis['cards'][0] + $redis['cards'][1] + $redis['cards'][2] + $redis['cards'][3] + $redis['cards'][4];

        //是否达成一套
        if ($redis['cards'][0] && $redis['cards'][1] && $redis['cards'][2] && $redis['cards'][3] && $redis['cards'][4]) {
            return json_encode(['err' => 1004, 'msg' => '请去合成']);
        }

        $card = $this->rand($data)['id'];

        //验证血统
        if (($myCardSum === 12 + 12 * $redis['cards'][5] && $redis['cards'][4] === 0) && $card !== 5) {//非洲人保底
            $card = 5;
        } elseif ($redis['cards'][4] === 1) {//贵族vip
            $card = $this->rand($data2)['id'];
        }

        //入库
        $member = Member::find($redis['id']);
        $member->update(['card' . $card => $member['card' . $card] += 1]);
        $redis['cards'][$card-1] += 1;
        Redis::setex(self::HOST_REDIS . $token, 3 * 24 * 60 * 60, serialize($redis));

        //组装
        $res = [
            "card" => $card,
            "get_cards" => [
                $redis['cards'][0], $redis['cards'][1], $redis['cards'][2], $redis['cards'][3], $redis['cards'][4]
            ]
        ];

        return response()->json($res);
    }


    /**
     * @param $data
     * @return mixed
     */
    public function rand($data)
    {
        $weight = 0;
        $sumData = [];

        foreach ($data as $key => $value) {
            $weight += $value['weight'];
            for ($i = 0; $i < $value['weight']; ++$i) {
                $sumData[] = $value;
            }
        }

        shuffle($sumData);

        $index = mt_rand(0, $weight - 1);

        return $sumData[$index];
    }

    /**
     * 合成卡片获得奖励
     * @param Request $request
     * @return string
     */
    public function reward(Request $request)
    {

        //获取token img
        $token = '';

        $this->validate($request, [
            'token' => 'required'
        ]);
        extract($request->input());

        //验证token 获得 uid

        if (!$redis = Redis::get(self::HOST_REDIS . $token)) {
            //0 返回错误信息 token过期
            return json_encode(['err' => 1003, 'msg' => 'token已过期']);
        }
        $redis = unserialize($redis);
//        $member = Member::find($id);

        //验证卡片是否收集齐
        if (!($redis['cards'][0] && $redis['cards'][1] && $redis['cards'][2] && $redis['cards'][3] && $redis['cards'][4])) {
            //0 返回错误信息 token过期
            return json_encode(['err' => 1002, 'msg' => '卡片没有集齐一套']);
        }



        //获取奖励
        switch ($redis['cards'][5]) {
            case 0:
                $reward = Reward::find(1);
                $res = ['code' => $reward->id, 'msg' => $reward->prize];
                break;
            default:
                $reward = Reward::find(2);
                $res = ['code' => $reward->id, 'msg' => $reward->prize];
                break;
        }

        //记录奖励
        MemberReward::create(['reward' => $reward->id, 'uid' => $redis['id']]);

        //入库//减一张卡片
        $member = Member::find($redis['id']);
        for ($i = 0 ; $i < 5 ; ++$i){
            $redis['cards'][$i] -= 1;
        }
        $redis['cards'][5] += 1;
        $member->update([
            'card1' => $redis['cards'][0],
            'card2' => $redis['cards'][1],
            'card3' => $redis['cards'][2],
            'card4' => $redis['cards'][3],
            'card5' => $redis['cards'][4],
            'level' => $redis['cards'][5]
        ]);
        Redis::setex(self::HOST_REDIS . $token, 3 * 24 * 60 * 60, serialize($redis));
        return json_encode($res);
    }


}
