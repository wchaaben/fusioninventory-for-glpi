<?php

define('PHPUnit_MAIN_METHOD', 'Plugins_Fusioninventory_XmlIntegrity::main');
    
if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', '../../../..');
   $_SESSION['glpi_use_mode'] = 2;
   require_once GLPI_ROOT."/inc/includes.php";

   ini_set('display_errors','On');
   error_reporting(E_ALL | E_STRICT);
   set_error_handler("userErrorHandler");

}

/**
 * Test class for MyFile.
 * Generated by PHPUnit on 2010-08-06 at 12:05:09.
 */
class Plugins_Fusioninventory_XmlIntegrity extends PHPUnit_Framework_TestCase {

    public static function main() {
        require_once 'PHPUnit/TextUI/TestRunner.php';

        $suite  = new PHPUnit_Framework_TestSuite('Plugins_Fusioninventory_XmlIntegrity');
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

   public function testXmlIntegrityProlog_Freq() {
      require_once 'emulatoragent.php';

      $input_xml = '<?xml version="1.0" encoding="UTF-8"?>
<REQUEST>
  <DEVICEID>agenttest-2010-03-09-09-41-28</DEVICEID>
  <QUERY>PROLOG</QUERY>
  <TOKEN>NTMXKUBJ</TOKEN>
</REQUEST>';

      $emulatorAgent = new emulatorAgent;
      $emulatorAgent->server_urlpath = "/glpi078/plugins/fusioninventory/front/communication.php";
      $return_xml = $emulatorAgent->sendProlog($input_xml);      

      $this->assertEquals(strstr($return_xml, "<PROLOG_FREQ>24</PROLOG_FREQ>"), true , 'Problem on integrity of XML response : PROLOG_FREQ');
   }
   
}

// Call Plugins_Fusioninventory_Discovery_Newdevices::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == 'Plugins_Fusioninventory_XmlIntegrity::main') {
    Plugins_Fusioninventory_XmlIntegrity::main();
}
?>