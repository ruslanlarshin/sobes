<?php
require_once($_SERVER["DOCUMENT_ROOT"].'/vendor/autoload.php');
$classes = [
  'Task' => '/local/lib/.../.../Task/Task.php',
  'Utils' => '/local/lib/.../.../Utils/Utils.php',
  '\...\...\TimeTask' => '/local/lib/.../.../TimeTask/TimeTask.php',
  '\...\...\User' => '/local/lib/.../.../User/User.php',
  '\...\...\Conciliator' => '/local/lib/.../.../Conciliator/Conciliator.php',
  '\...\...\ListProject' => '/local/lib/.../.../ListProject/ListProject.php',
  '\...\...\Comment' => '/local/lib/.../.../Comment/Comment.php',
  '\...\...\SocNetGroup' => '/local/lib/.../.../SocNetGroup/SocNetGroup.php',
  '\...\...\Bizproc' => '/local/lib/.../.../Bizproc/Bizproc.php',
  '\...\...\TagsDashboard' => '/local/lib/.../.../TagsDashboard/TagsDashboard.php',
  '\...\...\TrackDashboard' => '/local/lib/.../.../TrackDashboard/TrackDashboard.php',
  '\...\...\Risk' => '/local/lib/.../.../Risk/Risk.php',
  '\...\...\Status' => '/local/lib/.../.../Status/Status.php',
  'TaskFieldType' => '/local/lib/.../.../TaskFieldType/TaskFieldType.php',
  '\...\...\FilterDashboards' => '/local/lib/.../.../FilterDashboards/FilterDashboards.php',
  '\...\...\Reports' => '/local/lib/.../.../Reports/Reports.php',
  '\...\...\Escalation' => '/local/lib/.../.../Escalation/Escalation.php',
  '\...\...\Track' => '/local/lib/.../.../Track/Track.php',
  '\...\...\CodeProjectHighLoad' => '/local/lib/.../.../CodeProjectHighLoad/CodeProjectHighLoad.php',
];
\Bitrix\Main\Loader::registerAutoLoadClasses(null, $classes);
AddEventHandler("iblock", "OnIBlockPropertyBuildList", ["TaskFieldType", "GetUserTypeDescription"]);
AddEventHandler("tasks", "OnTaskAdd", ["\...\...\Task", "afterTaskAdd"]);
AddEventHandler("tasks", "OnBeforeTaskUpdate", ["\...\...\Task", "OnBeforeTaskUpdate"]);
AddEventHandler("tasks", "OnTaskDelete", ["\...\...\Task", "afterTaskDelete"]);
AddEventHandler("socialnetwork", "onSocNetGroupAdd", ["\...\...\SocNetGroup", "SocNetGroupAddHandler"]);
AddEventHandler("socialnetwork", "onSocNetGroupUpdate", ["\...\...\SocNetGroup", "SocNetGroupUpdateHandler"]);
AddEventHandler("socialnetwork", "onBeforeSocNetGroupAdd", ["\...\...\SocNetGroup", "BeforeSocNetGroupAddHandler"]);
AddEventHandler("socialnetwork", "OnBeforeSocNetGroupUpdate", ["\...\...\SocNetGroup", "BeforeSocNetGroupUpdateHandler"]);
AddEventHandler("socialnetwork", "onSocNetGroupDelete", ["\...\...\SocNetGroup", "SocNetGroupDeleteHandler"]);
?>