<?php

/*
   ----------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2011 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ----------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 2 of the License, or
   any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with FusionInventory.  If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------
   Original Author of file: Walid Nouh
   Co-authors of file: David DURIEUX
   Purpose of file:
   ----------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

include_once(GLPI_ROOT."/plugins/fusioninventory/inc/rule.class.php");


class PluginFusinvinventoryRuleEntity extends PluginFusioninventoryRule {

   // From Rule
   public $right='rule_ocs';
   public $can_sort=true;
   public $specific_parameters = true;

   function getTitle() {
      global $LANG;
      return $LANG['plugin_fusinvinventory']['rule'][100];
   }

   function preProcessPreviewResults($output) {
      return $output;
   }


   function maxActionsCount() {
      // Unlimited
      return 2;
   }

   function executeActions($output,$params) {

      if (PluginFusioninventoryConfig::getValue($_SESSION["plugin_fusioninventory_moduleid"], 'extradebug')) {
         logInFile("pluginFusinvinventory-entityrules", "execute action\n");
      }

      if (count($this->actions)) {
         foreach ($this->actions as $action) {
            switch ($action->fields["action_type"]) {
               case "assign" :
                  if (PluginFusioninventoryConfig::getValue($_SESSION["plugin_fusioninventory_moduleid"], 'extradebug')) {
                     logInFile("pluginFusinvinventory-entityrules", "value ".$action->fields["value"]."\n");
                  }
                  $output[$action->fields["field"]] = $action->fields["value"];
                  break;

               case "regex_result" :
                  //Assign entity using the regex's result
                  if ($action->fields["field"] == "_affect_entity_by_tag") {
                     if (PluginFusioninventoryConfig::getValue($_SESSION["plugin_fusioninventory_moduleid"], 'extradebug')) {
                        logInFile("pluginFusinvinventory-entityrules", "value".$action->fields["value"]."\n");
                     }
                     //Get the TAG from the regex's results
                     $res = RuleAction::getRegexResultById($action->fields["value"],
                                                           $this->regex_results[0]);
                     if ($res != null) {
                        //Get the entity associated with the TAG
                        $target_entity = EntityData::getEntityIDByTag($res);
                        if ($target_entity != '') {
                           $output["entities_id"]=$target_entity;
                        }
                     }
                  }
                  break;
            }
         }
      }
      return $output;
   }

   function getCriterias() {
      global $LANG;
      
      $criterias = array ();

      $criterias['tag']['field']     = 'name';
      $criterias['tag']['name']      = $LANG['plugin_fusinvinventory']['rule'][101];
      
      $criterias['domain']['field']     = 'name';
      $criterias['domain']['name']      = $LANG['setup'][89];
      
      $criterias['subnet']['field']     = 'name';
      $criterias['subnet']['name']      = $LANG['networking'][61];
      
      $criterias['ip']['field']     = 'name';
      $criterias['ip']['name']      = $LANG['financial'][44]." ".$LANG['networking'][14];

      $criterias['name']['field']     = 'name';
      $criterias['name']['name']      = $LANG['rulesengine'][25];
      
      $criterias['serial']['field']     = 'name';
      $criterias['serial']['name']      = $LANG['common'][19];

      return $criterias;
   }

   function getActions() {
      global $LANG;
      
      $actions = array();
      $actions['entities_id']['name']  = $LANG['entity'][0];
      $actions['entities_id']['type']  = 'dropdown';
      $actions['entities_id']['table'] = 'glpi_entities';

      $actions['locations_id']['name']  = $LANG['common'][15];
      $actions['locations_id']['type']  = 'dropdown';
      $actions['locations_id']['table'] = 'glpi_locations';

      $actions['_affect_entity_by_tag']['name'] = $LANG['rulesengine'][131];
      $actions['_affect_entity_by_tag']['type'] = 'text';
      $actions['_affect_entity_by_tag']['force_actions'] = array('regex_result');

      $actions['_ignore_import']['name'] = $LANG['plugin_fusinvinventory']['rule'][102];
      $actions['_ignore_import']['type'] = 'yesonly';

      return $actions;
   }


   function displayAdditionalRuleCondition($condition, $criteria, $name, $value, $test=false) {
      if ($test) {
         return false;
      }

      switch ($condition) {
         case PluginFusioninventoryRule::PATTERN_FIND:
            return false;
            break;

         case PluginFusioninventoryRuleImportEquipment::PATTERN_IS_EMPTY :
            Dropdown::showYesNo($name, 0, 0);
            return true;

         case PluginFusioninventoryRule::PATTERN_EXISTS:
            echo Dropdown::showYesNo($name, 1, 0);
            return true;

         case PluginFusioninventoryRule::PATTERN_DOES_NOT_EXISTS:
            echo Dropdown::showYesNo($name, 1, 0);
            return true;
      }

      return false;
   }

}

?>