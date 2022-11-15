<?php

namespace ...\...;

use \Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\PageNavigation;
use ...\...\{User, Task, ListProject};

class Status
{
  public const codeIb = 'STATUS';
  public const propertyProject = 'PROJECT';
  public const codeList = 'status';

  /**
   * this function return data for grid risks
   * @return array $result list and nav param
   *  $result = [
   *    'list' => array list by data
   *    'navigation' => array navigation by bitrix api
   *  ]
   */
  public static function getDataForGrid($projectId)
  {
    $listId = self::codeList;
    $gridOptions = new GridOptions($listId);
    $sort = $gridOptions->GetSorting(['sort' => ['DATE_CREATE' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
    $navParams = $gridOptions->GetNavParams();

    $nav = new PageNavigation($listId);
    $nav->allowAllRecords(true)
      ->setPageSize($navParams['nPageSize'])
      ->initFromUri();
    if ($nav->allRecordsShown()) {
      $navParams = false;
    }
    else {
      $navParams['iNumPage'] = $nav->getCurrentPage();
    }

    $filterOption = new \Bitrix\Main\UI\Filter\Options($listId);
    $filterData = $filterOption->getFilter([]);

    $filter['NAME'] = "%" . $filterData['FIND'] . "%";
    foreach ($filterData as $code => $value) {
      if (is_array($value)) {
        $filter[$code] = $value;
      }
      elseif ($code === 'DATE_CREATE_from') {
        $filter['>=DATE_CREATE'] = $value;
      }
      elseif ($code === 'DATE_CREATE_to') {
        $filter['<=DATE_CREATE'] = $value;
      }
      else {
        $filter[$code] = "%" . $value . "%";
      }
    }
    $filter['IBLOCK_CODE'] = self::codeIb;
    $filter['PROPERTY_PROJECT'] = ListProject::getProjectListIdByProjectId($projectId)['id'];
    $res = \CIBlockElement::GetList($sort['sort'], $filter, false, $navParams,
      ["ID", 'CREATED_USER_NAME', 'NAME', 'DETAIL_TEXT', 'PROPERTY_FILE', 'DATE_CREATE']
    );
    $nav->setRecordCount($res->selectedRowsCount());
    $list = [];
    while ($row = $res->GetNext()) {
      $fileList = '';
      foreach ($row['PROPERTY_FILE_VALUE'] as $fileId) {
        $file = \CFile::GetFileArray($fileId);
        $url = $file['SRC'];
        if (!empty($url)) {
          ob_start();
          ?>
            <div>Файл: <a href='<?= $url ?>'><?= $file['ORIGINAL_NAME'] ?></a></div>
            <div>Размер : <?= Utils::formatBytes($file['FILE_SIZE']) ?></div>
            <a href='<?= $url ?>' download='<?= $url ?>'>[ Скачать ]</a>
            <p></p>
          <?php
          $fileList .= ob_get_contents();
          ob_end_clean();
        }
      }
      $roleInProject = Task::roleUserInProject($projectId)['role'];
      $actions = [];
      if ($roleInProject === SONET_ROLES_OWNER || $roleInProject === SONET_ROLES_MODERATOR) {
        $actions[] = [
          'text' => 'Редактировать',
          'default' => true,
          'onclick' => 'document.location.href="/company/personal/status/edit.php?projectId=' . $projectId . '&id=' . $row['ID'] . '"'
        ];
      }
      if ($roleInProject === SONET_ROLES_OWNER) {
        $actions[] = [
          'text' => 'Удалить',
          'default' => true,
          'onclick' => "if(confirm('Вы точно хотите удалить элемент?')){deleteStatus({$row['ID']});}"
        ];
      }
      $list[] = [
        'data' => [
          "ID" => $row['ID'],
          "NAME" => $row['NAME'],
          "PROPERTY_FILE" => $fileList,
          "CREATED_USER_NAME" => $row['CREATED_USER_NAME'],
          "DETAIL_TEXT" => $row['DETAIL_TEXT'],
          "DATE_CREATE" => $row['DATE_CREATE'],
        ],
        'actions' => $actions,
      ];
    }
    return [
      'list' => $list,
      'navigation' => $nav
    ];
  }

  /**
   * this function return exists element or not
   * @param int $statusId id by risk items
   * @return array ['exists'] value exists
   * $result = [
   *  'exists' => boolean exists or not
   *  'error' => array error
   * ]
   */
  private static function ifExists($statusId)
  {
    try {
      $res = \CIBlockElement::GetList(
        [],
        [
          'IBLOCK_CODE' => self::codeIb,
          'ID' => $statusId,
        ],
        false,
        [],
        ["ID"]
      );
      if ($row = $res->GetNext()) {
        return ['exists' => true];
      }
      else {
        return ['exists' => false];
      }
    } catch (Exception $e) {
      return ['exists' => false];
    }
  }

  /**
   * this function delete risk item by list
   * @param int $statusId id by risk
   * @return json array by result
   * $result = [
   *  'id' => int id delete status
   *  'error' => array error
   * ]
   */
  public static function deleteStatus($statusId)
  {
    try {
      if (!empty($statusId) && self::ifExists($statusId)['exists']) {
        \CIBlockElement::Delete($statusId);
      }
      else {
        http_response_code(400);
        die(json_encode(['error' => ['Элемент не найден']], JSON_UNESCAPED_UNICODE));
      }
      http_response_code(200);
      die(json_encode(['id' => $statusId], JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return last status project for date
   * @param int $projectId id by project
   * @param date $date date for report
   * @return array $result data by last status or error
   * $result = [
   *  'id' => int id status
   *  'text' => string text status
   *  'error' => array error
   * ]
   */
  public static function getStatusProjectForDate($projectId, $date = '')
  {
    try {
      if (empty($projectId)) {
        return ['error' => 'Необходим идентификатор проекта'];
      }
      $filter =  [
        'IBLOCK_CODE' => self::codeIb,
        'PROPERTY_PROJECT' => ListProject::getProjectListIdByProjectId($projectId),
      ];
      if(empty($date)){
          $filter['<=DATE_CREATE'] = $date;
      }
      $res = \CIBlockElement::GetList(
        ['DATE_CREATE' => 'DESC'],
        $filter,
        false,
        ['nTopCount' => 1],
        ["ID",  'DETAIL_TEXT'],
      );
      if ($row = $res->GetNext()) {
        $result = [
          'id' => intval($row['ID']),
          'text' => $row['~DETAIL_TEXT'],
        ];
        return $result;
      }
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return last status project with file for dashboard
   * @param int $projectId id by project
   * @return array $result data by last status or error
   * $result = [
   *  'id' => int id status
   *  'text' => string text status
   *  'date' => date create status
   *  'user' => [
   *        'id' => int id user
   *        'name' => string name user
   *    ]
   *  'files' => array files
   *  'error' => array error
   * ]
   */
  public static function getStatusProjectCommentLast($projectId)
  {
    try {
      if (empty($projectId)) {
        return ['error' => 'Необходим идентификатор проекта'];
      }
      $res = \CIBlockElement::GetList(
        ['DATE_CREATE' => 'DESC'],
        [
          'IBLOCK_CODE' => self::codeIb,
          'PROPERTY_PROJECT' => ListProject::getProjectListIdByProjectId($projectId),
        ],
        false,
        ['nTopCount' => 1],
        ["ID", 'DATE_CREATE', 'NAME', 'DETAIL_TEXT', 'PROPERTY_PROJECT', 'PROPERTY_FILE', 'CREATED_USER_NAME', 'CREATED_BY'],
      );
      if ($row = $res->GetNext()) {
        $result = [
          'id' => intval($row['ID']),
          'text' => $row['~DETAIL_TEXT'],
          'date' => $row['DATE_CREATE'],
          'user' => [
            'id' => intval($row['CREATED_BY']),
            'name' => $row['CREATED_USER_NAME'],
          ],
          'files' => self::getFileComment($row['PROPERTY_FILE_VALUE']),
        ];
        return $result;
      }
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return all files by comment
   * @param array $files id by files
   * @return array info[] about attachment files
   * $result = [
   *  'id' => int id file
   *  'name' => string name file
   *  'type' => string type file
   *  'size' => string size in Mb/Gb
   *  'fileId' => int id file in system
   *  'download' => url file for download
   * ]
   */
  private static function getFileComment($files)
  {
    $result = [];
    foreach ($files as $fileId) {
      $id = intval($fileId);
      $file = \CFile::GetFileArray($fileId);
      $name = explode('.', $file['FILE_NAME']);
      $name = $name[count($name) - 1];
      $result[] = [
        'id' => intval($id),
        'name' => $file['FILE_NAME'],
        'type' => $name,
        'size' => $file['FILE_SIZE'],
        'fileId' => intval($id),
        'download' => $file['SRC'],
      ];
    }
    return $result;
  }
}