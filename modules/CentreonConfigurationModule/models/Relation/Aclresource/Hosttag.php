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

namespace CentreonConfiguration\Models\Relation\Aclresource;

use Centreon\Internal\Di;
use Centreon\Models\CentreonRelationModel;

class Hosttag extends CentreonRelationModel
{
    protected static $relationTable = "cfg_acl_resources_tags_hosts_relations";
    protected static $firstKey = "acl_resource_id";
    protected static $secondKey = "tag_id";
    public static $firstObject = "\CentreonAdministration\Models\Aclresource";
    public static $secondObject = "\CentreonConfiguration\Models\Hosttag";
}
