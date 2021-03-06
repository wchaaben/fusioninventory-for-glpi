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
   @author    David Durieux
   @co-author
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


class PluginFusioninventoryConfigLogField extends CommonDBTM {


   /**
    * Init config log fields : add default values in table
    *
    *@return nothing
    **/
   function initConfig() {

      $NOLOG = '-1';
      $logs = array();
      $logs['NetworkEquipment']['ifdescr'] = $NOLOG;
      $logs['NetworkEquipment']['ifIndex'] = $NOLOG;
      $logs['NetworkEquipment']['ifinerrors'] = $NOLOG;
      $logs['NetworkEquipment']['ifinoctets'] = $NOLOG;
      $logs['NetworkEquipment']['ifinternalstatus'] = $NOLOG;
      $logs['NetworkEquipment']['iflastchange'] = $NOLOG;
      $logs['NetworkEquipment']['ifmtu'] = $NOLOG;
      $logs['NetworkEquipment']['ifName'] = $NOLOG;
      $logs['NetworkEquipment']['ifouterrors'] = $NOLOG;
      $logs['NetworkEquipment']['ifoutoctets'] = $NOLOG;
      $logs['NetworkEquipment']['ifspeed'] = $NOLOG;
      $logs['NetworkEquipment']['ifstatus'] = $NOLOG;
      $logs['NetworkEquipment']['macaddr'] = $NOLOG;
      $logs['NetworkEquipment']['portDuplex'] = $NOLOG;
      $logs['NetworkEquipment']['trunk'] = $NOLOG;

      $logs['Printer']['ifIndex'] = $NOLOG;
      $logs['Printer']['ifName'] = $NOLOG;

      $mapping = new PluginFusioninventoryMapping();
      foreach ($logs as $itemtype=>$fields){
         foreach ($fields as $name=>$value){
            $input = array();
            $mapfields = $mapping->get($itemtype, $name);
            if ($mapfields != FALSE) {
               $input['plugin_fusioninventory_mappings_id'] = $mapfields['id'];
               $input['days']  = $value;
               $this->add($input);
            }
         }
      }
   }



   /**
    * Get the value of a field in configlog
    *
    * @global object $DB
    * @param $field name of the field
    *
    * @return value or FALSE
    */
   function getValue($field) {
      global $DB;

      $query = "SELECT days
                FROM ".$this->getTable()."
                WHERE `plugin_fusioninventory_mappings_id`='".$field."'
                LIMIT 1;";
      $result = $DB->query($query);
      if ($result) {
         $this->fields = $DB->fetch_row($result);
         if ($this->fields) {
            return $this->fields['0'];
         }
      }
      return FALSE;
   }



   function showForm($options=array()) {
      global $DB;

      $mapping = new PluginFusioninventoryMapping();

      echo "<form name='form' method='post' action='".$options['target']."'>";
      echo "<div class='center' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr>";
      echo "<th colspan='2'>";
      echo __('History configuration', 'fusioninventory');

      echo "</th>";
      echo "</tr>";

      echo "<tr>";
      echo "<th>";
      echo __('List of fields for which to keep history', 'fusioninventory');

      echo "</th>";
      echo "<th>";
      echo __('Retention in days', 'fusioninventory');

      echo "</th>";
      echo "</tr>";

      $days = array();
      $days[-1] = __('Never');

      $days[0]  = __('Always');

      for ($i = 1 ; $i < 366 ; $i++) {
         $days[$i]  = "$i";
      }

      $query = "SELECT `".$this->getTable()."`.`id`, `locale`, `days`, `itemtype`, `name`
                FROM `".$this->getTable()."`, `glpi_plugin_fusioninventory_mappings`
                WHERE `".$this->getTable()."`.`plugin_fusioninventory_mappings_id`=
                         `glpi_plugin_fusioninventory_mappings`.`id`
                ORDER BY `itemtype`, `name`;";
      $result=$DB->query($query);
      if ($result) {
         while ($data=$DB->fetch_array($result)) {
            echo "<tr class='tab_bg_1'>";
            echo "<td align='left'>";
            echo $mapping->getTranslation($data);
            echo "</td>";

            echo "<td align='center'>";
            Dropdown::showFromArray('field-'.$data['id'], $days,
                                    array('value'=>$data['days']));
            echo "</td>";
            echo "</tr>";
         }
      }

      if (PluginFusioninventoryProfile::haveRight("configuration", "w")) {
         echo "<tr class='tab_bg_2'><td align='center' colspan='4'>
               <input type='hidden' name='tabs' value='history'/>
               <input class='submit' type='submit' name='update'
                      value='" . __('Update') . "'></td></tr>";
      }
      echo "</table>";

      echo "<br/>";
      echo "<table class='tab_cadre_fixe' cellpadding='2'>";
      echo "<tr class='tab_bg_2'>";
      echo "<td colspan='1' class='center' height='30'>";
      if (PluginFusioninventoryProfile::haveRight("configuration", "w")) {
         echo "<input type='submit' class=\"submit\" name='Clean_history' ".
                 "value='".__('Clean')."' >";
      }
      echo "</td>";
      echo "</tr>";
      echo "</table></div>";
      Html::closeForm();

      return TRUE;
   }



   function putForm($p_post) {
      foreach ($p_post as $field=>$log) {
         if (substr($field, 0, 6) == 'field-') {
            $input = array();
            $input['id'] = substr($field, 6);
            $input['days'] = $log;
            $this->update($input);
         }
      }
   }
}

?>
