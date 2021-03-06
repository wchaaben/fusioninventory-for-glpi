<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2012 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory project.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with Behaviors. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    Alexandre Delaunay
   @co-author
   @copyright Copyright (c) 2010-2012 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

if(!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginFusioninventoryDeployCommon extends PluginFusioninventoryCommunication {

   // Get all devices and put in taskjobstate each task for each device for each agent
   function prepareRun($taskjobs_id) {
      global $DB;

      $task       = new PluginFusioninventoryTask();
      $job        = new PluginFusioninventoryTaskjob();
      $joblog     = new PluginFusioninventoryTaskjoblog();
      $jobstate  = new PluginFusioninventoryTaskjobstate();
      $agent      = new PluginFusioninventoryAgent();

      $uniqid= uniqid();

      $job->getFromDB($taskjobs_id);
      $task->getFromDB($job->fields['plugin_fusioninventory_tasks_id']);

      $communication= $task->fields['communication'];

      $actions     = importArrayFromDB($job->fields['action']);
      $definitions   = importArrayFromDB($job->fields['definition']);
      $taskvalid = 0;

      $computers = array();
      foreach ($actions as $action) {
         $itemtype = key($action);
         $items_id = current($action);

         switch($itemtype) {
            case 'Computer':
               $computers[] = $items_id;
               break;
            case 'Group':
               $computer_object = new Computer();

               //find computers by user associated with this group
               $group_users   = new Group_User();
               $group         = new Group();
               $group->getFromDB($items_id);

               $members = array();
               $computers_a_1 = array();
               $computers_a_2 = array();

               //array_keys($group_users->find("groups_id = '$items_id'"));
               $members = $group_users->getGroupUsers($items_id);

               foreach ($members as $member) {
                  $computers = $computer_object->find("users_id = '${member['id']}'");
                  foreach($computers as $computer) {
                     $computers_a_1[] = $computer['id'];
                  }
               }

               //find computers directly associated with this group
               $computers = $computer_object->find("groups_id = '$items_id'");
               foreach($computers as $computer) {
                  $computers_a_2[] = $computer['id'];
               }

               //merge two previous array and deduplicate entries
               $computers = array_unique(array_merge($computers_a_1, $computers_a_2));

               break;
            case 'PluginFusioninventoryDeployGroup':
               $group = new PluginFusioninventoryDeployGroup;
               $group->getFromDB($items_id);

               switch ($group->getField('type')) {
                  case 'STATIC':
                     $query = "SELECT items_id
                     FROM glpi_plugin_fusioninventory_deploygroups_staticdatas
                     WHERE groups_id = '$items_id'
                     AND itemtype = 'Computer'";
                     $res = $DB->query($query);
                     while ($row = $DB->fetch_assoc($res)) {
                        $computers[] = $row['items_id'];
                     }
                     break;
                  case 'DYNAMIC':
                     $query = "SELECT fields_array
                     FROM glpi_plugin_fusioninventory_deploygroups_dynamicdatas
                     WHERE groups_id = '$items_id'
                     LIMIT 1";
                     $res = $DB->query($query);
                     $row = $DB->fetch_assoc($res);

                     if (isset($_GET)) {
                        $get_tmp = $_GET;
                     }
                     if (isset($_SESSION["glpisearchcount"]['Computer'])) {
                        unset($_SESSION["glpisearchcount"]['Computer']);
                     }
                     if (isset($_SESSION["glpisearchcount2"]['Computer'])) {
                        unset($_SESSION["glpisearchcount2"]['Computer']);
                     }

                     $_GET = importArrayFromDB($row['fields_array']);

                     $_GET["glpisearchcount"] = count($_GET['field']);
                     if (isset($_GET['field2'])) {
                        $_GET["glpisearchcount2"] = count($_GET['field2']);
                     }

                     $pfSearch = new PluginFusioninventorySearch();
                     Search::manageGetValues('Computer');
                     $glpilist_limit = $_SESSION['glpilist_limit'];
                     $_SESSION['glpilist_limit'] = 999999999;
                     $result = $pfSearch->constructSQL('Computer',
                                                       $_GET);
                     $_SESSION['glpilist_limit'] = $glpilist_limit;
                     while ($data=$DB->fetch_array($result)) {
                        $computers[] = $data['id'];
                     }
                     if (count($get_tmp) > 0) {
                        $_GET = $get_tmp;
                     }

                     break;
               }
               break;
         }
      }


      $c_input= array();
      $c_input['plugin_fusioninventory_taskjobs_id'] = $taskjobs_id;
      $c_input['state']                              = 0;
      $c_input['plugin_fusioninventory_agents_id']   = 0;
      $package = new PluginFusioninventoryDeployPackage();

      foreach($computers as $computer_id) {
         //Unique Id match taskjobstatuses for an agent(computer)
         $uniqid= uniqid();

         foreach($definitions as $definition) {
            $package->getFromDB($definition['PluginFusioninventoryDeployPackage']);

            $c_input['state'] = 0;
            $c_input['itemtype'] = 'PluginFusioninventoryDeployPackage';
            $c_input['items_id'] = $package->fields['id'];
            $c_input['date'] = date("Y-m-d H:i:s");
            $c_input['uniqid'] = $uniqid;

            //get agent if for this computer
            $agents_id = $agent->getAgentWithComputerid($computer_id);
            if($agents_id === FALSE) {
               $jobstates_id = $jobstate->add($c_input);
               $jobstate->changeStatusFinish($jobstates_id,
                                             0,
                                             '',
                                             1,
                                             "No agent found for [[Computer::".$computer_id."]]",
                                             0,
                                             0);
            } else {
               $c_input['plugin_fusioninventory_agents_id'] = $agents_id;

               # Push the agent, in the stack of agent to awake
               if ($communication == "push") {
                  $_SESSION['glpi_plugin_fusioninventory']['agents'][$agents_id] = 1;
               }

               $jobstates_id= $jobstate->add($c_input);

               //Add log of taskjob
               $c_input['plugin_fusioninventory_taskjobstates_id'] = $jobstates_id;
               $c_input['state']= PluginFusioninventoryTaskjoblog::TASK_PREPARED;
               $taskvalid++;
               $joblog->add($c_input);
               unset($c_input['state']);
               unset($c_input['plugin_fusioninventory_agents_id']);
            }
         }
      }

      if ($taskvalid > 0) {
         $job->fields['status']= 1;
         $job->update($job->fields);
      } else {
         $job->reinitializeTaskjobs($job->fields['plugin_fusioninventory_tasks_id']);
      }

   }



   // When agent contact server, this function send datas to agent
   /*
    * $itemtype = type of device in definition
    * $array = array with different ID
    *
    */
   function run($taskjobs, $agent) {
      //process the first task planned
      $taskjob = array_shift($taskjobs);
      //get order type label
      $order_type_label = str_replace("PluginFusioninventoryDeploy", "", get_class($this));
      //get numeric order type from label
      $order_type = PluginFusioninventoryDeployOrder::getRender($order_type_label);
      //get order by type and package id
      $order = new PluginFusioninventoryDeployOrder($order_type, $taskjob['items_id']);
      //decode order data
      $order_data = json_decode($order->fields['json'], TRUE);

      /*
       * This has to be done properly in each corresponding classes.
       * Meanwhile, I just split the data to rebuild a proper and compliant JSON
       */
      $order_job = $order_data['jobs'];
      //add uniqid to response data
      $order_job['uuid'] = $taskjob['uniqid'];

      /*
       * Orders should only contains job data and associatedFiles should be retrieved from the list
       * inside Orders data.
       * exemple:
       * $order_files = array()
       * foreach($order_job["associatedFiles"] as $hash) {
       *    if (!isset($order_files[$hash]) {
       *       $order_files[$hash] = PluginFusioninventoryDeployFile::getByHash($hash);
       *       $order_files[$hash]['mirrors'] = $mirrors
       *    }
       * }
       */
      $order_files = $order_data['associatedFiles'];


      //Add mirrors to associatedFiles
      $mirrors = PluginFusioninventoryDeployMirror::getList($agent);
      foreach($order_files as $hash => $params) {
         $order_files[$hash]['mirrors'] = $mirrors;
         $manifest = GLPI_PLUGIN_DOC_DIR."/fusioninventory/files/manifests/".$hash;
         if ( file_exists($manifest) ) {
            $handle = fopen($manifest, "r");
            if ($handle) {
               $order_files[$hash]['multiparts'] = array();
               while ( ($buffer = fgets($handle) ) !== FALSE) {
                  $order_files[$hash]['multiparts'][] = trim($buffer);
               }
               fclose($handle);
            }
         }
      }
      //Send an empty json dict instead of empty json list
      if (count($order_files) == 0) {
         $order_files = (object)array();
      }

      $final_order = array(
         "jobs" => array(
            $order_job
         ),
         "associatedFiles" => $order_files
      );
      //return data response as json
      $json = json_encode($final_order);
      return $json;
   }
}

?>
