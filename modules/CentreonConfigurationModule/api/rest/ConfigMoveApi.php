<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 * 
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0  
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * For more information : contact@centreon.com
 * 
 */

namespace CentreonConfiguration\Api\Rest;

use Centreon\Internal\Di;
use CentreonConfiguration\Repository\ConfigMoveRepository;
use Centreon\Internal\Controller;

/**
 * @authors Lionel Assepo
 * @package Centreon
 * @subpackage Controllers                                   
 */
class ConfigMoveApi extends Controller
{
    /**
     * Action for Testing configuration files
     *
     * @method GET
     * @route /movecfg/[i:id]
     */
    public static function moveAction()
    {
        $di = Di::getDefault();
        $router = $di->get('router');

        $param = $router->request()->paramsNamed();

        $obj = new ConfigMoveRepository($param["id"]);
        $obj->moveConfig();

        $router->response()->json(
            array(
                "status" => $obj->getStatus(),
                "output" => $obj->getOutput()
            )
        );
    }
}
