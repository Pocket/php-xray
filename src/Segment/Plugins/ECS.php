<?php

namespace Pkerrigan\Xray\Segment\Plugins;

/**
 * Adds ECS data to the Segment
 *
 * Class ECS
 * @package Pkerrigan\Xray\Segment\Plugins
 */
class ECS implements Plugin
{

    public function getData()
    {
        return [
            'ecs' => [
                'container' => gethostname()
            ],
            'originName' => 'AWS::ECS::Container'
        ];
    }
}
