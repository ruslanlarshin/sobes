<?php

namespace ...\...;

use Bitrix\Highloadblock as HL;

\CModule::IncludeModule('highloadblock');
\CModule::IncludeModule('calendar');

class Utils
{
  private const logFile ='log.txt';
  /**
   * this function get entity for highLoad block by id
   * @param int $highloadId id HighLoad in DB
   * @return array entity or error
   */
  public static function init($highloadId)
  {
    try {
      $hlBlock = HL\HighloadBlockTable::getById($highloadId)->fetch();
      $entity = HL\HighloadBlockTable::compileEntity($hlBlock);
      $result = $entity->getDataClass();
    } catch (Exception $e) {
      return [
        'error' => [$e->getMessage()],
        'status' => 400,
      ];
    }
    return $result;
  }

  /**
   * this function get id for highLoad block by name
   * @param string $highLoadName - name by HighLoad bi DB
   * @return id (int) id HL by DB
   */
  public static function getIdByName($highLoadName)
  {
    if (!empty($highLoadName)) {
      $rHlblock = HL\HighloadBlockTable::getList(['filter' => ['NAME' => $highLoadName]]);
      return $rHlblock->fetchAll()[0]['ID'] ?? null;
    }
  }

  /**
   * this function return  date valid format("DD.MM.YYYY HH:MI:SS" or "DD.MM.YYYY") or not
   * @param date $date date time
   * @return bool valid form date time
   */
  public static function isDateTime($date)
  {
    global $DB;
    return $DB->IsDate($date, "DD.MM.YYYY HH:MI:SS");
  }

  /**
   * this function return  date valid format by d.m.Y or null
   * @param date $date who i s validated
   * @return date | null if valid - return params date else null
   */
  public static function returnOnlyValidDateTime($date)
  {
    if (self::isDateTime($date)) {
      return $date;
    }
    else {
      return null;
    }
  }

