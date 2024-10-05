<?php

namespace BjornTech\Common\Hybrid;
use BjornTech\Common\ActionScheduler\ActionSchedulerTrait;
use BjornTech\Common\SingletonTrait;

trait CommonTrait {
    //use LoggerTrait; - This is not needed because it is already included in the ActionSchedulerTrait
    use ActionSchedulerTrait;
    use SingletonTrait;
}