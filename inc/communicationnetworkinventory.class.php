<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2013 by the FusionInventory Development Team.

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
   along with FusionInventory. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    Vincent Mazzoni
   @co-author David Durieux
   @copyright Copyright (c) 2010-2013 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginFusioninventoryCommunicationNetworkInventory {

   private $ptd, $logFile, $agent, $arrayinventory;
   private $a_ports = array();



   function __construct() {
      if (PluginFusioninventoryConfig::isExtradebugActive()) {
         $this->logFile = GLPI_LOG_DIR.'/fusioninventorycommunication.log';
      }
   }



   static function canCreate() {
      return PluginFusioninventoryProfile::haveRight("networkequipment", "w");
   }



   static function canView() {
      return PluginFusioninventoryProfile::haveRight("networkequipment", "r");
   }



   /**
    * Import data
    *
    *@param $p_DEVICEID XML code to import
    *@param $p_CONTENT XML code to import
    *@return "" (import ok) / error string (import ko)
    **/
   function import($p_DEVICEID, $a_CONTENT, $arrayinventory) {

      //$_SESSION['SOURCEXML'] = $p_xml;

      PluginFusioninventoryCommunication::addLog(
              'Function PluginFusioninventoryCommunicationNetworkInventory->import().');

      $pfAgent = new PluginFusioninventoryAgent();
      $pfTaskjobstate = new PluginFusioninventoryTaskjobstate();

      $this->agent = $pfAgent->InfosByKey($p_DEVICEID);

      $this->arrayinventory = $arrayinventory;

      $_SESSION['glpi_plugin_fusioninventory_processnumber'] = $a_CONTENT['PROCESSNUMBER'];
      if ((!isset($a_CONTENT['AGENT']['START'])) AND (!isset($a_CONTENT['AGENT']['END']))) {
          $nb_devices = 0;
          if (isset($a_CONTENT['DEVICE'])) {
              if (is_int(key($a_CONTENT['DEVICE']))) {
                  $nb_devices = count($a_CONTENT['DEVICE']);
              } else {
                  $nb_devices = 1;
              }
          }

          $_SESSION['plugin_fusinvsnmp_taskjoblog']['taskjobs_id'] =
              $a_CONTENT['PROCESSNUMBER'];
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['items_id'] = $this->agent['id'];
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['itemtype'] = 'PluginFusioninventoryAgent';
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['state'] = '6';
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] = $nb_devices.
              ' ==devicesqueried==';
          $this->addtaskjoblog();

      }

      $this->importContent($a_CONTENT);

      if (isset($a_CONTENT['AGENT']['END'])) {
          $pfTaskjobstate->changeStatusFinish($a_CONTENT['PROCESSNUMBER'],
              $this->agent['id'],
              'PluginFusioninventoryAgent');
      }
      if (isset($a_CONTENT['AGENT']['START'])) {
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['taskjobs_id'] =
              $a_CONTENT['PROCESSNUMBER'];
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['items_id'] = $this->agent['id'];
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['itemtype'] = 'PluginFusioninventoryAgent';
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['state'] = '6';
          $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] = '==inventorystarted==';
          $this->addtaskjoblog();
      }
   }



   /**
    * Import the content (where have all devices)
    *@param $p_content CONTENT code to import
    *
    *@return errors string to be alimented if import ko / '' if ok
    **/
   function importContent($arrayinventory) {

      PluginFusioninventoryCommunication::addLog(
              'Function PluginFusioninventoryCommunicationNetworkInventory->importContent().');
      $pfAgent = new PluginFusioninventoryAgent();

      $errors='';
      $nbDevices = 0;
      foreach ($arrayinventory as $childname=>$child) {
         PluginFusioninventoryCommunication::addLog($childname);
         switch ($childname) {

            case 'DEVICE' :
               $a_devices = array();
               if (is_int(key($child))) {
                  $a_devices = $child;
               } else {
                  $a_devices[] = $child;
               }
               foreach ($a_devices as $dchild) {
                  $a_inventory = array();
                  if (isset($dchild['INFO'])) {
                     if ($dchild['INFO']['TYPE'] == "NETWORKING") {
                        $a_inventory = PluginFusioninventoryFormatconvert::networkequipmentInventoryTransformation($dchild);
                     } else if ($dchild['INFO']['TYPE'] == "PRINTER") {
                        $a_inventory = PluginFusioninventoryFormatconvert::printerInventoryTransformation($dchild);
                     }
                  }
                  if (isset($dchild['ERROR'])) {
                     $itemtype = "";
                     if ($dchild['ERROR']['TYPE'] == "NETWORKING") {
                        $itemtype = "NetworkEquipment";
                     } else if ($dchild['ERROR']['TYPE'] == "PRINTER") {
                        $itemtype = "Printer";
                     }
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] = '[==detail==] '.
                             $dchild['ERROR']['MESSAGE'].' [['.$itemtype.'::'.
                             $dchild['ERROR']['ID'].']]';
                     $this->addtaskjoblog();
                  } else if ($a_inventory['PluginFusioninventory'.$a_inventory['itemtype']]['sysdescr'] == ''
                              && $a_inventory[$a_inventory['itemtype']]['name'] == ''
                              && $a_inventory[$a_inventory['itemtype']]['serial'] == '') {

                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] =
                              '[==detail==] No informations [['.$itemtype.'::'.$dchild['ID'].']]';
                     $this->addtaskjoblog();
                  } else {
                     if (count($a_inventory) > 0) {
                        $errors .= $this->sendCriteria($a_inventory);
                        $nbDevices++;
                     }
                  }
               }
               break;

            case 'AGENT' :
               if (isset($this->arrayinventory['CONTENT']['AGENT']['AGENTVERSION'])) {
                  $agent = $pfAgent->InfosByKey($this->arrayinventory['DEVICEID']);
                  $agent['fusioninventory_agent_version'] =
                                       $this->arrayinventory['CONTENT']['AGENT']['AGENTVERSION'];
                  $agent['last_agent_update'] = date("Y-m-d H:i:s");
                  $pfAgent->update($agent);
               }
               break;

            case 'PROCESSNUMBER' :
               break;

            case 'MODULEVERSION' :
               break;

            default :
               $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] = '[==detail==] '.
                           __('Unattended element in', 'fusioninventory').' CONTENT : '.$childname;
               $this->addtaskjoblog();
         }
      }
      return $errors;
   }



   /**
    * Import one device
    *
    * @param type $itemtype
    * @param type $items_id
    *
    * @return errors string to be alimented if import ko / '' if ok
    */
   function importDevice($itemtype, $items_id, $a_inventory) {

      PluginFusioninventoryCommunication::addLog(
              'Function PluginFusioninventoryCommunicationNetworkInventory->importDevice().');

      $pfFormatconvert = new PluginFusioninventoryFormatconvert();
      $a_inventory = $pfFormatconvert->replaceids($a_inventory);

      // Write XML file
      if (count($a_inventory) > 0) {
         $folder = substr($items_id, 0, -1);
         if (empty($folder)) {
            $folder = '0';
         }
         if (!file_exists(GLPI_PLUGIN_DOC_DIR."/fusioninventory/".$itemtype."/".$folder)) {
            mkdir(GLPI_PLUGIN_DOC_DIR."/fusioninventory/".$itemtype."/".$folder, 0777, TRUE);
         }
         $fileopen = fopen(GLPI_PLUGIN_DOC_DIR."/fusioninventory/".$itemtype."/".$folder."/".
                           $items_id, 'w');
         fwrite($fileopen, print_r($a_inventory, TRUE));
         fclose($fileopen);
       }

      $errors='';
      $this->deviceId=$items_id;


      $serialized = gzcompress(serialize($a_inventory));

      switch ($itemtype) {

         case 'Printer':
            $pfiPrinterLib = new PluginFusioninventoryInventoryPrinterLib();
            $a_inventory['PluginFusioninventoryPrinter']['serialized_inventory'] =
                        Toolbox::addslashes_deep($serialized);
            $pfiPrinterLib->updatePrinter($a_inventory, $items_id);
            break;

         case 'NetworkEquipment':
            $pfiNetworkEquipmentLib = new PluginFusioninventoryInventoryNetworkEquipmentLib();
            $a_inventory['PluginFusioninventoryNetworkEquipment']['serialized_inventory'] =
                        Toolbox::addslashes_deep($serialized);
            $pfiNetworkEquipmentLib->updateNetworkEquipment($a_inventory, $items_id);
            break;

         default:
            $errors.=__('Unattended element in', 'fusioninventory').' TYPE : '
                              .$a_inventory['itemtype']."\n";
      }
   }



   /**
    * Send XML of SNMP device to rules
    *
    * @param simplexml $p_CONTENT
    *
    * @return type
    */
   function sendCriteria($a_inventory) {

      PluginFusioninventoryCommunication::addLog(
              'Function PluginFusioninventoryCommunicationNetworkInventory->sendCriteria().');

      $errors = '';

      // Manual blacklist
       if ($a_inventory[$a_inventory['itemtype']]['serial'] == 'null') {
          $a_inventory[$a_inventory['itemtype']]['serial'] = '';
       }
       // End manual blacklist

       $_SESSION['SOURCE_XMLDEVICE'] = $a_inventory;
       $input = array();

      // Global criterias

         if (!empty($a_inventory[$a_inventory['itemtype']]['serial'])) {
            $input['serial'] = $a_inventory[$a_inventory['itemtype']]['serial'];
         }
         if ($a_inventory['itemtype'] == 'NetworkEquipment') {
            if (!empty($a_inventory[$a_inventory['itemtype']]['mac'])) {
               $input['mac'][] = $a_inventory[$a_inventory['itemtype']]['mac'];
            }
            $input['itemtype'] = "NetworkEquipment";
         } else if ($a_inventory['itemtype'] == 'Printer') {
            $input['itemtype'] = "Printer";
            if (isset($a_inventory['networkport'])) {
               $a_ports = array();
               if (is_int(key($a_inventory['networkport']))) {
                  $a_ports = $a_inventory['networkport'];
               } else {
                  $a_ports[] = $a_inventory['networkport'];
               }
               foreach($a_ports as $port) {
                  if (!empty($port['mac'])) {
                     $input['mac'][] = $port['mac'];
                  }
                  if (!empty($port['ip'])) {
                     $input['ip'][] = $port['ip'];
                  }
               }
            }
         }
         if (!empty($a_inventory['networkequipmentmodels_id'])) {
            $input['model'] = $a_inventory['networkequipmentmodels_id'];
         }
         if (!empty($a_inventory['name'])) {
            $input['name'] = $a_inventory['name'];
         }

      $_SESSION['plugin_fusinvsnmp_datacriteria'] = serialize($input);
      $_SESSION['plugin_fusioninventory_classrulepassed'] =
                                 "PluginFusioninventoryCommunicationNetworkInventory";
      $rule = new PluginFusioninventoryInventoryRuleImportCollection();
      PluginFusioninventoryConfig::logIfExtradebug("pluginFusioninventory-rules",
                                                   "Input data : ".print_r($input, TRUE));
      $data = $rule->processAllRules($input, array());
      PluginFusioninventoryConfig::logIfExtradebug("pluginFusioninventory-rules",
                                                   $data);
      if (isset($data['action'])
             && ($data['action'] == PluginFusioninventoryInventoryRuleImport::LINK_RESULT_DENIED)) {

         $a_text = '';
         foreach ($input as $key=>$data) {
            if (is_array($data)) {
               $a_text[] = "[".$key."]:".implode(", ", $data);
            } else {
               $a_text[] = "[".$key."]:".$data;
            }
         }
         $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] = '==importdenied== '.
                 implode(", ", $a_text);
         $this->addtaskjoblog();

         $pfIgnoredimportdevice = new PluginFusioninventoryIgnoredimportdevice();
         $inputdb = array();
         $inputdb['name'] = $input['name'];
         $inputdb['date'] = date("Y-m-d H:i:s");
         $inputdb['itemtype'] = $input['itemtype'];
         if (isset($input['serial'])) {
            $input['serialnumber'] = $input['serial'];
         }
         if (isset($input['ip'])) {
            $inputdb['ip'] = exportArrayToDB($input['ip']);
         }
         if (isset($input['mac'])) {
            $inputdb['mac'] = exportArrayToDB($input['mac']);
         }
         $inputdb['rules_id'] = $_SESSION['plugin_fusioninventory_rules_id'];
         $inputdb['method'] = 'networkinventory';
         $pfIgnoredimportdevice->add($inputdb);
         unset($_SESSION['plugin_fusioninventory_rules_id']);
      }
      if (isset($data['_no_rule_matches']) AND ($data['_no_rule_matches'] == '1')) {
         if (isset($input['itemtype'])
             && isset($data['action'])
             && ($data['action'] == PluginFusioninventoryInventoryRuleImport::LINK_RESULT_CREATE)) {

            $errors .= $this->rulepassed(0, $input['itemtype']);
         } else if (isset($input['itemtype'])
              AND !isset($data['action'])) {
            $id_xml = $a_inventory[$a_inventory['itemtype']]['id'];
            $classname = $input['itemtype'];
            $class = new $classname;
            if ($class->getFromDB($id_xml)) {
               $errors .= $this->rulepassed($id_xml, $input['itemtype']);
            } else {
               $errors .= $this->rulepassed(0, $input['itemtype']);
            }
         } else {
            $errors .= $this->rulepassed(0, "PluginFusioninventoryUnknownDevice");
         }
      }
      return $errors;
   }



   /**
    * After rules import device
    *
    * @param integer $items_id id of the device in GLPI DB (0 = created, other = merge)
    * @param varchar $itemtype itemtype of the device
    *
    * @return type
    */
   function rulepassed($items_id, $itemtype) {

      PluginFusioninventoryLogger::logIfExtradebug(
         "pluginFusioninventory-rules",
         "Rule passed : ".$items_id.", ".$itemtype."\n"
      );
      PluginFusioninventoryLogger::logIfExtradebugAndDebugMode(
         'fusioninventorycommunication',
         'Function PluginFusinvsnmpCommunicationSNMPQuery->rulepassed().'
      );

      PluginFusioninventoryConfig::logIfExtradebug("pluginFusioninventory-rules",
                                                   "Rule passed : ".$items_id.", ".$itemtype."\n");
      PluginFusioninventoryCommunication::addLog(
              'Function PluginFusioninventoryCommunicationNetworkInventory->rulepassed().');

      $a_inventory = $_SESSION['SOURCE_XMLDEVICE'];

      $errors = '';
      $class = new $itemtype;
      if ($items_id == "0") {
         $input = array();
         $input['date_mod'] = date("Y-m-d H:i:s");
         if ($class->getFromDB($a_inventory[$a_inventory['itemtype']]['id'])) {
            $input['entities_id'] = $class->fields['entities_id'];
         } else {
            $input['entities_id'] = 0;
         }
         if (!isset($_SESSION['glpiactiveentities_string'])) {
            $_SESSION['glpiactiveentities_string'] = "'".$input['entities_id']."'";
         }
         $items_id = $class->add($input);
         if (isset($_SESSION['plugin_fusioninventory_rules_id'])) {
            $pfRulematchedlog = new PluginFusioninventoryRulematchedlog();
            $inputrulelog = array();
            $inputrulelog['date'] = date('Y-m-d H:i:s');
            $inputrulelog['rules_id'] = $_SESSION['plugin_fusioninventory_rules_id'];
            if (isset($_SESSION['plugin_fusioninventory_agents_id'])) {
               $inputrulelog['plugin_fusioninventory_agents_id'] =
                                                   $_SESSION['plugin_fusioninventory_agents_id'];
            }
            $inputrulelog['items_id'] = $items_id;
            $inputrulelog['itemtype'] = $itemtype;
            $inputrulelog['method'] = 'snmpinventory';
            $pfRulematchedlog->add($inputrulelog);
            $pfRulematchedlog->cleanOlddata($items_id, $itemtype);
            unset($_SESSION['plugin_fusioninventory_rules_id']);
         }
      }
      if ($itemtype == "PluginFusioninventoryUnknownDevice") {
         $class->getFromDB($items_id);
         $input = array();
         $input['id'] = $class->fields['id'];
         if (!empty($a_inventory[$a_inventory['itemtype']]['name'])) {
            $input['name'] = $a_inventory[$a_inventory['itemtype']]['name'];
         }
         if (!empty($a_inventory[$a_inventory['itemtype']]['serial'])) {
            $input['serial'] = $a_inventory[$a_inventory['itemtype']]['serial'];
         }
         if (!empty($a_inventory[$a_inventory['itemtype']]['otherserial'])) {
            $input['otherserial'] = $a_inventory[$a_inventory['itemtype']]['otherserial'];
         }
         if (!empty($a_inventory['itemtype'])) {
            $input['itemtype'] = $a_inventory['itemtype'];
         }
         // TODO : add import ports
         PluginFusioninventoryToolbox::writeXML($items_id,
                                                serialize($_SESSION['SOURCE_XMLDEVICE']),
                                                'PluginFusioninventoryUnknownDevice');
         $class->update($input);
         $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] =
            '[==detail==] ==updatetheitem== Update '.
                 PluginFusioninventoryUnknownDevice::getTypeName().
                 ' [[PluginFusioninventoryUnknownDevice::'.$items_id.']]';
         $this->addtaskjoblog();
      } else {
         $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment'] =
               '[==detail==] Update '.$class->getTypeName().' [['.$itemtype.'::'.$items_id.']]';
         $this->addtaskjoblog();
         $errors .= $this->importDevice($itemtype, $items_id, $a_inventory);
      }
      return $errors;
   }



   /**
    * Used to add log in the task
    */
   function addtaskjoblog() {

      if (!isset($_SESSION['plugin_fusinvsnmp_taskjoblog']['taskjobs_id'])) {
         return;
      }

      $pfTaskjoblog = new PluginFusioninventoryTaskjoblog();
      $pfTaskjoblog->addTaskjoblog(
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['taskjobs_id'],
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['items_id'],
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['itemtype'],
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['state'],
                     $_SESSION['plugin_fusinvsnmp_taskjoblog']['comment']);
   }

   

   static function getMethod() {
      return 'snmpinventory';
   }

}

?>
