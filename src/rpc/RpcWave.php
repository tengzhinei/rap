<?php
/**
 * User: jinghao@duohuo.net
 * Date: 18/12/7
 * Time: 下午4:15
 * Link:  http://magapp.cc
 * Copyright:南京灵衍信息科技有限公司
 */

namespace rap\rpc;

use rap\aop\JoinPoint;
use rap\rpc\client\RpcClient;
use rap\rpc\client\RpcClientException;
use rap\swoole\pool\Pool;

/**
 * 主要实现了 RCP 远程调用和熔断器的功能
 */
class RpcWave {

    const FUSE_STATUS_OPEN      = 1;
    const FUSE_STATUS_HALF_OPEN = 2;
    const FUSE_STATUS_CLOSE     = 3;

    /**
     * @var Rpc
     */
    private $rpc;

    /**
     * RpcWave _initialize.
     *
     * @param Rpc $rpc
     */
    public function _initialize(Rpc $rpc) {
        $this->rpc = $rpc;
    }

    public function before(JoinPoint $point) {
        $method = $point->getMethod();//对应的反射方法
        /* @var $obj RpcSTATUS */
        $obj = $point->getObj();//对应包装对象
        /* @var $client RpcClient */
        $client = $this->rpc->getRpcClient($point->getOriginalClass());
        try{
            $fuseConfig = $client->fuseConfig();
            //熔断器开启
            if ($obj->FUSE_STATUS == RpcWave::FUSE_STATUS_OPEN) {
                //熔断30s
                if (time() - $obj->FUSE_OPEN_TIME < $fuseConfig[ 'fuse_time' ]) {
                    //使用服务降级
                    return null;
                }
                $obj->FUSE_STATUS = RpcWave::FUSE_STATUS_HALF_OPEN;
                //半开
            }
            if ($obj->FUSE_STATUS == RpcWave::FUSE_STATUS_HALF_OPEN) {
                $obj->FUSE_STATUS = RpcWave::FUSE_STATUS_OPEN;
                try {
                    $args = $point->getArgs();
                    $request = request();
                    $header=[];
                    if ($request) {
                        $header = request()->header();
                    }
                    $value = $client->query($point->getOriginalClass(), $method->getName(), $args,$header);
                    $obj->FUSE_STATUS = RpcWave::FUSE_STATUS_CLOSE;
                    if ($value == null) {
                        $value = true;
                    }
                    return $value;
                } catch (RpcClientException $exception) {
                    $obj->FUSE_STATUS = RpcWave::FUSE_STATUS_OPEN;
                    return null;
                }
            } else {
                try {
                    $args = $point->getArgs();
                    $request = request();
                    $header=[];
                    if ($request) {
                        $header = $request->header();
                        $header['x-session-id']=$request->session()->sessionId();
                    }
                    $value = $client->query($point->getOriginalClass(), $method->getName(), $args,$header);
                    if ($obj->FUSE_FAIL_COUNT) {
                        $obj->FUSE_FAIL_COUNT = 0;
                    }
                    if ($value == null) {
                        $value = true;
                    }
                    return $value;
                } catch (RpcClientException $exception) {
                    $obj->FUSE_FAIL_COUNT++;
                    //失败一定次数开启熔断
                    if ($obj->FUSE_FAIL_COUNT > $fuseConfig[ 'fuse_fail_count' ]) {
                        $obj->FUSE_OPEN_TIME = time();
                        $obj->FUSE_STATUS = RpcWave::FUSE_STATUS_OPEN;
                    }
                    //TODO 日志记录
                    //失败就服务降级
                    return null;
                }
            }
        }finally{
            Pool::release($client);
        }
        return null;
    }


}