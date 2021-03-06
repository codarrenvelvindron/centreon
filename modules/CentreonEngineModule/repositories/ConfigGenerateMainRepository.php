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

namespace CentreonEngine\Repository;

use Centreon\Internal\Di;
use Centreon\Internal\Exception;
use CentreonConfiguration\Models\Poller;
use CentreonConfiguration\Events\BrokerModule as BrokerModuleEvent;
use CentreonConfiguration\Internal\Poller\Template\Manager as PollerTemplateManager;
use CentreonConfiguration\Repository\CustomMacroRepository;
use CentreonConfiguration\Internal\Poller\WriteConfigFile;

/**
 * Factory for ConfigGenerate Engine For centengine.cfg
 *
 * @author Sylvestre Ho <sho@centreon.com>
 * @version 3.0.0
 */

class ConfigGenerateMainRepository
{
    /**
     * @var string
     */
    protected static $path;

    /**
     * Final etc path
     *
     * @var string
     */
    protected static $finalPath;

    /**
     * Method for generating Main configuration file
     * 
     * @param array $filesList
     * @param int $poller_id
     * @param string $path
     * @param string $filename
     * @param int $testing
     */
    public static function generate(& $filesList, $poller_id, $path, $filename, $testing = 0)
    {
        static::$path = rtrim($path, '/');

        /* Get Content */
        $content = static::getContent($poller_id, $filesList, $testing);

        /* Write Check-Command configuration file */
        WriteConfigFile::writeParamsFile($content, $path.$poller_id."/".$filename, $filesList, $user = "API");
        unset($content);
    }

    /**
     * 
     * @param array $filesList
     * @param array $content
     * @param int $testing
     * @param int $pollerId
     * @return array
     */
    private static function getFilesList($filesList, $content, $testing, $pollerId)
    {
        $di = Di::getDefault();

        $tmpPath = static::$path;
        $engineEtcPath = static::$finalPath;
        
        foreach ($filesList as $category => $data) {
            if ($category != 'main_file') {
                foreach ($data as $path) {
                    if (!isset($content[$category])) {
                        $content[$category] = array();
                    }
                    if (!$testing) {
                        $path = str_replace("{$tmpPath}/{$pollerId}/", "{$engineEtcPath}/", $path);
                    }
                    $content[$category][] = $path;
                }
            }
        }
        return $content;
    }

    /**
     * 
     * @param int $poller_id
     * @param array $filesList
     * @param int $testing
     * @return array
     */
    private static function getContent($poller_id, & $filesList, $testing)
    {
        $di = Di::getDefault();

        /* Get Database Connexion */
        $dbconn = $di->get('db_centreon');

        /* Init Content Array */
        $content = array();

        /* Default values that can be overwritten by template and user */
        $defaultValues = static::getDefaultValues();

        /* Template values that can be overwritten by user */
        $templateValues = static::getTemplateValues($poller_id);

        /* For command name resolution */
        $commandIdFields = static::getCommandIdField();
        
        /* Get configuration files */
        $content = static::getFilesList($filesList, $content, $testing, $poller_id);

        /* Get values from the table, those are saved by user */
        $query = "SELECT * FROM cfg_engine WHERE poller_id = ?";
        $stmt = $dbconn->prepare($query);
        $stmt->execute(array($poller_id));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (false === $row) {
            throw new Exception(sprintf('Could not find parameters for poller %s.', $poller_id));
        }
        $userValues = array();
        foreach ($row as $key => $val) {
            if (!is_null($val) && $val != "") {
                $userValues[$key] = $val;
            }
        }

        /* Overwrite parameter */
        $tmpConf = array_merge($defaultValues, $templateValues);
        $finalConf = array_merge($tmpConf, $userValues);

        /* For object directory */
        $objectDirectives = static::getConfigFiles($poller_id);

        $finalConf = array_merge($finalConf, $objectDirectives);

        /* Add illegal chars */
        $finalConf['illegal_macro_output_chars'] = CustomMacroRepository::$forbidenCHar;
        $finalConf['illegal_object_name_chars'] = CENTREON_ILLEGAL_CHAR_OBJ;
        
        /* Set real etc path of the poller */
        static::$finalPath = $finalConf['conf_dir'];

        /* Replace path macros */
        foreach ($finalConf as $k => $v) {
            $arr = array();
            if (!is_array($v)) {
                $arr[] = $v;
            } else {
                $arr = $v;
            }
            foreach ($arr as $key => $val) {
                if (preg_match('/%([a-z_]+)%/', $val, $matches)) {
                    $macro = $matches[1];
                    if (isset($finalConf[$macro])) {
                        $finalConf[$macro] = rtrim($finalConf[$macro], '/');
                        if (is_array($finalConf[$k])) {
                            $finalConf[$k][$key] = str_replace("%{$macro}%", $finalConf[$macro], $val);
                        } else {
                            $finalConf[$k] = str_replace("%{$macro}%", $finalConf[$macro], $val);
                        }
                    }
                    if ($macro == 'conf_dir' && $testing) {
                        $finalConf[$k][$key] = str_replace('%conf_dir%', static::$path . "/" . $poller_id, $val);
                    }
                }
            }
        }

        /* Replace commands */
        foreach ($commandIdFields as $fieldName) {
            if (isset($finalConf[$fieldName]) && $finalConf[$fieldName]) {
                $finalConf[$fieldName] = CommandRepository::getCommandName($finalConf[$fieldName]);
            }
        }

        /* Exclude parameters */
        static::unsetParameters($finalConf);

        return $finalConf;
    }