  /**
   * this function view json and die if script return value or error
   * @param array $data for view error
   * $data => [
   *  'value' => array result
   *  'error' => array string message about error,
   *  'status' => 200|400|401|404 /// http request status /
   * ]
   * @return json | true  die width json error or true
   */
  public static function viewJson($data)
  {
    if (!empty($data['error'])) {
      http_response_code($data['status'] ?: 400);
      die(json_encode(['error' => $data['error']], JSON_UNESCAPED_UNICODE));
    }
    else {
      http_response_code($data['status'] ?: 200);
      die(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function view json and die if script return error
   * @param array $data for view error
   * $data => [
   *  'error' => [] string message about error,
   *  'status' => 400|401|404 /// http request status / . default 400 noRequired
   * ]
   * @return json | true  die width json error or true
   */
  public static function viewErrorJson($data)
  {
    if (!empty($data['error'])) {
      http_response_code($data['status'] ?: 400);
      die(json_encode(['error' => $data['error']], JSON_UNESCAPED_UNICODE));
    }
    else {
      return true;
    }
  }

  /**
   * This function returns the status of the day: working day or weekend
   * @param date $date to be checked
   * @return string|array holiday | workday
   */
  public static function getDayStatus($date){
    $date = self::returnOnlyValidDateTime($date);
    if(!empty($date)){
      $settings = \CCalendar::GetSettings();//TODO проверить актуальность выходных дней [year_holidays] => 1.01,2.01,7.01,23.02,8.03,1.05,9.05,12.06,4.11
      $settings['year_holidays'] = explode(',' , $settings['year_holidays']);
      $dayByWeek = date('l', strtotime($date));
      if($dayByWeek === 'Saturday' || $dayByWeek === 'Sunday'){
        return 'holiday';
      }
      foreach($settings['year_holidays'] as $day){
        if(date('d.m', strtotime($date)) === $day){
          return 'holiday';
        }
      }
      return 'workday';
    }else{
      return ['error' => ['Невалидная дата']];
    }
  }

  /**
   * this function return all date after date1 and date2
   * @param date $start first date
   * @param date $end second date
   * @param string $format string format date
   * @return array dates for interval
   */
  public static function getDateByInterval($start, $end, $format = 'd.m.Y')
  {
    $day = 86400;
    $start = strtotime($start . ' -1 days');
    $end = strtotime($end . ' +1 days');
    $nums = round(($end - $start) / $day);
    $days = [];
    for ($i = 1; $i < $nums; $i++) {
      $days[] = date($format, ($start + ($i * $day)));
    }
    return $days;
  }

  /**
   * this function return monday in the week for date
   * @params date $date for OP
   * @return string date format d.m.Y
   */
  private static function getMondayByDate($date){
    $dayWeek = date('w', strtotime($date));
    if($dayWeek === 0 || $dayWeek === 6 || $dayWeek === 5){
      $monday = date('d.m.Y', strtotime('next week monday', strtotime($date)));
    }else{
      $monday = date('d.m.Y', strtotime('this week monday', strtotime($date)));
    }
    return $monday;
  }

  /**
   * this function return friday in the week for date
   * @params date $date for OP
   * @return string date format d.m.Y
   */
  public static function getFridayForDate($date){
    return date("d.m.Y", strtotime('-3 day', strtotime(self::getMondayByDate($date))));
  }

  /**
   * this function return friday in the week for date
   * @params date $date for OP
   * @return string date format d.m.Y
   */
  public static function getThursdayForDate($date){
    return date("d.m.Y", strtotime('+3 day', strtotime(self::getMondayByDate($date))));
  }

  /**
   * this function return last friday in the week
   * @return string date format d.m.Y
   */
  public static function getLastFriday(){
    return date("d.m.Y", strtotime('-3 day', strtotime('monday this week')));
  }

  /**
   * this function return last Thursday in the week
   * @return string date format d.m.Y
   */
  public static function getLastThursday(){
    return date("d.m.Y", strtotime('+3 day', strtotime('monday this week')));
  }

  /**
   * This function returns the ID of the information block by its code
   * @param string $code information block code
   * @return int information block ID
   */
  public static function getIbIdByCode($code){
    if(empty($code)){
      return 0;
    }
    $res = \CIBlock::GetList(
      [],
      ['CODE' => $code],
      true
    );
    if($arRes = $res->Fetch())
    {
      return intval($arRes['ID']);
    }
    return 0;
  }

  /**
   * this function return idProperty by code
   * @param string $propertyCode code by property
   * @return int id code property
   */
  public function getIdProperty($propertyCode)
  {
    try {
      $dbUserFields = \Bitrix\Main\UserFieldTable::getList([
        'filter' => [
          'FIELD_NAME' => $propertyCode,
          'ENTITY_ID' => 'TASKS_TASK'
        ]
      ]);
      if ($arUserField = $dbUserFields->fetch()) {
        return intval($arUserField['ID']);
      }
      return 0;
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * this function return idProperty IBlock by code
   * @param string $propertyCode code by property
   * @param string $iblockCode code by property
   * @return int id code property
   */
  public function getIdPropertyIblock($propertyCode, $iblockCode)
  {
    try {
      $properties = \CIBlockProperty::GetList(
        [],
        [
          'CODE' => $propertyCode,
          'IBLOCK_CODE' => $iblockCode,
        ]
      );
      if ($propFields = $properties->GetNext())
      {
        return intval($propFields['ID']);
      }
      return 0;
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * this function return list values by property code
   * @param string $propertyCode code by value
   * @return array items by value
   *  $result => [
   *    'id' => int id by property value
   *    'name' => string name by property value
   *  ]
   */
  public static function getListProperty($propertyCode){
    try {
      $enum = \Bitrix\Main\UserFieldTable::getFieldData(self::getIdProperty($propertyCode));
      foreach($enum['ENUM'] as $value){
        $result[] = [
          'id' => intval($value['ID']),
          'name' => $value['VALUE'],
        ];
      }
      return $result ?? [];
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function writeToLog object
   * @param array $object object write to log
   */
  public static function writeLog($object){
    $logDirname = self::logFile;
    if (!file_exists($logDirname)) {
      mkdir($logDirname, 0777, true);
    }
    ob_start();
    view($object);
    $log = date('Y-m-d H:i:s') . PHP_EOL . ob_get_clean() . PHP_EOL;
    file_put_contents($_SERVER["DOCUMENT_ROOT"] . '/' . $logDirname, $log, FILE_APPEND);
  }

  /**
   * this function format size bytes in large value
   * @param int $bytes file size
   * @param int $precision precision for return value
   * @return string file Size
   */
  public static function formatBytes($bytes) {
    $bytes = intval($bytes);
    if ($bytes >= 1073741824) {
      $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    }
    elseif ($bytes >= 1048576) {
      $bytes = number_format($bytes / 1048576, 2) . ' MB';
    }
    elseif ($bytes >= 1024) {
      $bytes = number_format($bytes / 1024, 2) . ' KB';
    }
    elseif ($bytes > 1) {
      $bytes = $bytes . ' байты';
    }
    elseif ($bytes === 1) {
      $bytes = $bytes . ' байт';
    }
    else {
      $bytes = '0 байтов';
    }
    return $bytes;
  }
}