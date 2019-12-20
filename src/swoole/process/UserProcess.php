<?php
namespace rap\swoole\process;

use rap\aop\Event;
use rap\log\Log;
use rap\ServerEvent;
use rap\swoole\CoContext;

abstract class UserProcess
{
    private $process;


    public function getProcess()
    {
        return $this->process;
    }


    public static function start()
    {
        Event::add(ServerEvent::onBeforeServerStart, get_called_class(), 'register');
    }

    public function register($server)
    {
        //定时任务调度
        $process = new \Swoole\Process(function ($process) {
            $this->process = $process;
            go(function () {
                defer(function () {
                    //释放下
                    CoContext::getContext()->release();
                });
                Event::trigger(ServerEvent::onServerWorkStart);
                Log::error('onProcessStarted');
                $this->onProcessStarted();
            });
        });
        $server->addProcess($process);
    }

    public abstract function onProcessStarted();


}