    /**
     * Unset unwanted parameters for generation
     *
     * @param array $finalConf
     */
    private static function unsetParameters(& $finalConf)
    {
        unset($finalConf['poller_id']);
        unset($finalConf['conf_dir']);
        unset($finalConf['log_dir']);
        unset($finalConf['var_lib_dir']);
        unset($finalConf['module_dir']);
        unset($finalConf['init_script']);
    }

    /**
     * 
     * @param int $poller_id
     * @return array
     */
    private static function getConfigFiles($poller_id)
    {
        $includeList = array();
        $pathList = array();
        $resList = array();
        $dirList = array();
        
        $path = static::$path . '/';
        
        /* Check that that basic path exists */
        if (!file_exists($path)) {
            if (!is_dir($path)) {
                mkdir($path);
            }
        }

        /* Check that poller directory exists */
        if (!file_exists($path.$poller_id)) {
            if (!is_dir($path.$poller_id)) {
                mkdir($path.$poller_id);
            }
        }

        /* Check that main configuration directory exists */
        if (!file_exists($path.$poller_id."/conf.d/")) {
            if (!is_dir($path.$poller_id."/conf.d/")) {
                mkdir($path.$poller_id."/conf.d/");
            }
        }

        /* Check that objects directory exists */
        if (!file_exists($path.$poller_id."/objects.d/")) {
            if (!is_dir($path.$poller_id."/objects.d/")) {
                mkdir($path.$poller_id."/objects.d/");
            }
        }

        $includeDirList[] = "conf.d/";
        
        $dirList[] = "objects.d/";
        $dirList[] = "objects.d/resources/";

        return array("cfg_file" => $pathList, "resource_file" => $resList, "cfg_dir" => $dirList, "cfg_include_dir" => $includeDirList);
    }

    /**
     * Returns the default configuration values of value
     * Those values are stored in the default.json file
     * 
     * @return array
     * @throws \Centreon\Internal\Exception
     */
    private static function getDefaultValues()
    {
        $config = Di::getDefault()->get('config');

        $centreonPath = rtrim($config->get('global', 'centreon_path'), '/');
        $jsonFile = "{$centreonPath}/modules/CentreonEngineModule/data/default.json";
        if (!file_exists($jsonFile)) {
            throw new Exception('Default engine configuration JSON file not found');
        }
        $defaultValue = json_decode(file_get_contents($jsonFile), true);

        return $defaultValue;
    }

    /**
     * Returns the template configuration values
     * 
     * @param int $pollerId
     * @return array
     * @throws \Centreon\Internal\Exception
     */
    private static function getTemplateValues($pollerId)
    {
        $templateValues = array(); 

        /* Retrieve template name  */
        $pollerParam = Poller::get($pollerId, 'tmpl_name');
        if (!isset($pollerParam['tmpl_name']) || is_null($pollerParam['tmpl_name'])) {
            return $templateValues;
        }

        /* Look for template file */
        $config = Di::getDefault()->get('config');
        $centreonPath = rtrim($config->get('global', 'centreon_path'), '/');

        /* Get template engine file */
        $listTpl = PollerTemplateManager::buildTemplatesList();
        if (!isset($listTpl[$pollerParam['tmpl_name']])) {
            throw new Exception('The template is not found on list of templates');
        }
        $jsonFile = $listTpl[$pollerParam['tmpl_name']]->getEnginePath();
        if (!file_exists($jsonFile)) {
            throw new Exception('Engine template file not found: ' . $pollerParam['tmpl_name'] . '.json');
        }

        /* Checks whether or not template file has all the sections */
        $arr = json_decode(file_get_contents($jsonFile), true);
        if (!isset($arr['content']) || !isset($arr['content']['engine']) || 
            !isset($arr['content']['engine']['setup'])) {
                return $templateValues;
        }

        /* Retrieve parameter values */
        foreach ($arr['content']['engine']['setup'] as $setup) {
            if (isset($setup['params'])) {
                foreach ($setup['params'] as $k => $v) {
                    $templateValues[$k] = $v;
                }
            }
        }
        return $templateValues;
    }

    /**
     * 
     * @return int
     */
    private static function getCommandIdField()
    {
        $commands = array(
            'global_host_event_handler',
            'global_service_event_handler',
            'ocsp_command',
            'ochp_command'
        );
        return $commands;
    }
}
