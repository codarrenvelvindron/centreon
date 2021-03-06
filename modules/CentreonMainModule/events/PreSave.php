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

namespace CentreonMain\Events;

/**
 * This event allows modules to catch form actions
 */
class PreSave
{
    /**
     *
     * @var type 
     */
    private $action;
    
    /**
     *
     * @var type 
     */
    private $parameters;
    
    /**
     *
     * @var type 
     */
    private $extraParameters;

    /**
     * 
     * @param type $action
     * @param type $parameters
     * @param type $extraParameters
     */
    public function __construct($action, $parameters, $extraParameters)
    {
        $this->action = $action;
        $this->parameters = $parameters;
        $this->extraParameters= $extraParameters;
    }

    /**
     * 
     * @return type
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * 
     * @return type
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * 
     * @return type
     */
    public function getExtraParameters()
    {
        return $this->extraParameters;
    }

    /**
     * 
     * @return type
     */
    public function getObjectId()
    {
        $parameters = $this->parameters;
        return $parameters['object_id'];
    }

    /**
     * 
     * @return type
     */
    public function getObjectName()
    {
        $parameters = $this->parameters;
        return $parameters['object'];
    }
}
