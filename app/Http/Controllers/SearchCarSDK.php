<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;

class SearchCarSDK extends Controller
{
    public function getAccessToken()
    {
        $url = 'https://aip.baidubce.com/oauth/2.0/token';
        $post_data['grant_type'] = 'client_credentials';
        $post_data['client_id'] = env('BAIDU_API_KEY');
        $post_data['client_secret'] = env('BAIDU_SECRET_KEY');
        $o = "";
        foreach ($post_data as $k => $v) {
            $o .= "$k=" . urlencode($v) . "&";
        }
        $post_data = substr($o, 0, -1);

        $postUrl = $url;
        $curlPost = $post_data;

        $curl = curl_init();//初始化curl
        curl_setopt($curl, CURLOPT_URL, $postUrl);//抓取指定网页
        curl_setopt($curl, CURLOPT_HEADER, 0);//设置header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_POST, 1);//post提交方式
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($curl);//运行curl
        curl_close($curl);//关闭curl

        $json = json_decode($data);//转为Json数据


        $access_token = $json->access_token;
        $expires_in = $json->expires_in;

        Redis::setex('msg.bexas.cn:access_token', $expires_in, $access_token);

    }

    public function getCarData($file = '')
    {

        if (empty($file)) {
            return false;
        }

        if (!Redis::get('msg.bexas.cn:access_token')) {
            $this->getAccessToken();
        }


        $postUrl = 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/classification/fuTest?access_token=' . Redis::get('msg.bexas.cn:access_token');


        //$file = $request->file('file');

        //将临时路径中的图片变成对象
        $img = file_get_contents($file);
        //将图片对象转换成BASE64
        $img = base64_encode($img);
        //属性

        $curlPost = json_encode([
            'image' => $img,
            'top_num' => 3
        ]);


        // 初始化curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $postUrl);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);

        // post提交方式
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        // 运行curl
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }
}