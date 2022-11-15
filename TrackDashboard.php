<?php

// не могу показать ни проект, ни используемые модули.... политика компании

\CModule::IncludeModule('tasks');

class TrackDashboard
{

  private const tagsPropertyCode = 'UF_TRACK';
  private const pageSize = 10;
  private const sort = ['name', 'responsible', 'deadline', 'status'];
  private const sortType = ['ASC', 'DESC'];

  /**
   * this function get first data(1 page for all level)
   * @param array $request array for filter
   * $request => [
   *  'projectId' => id by project
   *  'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   *  'sort' => [
   *     'name' => by self::sort
   *     'type' => by self::sortType
   *   ]
   * ]
   * @return json error or list by dashboard tags
   * $result => array three tasks
   */
  public static function getDashboardTags($request)
  {
    if (empty($request['projectId'])) {
      http_response_code(400);
      die(json_encode(['error' => ['Необходим идентификатор проекта']], JSON_UNESCAPED_UNICODE));
    }
    if (empty($request['filter'])) {
      $result['filter'] = FilterDashboards::filterDashboardTagsForUser($request['projectId'])['filter'];
    }
    $result['sort'] = [
      'name' => self::sort,
      'type' => self::sortType,
    ];
    $result['info'] = self::getProjectInfo($request['projectId']);
    $result['list'] = [];
    //$tracks = Utils::getListProperty(self::tagsPropertyCode);//TODO здесь меняем
    $tracks = Track::getTracksList($request['projectId'])['items'];
    foreach ($tracks as $track) {
      $request['trackId'] = $track['id'];
      if (intval($result['filter']['trackId']) === intval($track['id'])) {
        $bufArray = $request;
        $bufArray['filter'] = $result['filter'];
        $items = self::getTrackParents($bufArray);
      }
      else {
        $items = self::getTrackParents($request);
      }
      if (!empty($items['items']) || intval($result['filter']['trackId']) === intval($track['id'])) {
        $result['list'][] = [
          'id' => intval($track['id']),
          'title' => $track['name'],
          'type' => 'track',
          'active' => 'Y',
          'save' => (intval($result['filter']['trackId']) === intval($track['id'])) ? 'Y' : 'N',
          'level' => 0,
          'list' => $items,
        ];
      }
    }
    $request['trackId'] = 0;
    if (intval($result['filter']['trackId']) === '0') { //TODO intval('0') === '0'
      $bufArray = $request;
      $bufArray['filter'] = $result['filter'];
      $itemsNoTrack = self::getTrackParents($bufArray);
    }else {
      $itemsNoTrack = self::getTrackParents($request);
    }
    if (!empty($itemsNoTrack['items'])) {
      $result['list'][] = [
        'id' => 0,
        'title' => 'Без трека',
        'type' => 'track',
        'active' => 'Y',
        'save' => (intval($result['filter']['trackId']) === 0)  ? 'Y' : 'N',
        'level' => 0,
        'list' => $itemsNoTrack,
      ];
    }
    http_response_code(200);
    die(json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  /**
   * this function return sort array by params
   * @param array $request params by sort
   * $request => [
   *     'sort' => [
   *        'name' => by self::sort
   *        'type' => by self::sortType
   *      ]
   * ]
   * @return array sort by getList
   * $result = [sortName => sortType]
   */
  private static function getSort($request)
  {
    if ($request['sort']) {
      switch ($request['sort']['name']) {
        case 'task':
          $sort = 'TITLE';
          break;
        case 'responsible':
          $sort = 'RESPONSIBLE_LAST_NAME';
          break;
        case 'deadline':
          $sort = 'DEADLINE';
          break;
        case 'planeTime':
          $sort = 'END_DATE_PLAN';
          break;
        case 'status':
          $sort = 'STATUS';
          break;
        default:
          $sort = 'TITLE';
      }
      $arSort = [$sort => $request['sort']['type'] ?: 'ASC', 'ID' => 'ASC'];
    }
    else {
      $arSort = ['TITLE' => 'ASC', 'ID' => 'ASC'];
    }
    return $arSort;
  }

  /**
   * this function return tracks group (no tasks for pagination)
   * @param array $request projectId required and track id other filter maybe
   * $request => [
   *    'projectId' => int id by project
   *    'trackId' => int id by track
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   *    'page' => int page. default 1
   *    'sort' => [
   *        'name' => by self::sort
   *        'type' => by self::sortType
   *      ]
   *  ];
   * @return array list tracks or error
   * $result = [
   *    'id' => int id by task
   *    'title' => string title task
   *    'level' => int level task
   *    'active' => boolean active task or not
   *    'info' => [
   *       'user' => array user array
   *       'deadline' => date deadline
   *       'status' => array status
   *       'escalation' => boolean escalation value
   *       'risk' => array ids task
   *       'taskRisks' => string tasks with url
   *       'list' => array recursive three
   *    ]
   *    'error' => array errors
   *  ]
   */
  public static function getTrackParents($request)
  {//TODO сейчас треки используются только родителя, для подзадач свойство не проверяется!! если изменится то начинать отсюда
    try {
      if (empty($request['projectId'])) {
        return ['error' => 'Не введен идентификатор проекта'];
      }
      if (!isset($request['trackId'])) {
        return ['error' => 'Не введен идентификатор трека'];
      }
      if (!empty($request['filter'])) {
        $ids = self::getTaskIdsFromFilters($request);
      }
      $result['items'] = [];
      $filter = [
        '::SUBFILTER-1' => [
          '::LOGIC' => 'OR',
          '::SUBFILTER-1' => [
            'PARENT_ID' => false,
          ],
          '::SUBFILTER-2' => [
            'PARENT_ID' => 0,
          ],
        ],
        'ID' => $ids['taskIdsByFilterAndAllParent'],
        'GROUP_ID' => $request['projectId'],
      ];
      if ($request['trackId'] !== 0) {
        if($filter['ID'][count($filter['ID']) - 1] === 0){
          unset($filter['ID'][count($filter['ID']) - 1]);
        }
        if(!empty($filter['ID'])) {
          $filter['ID'] = array_intersect($filter['ID'] ?: [], Track::getTaskIdsByTrackId($request['trackId'], $request['projectId'])['ids']);
        }else{
          $filter['ID'] =  Track::getTaskIdsByTrackId($request['trackId'], $request['projectId'])['ids'];
        }
      }
      else {
        $filter['!ID'] = Track::getTaskIdsByTrackId($request['trackId'], $request['projectId'])['ids'];
      }
      $res = \CTasks::GetList(
        self::getSort($request),
        $filter,
        ['ID', 'RESPONSIBLE_ID', 'DEADLINE', 'STATUS', 'TITLE', 'END_DATE_PLAN', Escalation::externalCode, Escalation::internalCode, 'PARENT_ID', self::tagsPropertyCode],
        [
          'NAV_PARAMS' =>
            [
              'nPageSize' => self::pageSize,
              'iNumPage' => $request['page'] ?: 1,
            ]
        ]
      );
      $userId = User::getId();
      while ($arTask = $res->GetNext()) {
        $active = 'Y';
        if (!empty($request['filter'])) {
          $active = in_array($arTask['ID'], $ids['activeTaskIds']) ? 'Y' : 'N';
        }
        $escalation = [];
        if($arTask[Escalation::externalCode]){
          $escalation[] = 'Внешняя эскалация';
        }
        if($arTask[Escalation::internalCode]){
          $escalation[] = 'Внутренняя эскалация';
        }
        $childrenRequest = $request;
        $childrenRequest['taskId'] = $arTask['ID'];
        $riskTask = Risk::getInfoForTask($arTask['ID']);
        $result['items'][] = [
          'id' => intval($arTask['ID']),
          'title' => $arTask['TITLE'],
          'type' => 'parentTask',
          'url' => "/company/personal/user/{$userId}/tasks/task/view/{$arTask['ID']}/",
          'level' => Task::getLevelTask($arTask['ID'])['id'],
          'active' => $active,
          'info' => [
            'user' => User::getPhotoAndFullName($arTask['RESPONSIBLE_ID']),
            'deadline' => Utils::returnOnlyValidDateTime($arTask['DEADLINE']),
            'planeTime' => Utils::returnOnlyValidDateTime($arTask['END_DATE_PLAN']),
            'status' => [
              'id' => intval($arTask['STATUS']),
              'name' => Task::status[intval($arTask['STATUS'])],
            ],
            'escalation' => $escalation,
            'risk' => $riskTask['names'],
            'taskRisks' => $riskTask['tasksString'],
            'comment' => Comment::getLastCommentByTask($arTask['ID']),
          ],
          'list' => self::getChildrenTasks($childrenRequest),
        ];
      }
      $resCount = \CTasks::GetList(
        [],
        $filter,
        ['ID', 'RESPONSIBLE_ID', 'DEADLINE', 'STATUS', 'TITLE', 'PARENT_ID', self::tagsPropertyCode],
      );
      $count = $resCount->SelectedRowsCount();
      if ($count > 0) {
        $result['pager'] = [
          'page' => intval($request['page'] ?: 1),
          'count' => intval($count),
          'pageCount' => ceil($count / self::pageSize),
        ];
      }
      return $result ?? [];
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return child for task parent with pager and sort
   * @param array $request taskId required and other params
   * $request =
   *  [
   *    'taskId' => int id by parent task
   *    'projectId' => int id by project
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   *    'sort' => [
   *        'name' => self::sort
   *        'type' => self:sortType
   *      ]
   *  ]
   * @return array three tasks child
   * $result = [
   *    'id' => int id by task
   *    'title' => string title task
   *    'level' => int level task
   *    'active' => boolean active task or not
   *    'info' => [
   *       'user' => array user array
   *       'deadline' => date deadline
   *       'status' => array status
   *       'escalation' => boolean escalation value
   *       'risk' => array ids task
   *       'taskRisks' => string tasks with url
   *       'list' => array recursive three
   *    ]
   *    'error' => array errors
   *  ]
   */

  public static function getChildrenTasks($request)
  {
    try {
      if (empty($request['projectId'])) {
        return ['error' => 'Не введен идентификатор проекта'];
      }
      if (empty($request['taskId'])) {
        return ['error' => 'Не введен идентификатор родительской задачи'];
      }
      if (!empty($request['filter'])) {
        $ids = self::getTaskIdsFromFilters($request);
      }
      $res = \CTasks::GetList(
        self::getSort($request),
        [
          'GROUP_ID' => $request['projectId'],
          'PARENT_ID' => $request['taskId'],
          'ID' => $ids['taskIdsByFilterAndAllParent'],
        ],
        ['ID', 'RESPONSIBLE_ID', 'DEADLINE', 'STATUS', 'END_DATE_PLAN', 'TITLE', 'PARENT_ID', Escalation::externalCode, Escalation::internalCode, self::tagsPropertyCode],
        [
          'NAV_PARAMS' =>
            [
              'nPageSize' => self::pageSize,
              'iNumPage' => $request['page'] ?: 1,
            ]
        ]
      );
      $type = 'task';
      $userId = User::getId();
      while ($arTask = $res->GetNext()) {
        $active = 'Y';
        if (!empty($request['filter'])) {
          $active = in_array($arTask['ID'], $ids['activeTaskIds']) ? 'Y' : 'N';
        }
        $escalation = [];
        if($arTask[Escalation::externalCode]){
          $escalation[] = 'Внешняя эскалация';
        }
        if($arTask[Escalation::internalCode]){
          $escalation[] = 'Внутренняя эскалация';
        }
        $childrenRequest = $request;
        $childrenRequest['taskId'] = $arTask['ID'];
        $result['items'][] = [
          'id' => intval($arTask['ID']),
          'title' => $arTask['TITLE'],
          'type' => $type,
          'url' => "/company/personal/user/{$userId}/tasks/task/view/{$arTask['ID']}/",
          'level' => Task::getLevelTask($arTask['ID'])['id'],
          'active' => $active,
          'info' => [
            'user' => User::getPhotoAndFullName($arTask['RESPONSIBLE_ID']),
            'deadline' => Utils::returnOnlyValidDateTime($arTask['DEADLINE']),
            'planeTime' => Utils::returnOnlyValidDateTime($arTask['END_DATE_PLAN']),
            'status' => [
              'id' => intval($arTask['STATUS']),
              'name' => Task::status[intval($arTask['STATUS'])],
            ],
            'escalation' => $escalation,
            'risk' => [],
            'taskRisks' => [],
            'comment' => Comment::getLastCommentByTask($arTask['ID']),
          ],
          'list' => self::getChildrenTasks($childrenRequest),
        ];
      }
      $resCount = \CTasks::GetList(
        [],
        [
          'GROUP_ID' => $request['projectId'],
          'PARENT_ID' => $request['taskId'],
          'ID' => $ids['taskIdsByFilterAndAllParent'],
        ],
        ['ID', 'RESPONSIBLE_ID', 'DEADLINE', 'STATUS', 'TITLE', 'PARENT_ID', self::tagsPropertyCode],
      );
      $count = $resCount->SelectedRowsCount();
      if ($count > 0) {
        $result['pager'] = [
          'page' => intval($request['page'] ?: 1),
          'count' => intval($count),
          'pageCount' => ceil($count / self::pageSize),
        ];
      }
      return $result ?? [];
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return child for task parent with pager and sort
   * @param array $request taskId required and other params
   * $request =
   *  [
   *    'taskId' => int id by parent task
   *    'projectId' => int id by project
   *    'page' => int page now
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   *
   *    'sort' => [
   *        'name' => self::sort
   *        'type' => self:sortType
   *      ]
   *  ]
   */
  public static function getChildrenJson($request)
  {
    $result = self::getChildrenTasks($request);
    if (!empty($result['error'])) {
      http_response_code($result['status'] ?: 400);
      die(json_encode(['error' => $result['error']], JSON_UNESCAPED_UNICODE));
    }
    http_response_code(200);
    die(json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  /**
   * this function return tracks group (no tasks for pagination)
   * @param array $request projectId required and track id other filter maybe
   * $request => [
   *    'projectId' => int id by project
   *    'trackId' => int id by track
   *    'page' => int page. default 1
   *  ];
   * @return json list tracks or error
   * $result = [
   *    'items' => array tasks by three
   *    'error' => array errors
   *  ]
   */
  public static function getTrackParentsJson($request)
  {
    if ($request['filter']['deadline'] === null) {
      unset($request['filter']['deadline']);
    }
    if (!empty($request['filter'])) {
      $request['filter']['trackId'] = $request['trackId'];
      FilterDashboards::saveDashboardFilterTags($request['projectId'], $request['filter']);
      $result['filter'] = $request['filter'];
    }
    else {
      $result['filter'] = FilterDashboards::filterDashboardTagsForUser($request['projectId']);
      $request['filter'] = $result['filter'];
    }
    $result = self::getTrackParents($request);
    if (!empty($result['error'])) {
      http_response_code($result['status'] ?: 400);
      die(json_encode(['error' => $result['error']], JSON_UNESCAPED_UNICODE));
    }
    http_response_code(200);
    die(json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  /**
   * this function return info about project and status all task
   * @param int $projectId id by project
   * @return array $result or error
   * $result = [
   *  'name' => string name by project
   *  'project' => [
   *    'total' => int count all task
   *    'performed' => int count performed
   *    'overdue' => int count overdue
   *    'waiting' => int count waiting
   *    'escalation' => int count escalation
   *  ]
   * ]
   */
  private static function getProjectInfo($projectId)
  {
    try {
      $result = [
        'name' => Task::getProjectName($projectId),
        'project' => [
          'total' => 0,
          'performed' => 0,
          'overdue' => 0,
          'working' => 0,
          'escalation' => 0,
        ],
      ];
      $escalationCode = Task::getEscalationPropertyCode();
      $res = \CTasks::GetList(
        [],
        [
          '!' . $escalationCode => false,
          'GROUP_ID' => $projectId
        ],
        ['ID', $escalationCode]
      );
      $result['project']['escalation'] = intval(Escalation::getCountByFilter(['internalCode' => 'Y', 'externalCode' => 'Y'])['cnt']);
      foreach (Task::statusList as $statusName => $statusIds) {
        $res = \CTasks::GetList(
          [],
          [
            'STATUS' => $statusIds,
            'GROUP_ID' => $projectId
          ],
          ['ID']
        );
        $result['project'][$statusName] = intval($res->AffectedRowsCount());
        $result['project']['total'] += $result['project'][$statusName];
      }
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return all ids by tracks only parent
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'tagsId' => int by tags
   * ];
   * @return array $result ids by task
   * $result = [
   *    'ids' => array ids task
   *    'error' => array errors
   *  ]
   */
  private static function getAllTaskByTracks($request)
  {
    try {
      $result = [];
      $res = \CTasks::GetList(
        self::getSort($request),
        [
          '::SUBFILTER-1' => [
            '::LOGIC' => 'OR',
            '::SUBFILTER-1' => [
              'PARENT_ID' => false,
            ],
            '::SUBFILTER-2' => [
              'PARENT_ID' => 0,
            ],
          ],
          'ID' => Track::getTaskIdsByTrackId($request['trackId'], $request['projectId'])['ids'],
          'GROUP_ID' => $request['projectId'],
          //self::tagsPropertyCode => $request['trackId'],TODO проверить после перехода
        ],
        ['ID'],
      );
      while ($arTask = $res->GetNext()) {
        $result['ids'][] = intval($arTask['ID']);
      }
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return all task by filter in all level(all project,all track)
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => [
   *      'type' => task | responsible
   *      'text' => string text
   *    ]
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return array $result ids by task
   * $result = [
   *    'ids' => array ids task for filter
   *    'error' => array errors
   *  ]
   */
  private static function getAllIdsTaskChildByFilter($request)
  {
    try {
      $result = [];
      $filter = [
        '::SUBFILTER-1' => [
          '::LOGIC' => 'OR',
          '::SUBFILTER-1' => [
            '!PARENT_ID' => false,
          ],
          '::SUBFILTER-2' => [
            '!PARENT_ID' => 0,
          ],
        ],
        'GROUP_ID' => $request['projectId'],
        //self::tagsPropertyCode => $request['trackId'],
      ];
      if (!empty($request['filter']['responsible'])) {
        $filter['RESPONSIBLE_ID'] = $request['filter']['responsible'];
      }
      if (!empty($request['filter']['taskIds'])) {
        $filter['ID'] = $request['filter']['taskIds'];
      }

      if (!empty($request['filter']['escalations'])) {
        if(count($request['filter']['escalations']) === 1){
          if(in_array('internalCode', $request['filter']['escalations']))
          {
            $filter[Escalation::internalCode] = true;
          }elseif(in_array('externalCode', $request['filter']['escalations'])){
            $filter[Escalation::externalCode] = true;
          }elseif(in_array('none', $request['filter']['escalations'])){
            $filter[Escalation::externalCode] = false;
            $filter[Escalation::internalCode] = false;
          }
        }else{
          $filterLogic = ['LOGIC' => 'OR'];
          if(in_array('internalCode', $request['filter']['escalations']))
          {
            $filterLogic[] = [Escalation::internalCode => true];
          }
          if(in_array('externalCode', $request['filter']['escalations'])){
            $filterLogic[] = [Escalation::externalCode => true];
          }
          if(in_array('none', $request['filter']['escalations'])){
            $filterLogic[] = [
              Escalation::externalCode => false,
              Escalation::internalCode => false,
            ];
          }
          $filter[] = $filterLogic;
        }
      }
      if (!empty($request['filter']['deadline']['end']) && $request['filter']['deadline']['end'] !== 'null') {//TODO приходит null строкой?!?
        $filter['<=DEADLINE'] = $request['filter']['deadline']['end'];
      }
      if (!empty($request['filter']['deadline']['begin']) && $request['filter']['deadline']['begin'] !== 'null') {
        $filter['>=DEADLINE'] = $request['filter']['deadline']['begin'];
      }
      if (!empty($request['filter']['planeTime']['end']) && $request['filter']['planeTime']['end'] !== 'null') {
        $filter['<=END_DATE_PLAN'] = $request['filter']['planeTime']['end'];
      }
      if (!empty($request['filter']['planeTime']['begin']) && $request['filter']['planeTime']['begin'] !== 'null') {
        $filter['>=END_DATE_PLAN'] = $request['filter']['planeTime']['begin'];
      }
      if (!empty($request['filter']['statuses'])) {
        $filter['STATUS'] = $request['filter']['statuses'];
      }
      if (!empty($request['search']['type']) && !empty($request['search']['text'])) {
        if ($request['search']['type'] === 'task') {
          $filter['%TITLE'] = $request['search']['text'];
        }
      }
      $res = \CTasks::GetList(
        self::getSort($request),
        $filter,
        ['ID'],
      );
      while ($arTask = $res->GetNext()) {
        $result['ids'][] = intval($arTask['ID']);
      }
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return all parents ids withjout all filter and main parent fot task
   * @param array $request array param for filter
   * $request = [
   *   'taskId' => int id by task
   *   'taskIds' = > array all level tasks
   * ];
   * @return array $result ids by task
   * $result = [
   *    'taskIds' => array ids task
   *    'taskId' => int id task
   *    'error' => array errors
   *  ]
   */
  public static function getAllParentsByTask($request)
  {
    try {
      $result = [];
      $res = \CTasks::GetList(
        self::getSort($request),
        [
          'ID' => $request['taskId'],
        ],
        ['PARENT_ID'],
      );
      if ($arTask = $res->GetNext()) {
        $request['taskIds'][] = intval($request['taskId']);
        if (!empty($arTask['PARENT_ID'])) {
          $request['taskId'] = intval($arTask['PARENT_ID']);
          $request['taskIds'] = self::getAllParentsByTask($request)['taskIds'];
        }
      }
      return $request;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return info for task with child
   * @param array $request array param for filter
   * $request = [
   *   'taskId' => int id by project
   *   'taskIds' = array valid ids for childs
   *   'activeIds' = array valid ids all tasks by filter
   * ];
   * @return array $result ids by task
   * $result = [
   *    'id' => int id task
   *    'title' => string title by task
   *    'level' => int level task
   *    'active' => boolean active task by filter(for parent with active child)
   *    'items' => array items task by three
   *    'error' => array errors
   *  ]
   */
  private static function getTaskInfoByFilter($request)
  {
    try {
      $request['level'] = intval($request['level']) + 1;
      $res = \CTasks::GetList(
        self::getSort($request),
        [
          'ID' => $request['taskId'],
          'PARENT_ID' => $request['parentId'] ?? []
        ],
        ['ID', 'PARENT_ID', 'TITLE'],
      );
      if ($arTask = $res->GetNext()) {
        $result = [
          'id' => intval($arTask['ID']),
          'title' => $arTask['TITLE'],
          'level' => intval($request['level']) - 1,
          'active' => in_array($arTask['ID'], $request['activeIds']) ? 'Y' : 'N',
          'items' => [],
        ];
        $childTaskIds = array_diff(Task::getChild($arTask['ID'], false), [$arTask['ID']]);
        if (!empty($childTaskIds)) {
          foreach ($childTaskIds as $childTaskId) {
            if (in_array($childTaskId, $request['taskIds'])) {
              $result['items'][] = array_merge(
                ['active' => in_array($childTaskId, $request['activeIds'])  ? 'Y' : 'N'],
                self::getTaskInfoByFilter([
                    'taskId' => $childTaskId,
                    'taskIds' => $request['taskIds'],
                    'level' => $request['level'],
                    'activeIds' => $request['activeIds'],
                  ]
                )
              );
            }
          }
        }
      }
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];
    }
  }

  /**
   * this function return valid Parent Tracks, valid task by Filter, all Parent for valid task
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => [
   *      'type' => task | responsible
   *      'text' => string text
   *    ]
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return array $result ids by task
   *  $result => [
   *    'parentTaskValidFromFilter' => array ids valid parent task for track, project and valid child for filter,
   *    'taskIdsByFilterAndAllParent' => array ids task all task where have valid child
   *    'activeTaskIds' => array ids task valid child or parent for all filter
   *  ];
   */
  private static function getTaskIdsFromFilters($request)
  {
    $taskIdsParentsByFilter = self::getAllTaskByTracks($request)['ids'];
    //это массив всех подзадач, подходящих под фильтр
    $taskIdByFilter = self::getAllIdsTaskChildByFilter($request)['ids'] ?? [0];
    $taskIdsByFilterAndAllParent = [];
    $activeTask = [];
    foreach ($taskIdByFilter as $taskId) {
      if (!in_array($taskId, $taskIdsByFilterAndAllParent)) {
        $parents = self::getAllParentsByTask(['taskId' => $taskId])['taskIds'];
        if (in_array($parents[count($parents) - 1], $taskIdsParentsByFilter)) {
          $taskIdsByFilterAndAllParent = array_merge($taskIdsByFilterAndAllParent, $parents);
        }
      }
    }
    //это массив всех задач по фильтру и их родителей, с учетом, что на среднее звено(родители данной задачи,
    // являющиеся чьей-то подзадачей) фильтр не работает,
    // а у верхнего родителя проверяется трек и проект(без остального фильтра!)
    $taskIdsByFilterAndAllParent = array_unique($taskIdsByFilterAndAllParent);
    //это массив всех родителей (с треками) с учетом фильтрации - которые будут показаны!
    $parentTaskValidFromFilter = array_intersect($taskIdsParentsByFilter, $taskIdsByFilterAndAllParent);
    $taskIdByFilter = array_intersect($taskIdByFilter, $taskIdsByFilterAndAllParent);
    $taskIdsByFilterAndAllParent[] = 0;//TODO чтобы избежать лишнего кода, иначе на пустой результат запрос выведет без фильтра

    return [
      'parentTaskValidFromFilter' => $parentTaskValidFromFilter,
      'taskIdsByFilterAndAllParent' => $taskIdsByFilterAndAllParent,
      'activeTaskIds' => $taskIdByFilter,
    ];
  }

  /**
   * this function return three list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   * $result = [
   *    'list' => array three tasks
   *    'error' => array errors
   *  ]
   */
  public static function getThreeByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      if (!empty($request['search'])) {
        $request['search'] = [
          'type' => 'task',
          'text' => $request['search']
        ];
      }
      unset($request['filter']['taskIds']);
      $ids = self::getTaskIdsFromFilters($request);
      $result['list'] = [];
      foreach ($ids['parentTaskValidFromFilter'] as $taskId) {
        if (in_array($taskId, $ids['taskIdsByFilterAndAllParent'])) {
          $result['list'][] = self::getTaskInfoByFilter(['taskId' => $taskId, 'taskIds' => $ids['taskIdsByFilterAndAllParent'], 'activeIds' => $ids['activeTaskIds']]);
        }
      }
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return three list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function getStatusByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      unset($request['filter']['statuses']);
      $ids = self::getTaskIdsFromFilters($request);
      if (!empty($ids['activeTaskIds'])) {
        $res = \CTasks::GetList(
          ['DEADLINE' => 'ASC'],
          [
            'ID' => $ids['activeTaskIds'],
          ],
          ['STATUS', 'ID'],
        );
        $buf = [];
        while ($arTask = $res->GetNext()) {
          if (!empty($arTask['STATUS'])) {
            $frontName = ListProject::frontStatus[floatval($arTask['STATUS'])];
            if (empty($buf[Task::status[intval($arTask['STATUS'])]])) {
              $buf[Task::status[intval($arTask['STATUS'])]] = [
                'id' => floatval($arTask['STATUS']),
                'ids' => [floatval($arTask['STATUS'])],
                'name' => $frontName,
                'title' => Task::status[intval($arTask['STATUS'])],
              ];
            }
          }
        }
      }
      $result['list'] = array_values($buf ?? []);
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return three list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function getDeadlineByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      unset($request['filter']['deadline']);
      $ids = self::getTaskIdsFromFilters($request);
      $filter = [
        'ID' => $ids['activeTaskIds'],
      ];
      $result['deadline'] = [
        'items' => [],
        'min' => '',
        'max' => '',
      ];
      if (empty($ids['activeTaskIds'])) {
        http_response_code(200);
        die(json_encode($result, JSON_UNESCAPED_UNICODE));
      }
      $res = \CTasks::GetList(
        ['DEADLINE' => 'ASC'],
        $filter,
        ['DEADLINE'],
        [],
        ['DEADLINE']
      );
      while ($arTask = $res->GetNext()) {
        if (!empty($arTask['DEADLINE'])) {
          $result['deadline']['items'][] = date('d.m.Y', strtotime($arTask['DEADLINE']));
        }
      }

      $result['deadline']['min'] = $result['deadline']['items'][0];
      $result['deadline']['max'] = $result['deadline']['items'][count($result['deadline']['items']) - 1];
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return responsible list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function getResponsibleByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      if (!empty($request['search'])) {
        $dbUser = \Bitrix\Main\UserTable::getList([
          'select' => ['ID'],
          'filter' => [
            "ACTIVE" => "Y",
            [
              'LOGIC' => 'OR',
              ['NAME' => "%{$request['search']}%"],
              ['LAST_NAME' => "%{$request['search']}%"],
              ['SECOND_NAME' => "%{$request['search']}%"],
            ]
          ],
        ]);
        $userIds = [];
        while ($arUser = $dbUser->fetch()) {
          $userIds[] = intval($arUser['ID']);
        }
        if (empty($userIds)) {
          http_response_code(200);
          die(json_encode(['list' => []], JSON_UNESCAPED_UNICODE));
        }
        $request['search'] = [
          'type' => 'responsible',
          'text' => $request['search']
        ];
      }

      unset($request['filter']['responsible']);
      $ids = self::getTaskIdsFromFilters($request);
      if (!empty($ids['activeTaskIds'])) {
        $filter = [
          'ID' => $ids['activeTaskIds'],
          'RESPONSIBLE_ID' => $userIds ?? [],
        ];
        $result['list'] = [];
        $res = \CTasks::GetList(
          [],
          $filter,
          ['RESPONSIBLE_ID'],
          [],
          ['RESPONSIBLE_ID']
        );
        $nameList = [];
        $buf = [];
        while ($arTask = $res->GetNext()) {
          if (count($buf['responsible']) < 10) {
            $nameUser = User::getFullName($arTask['RESPONSIBLE_ID']);
            $buf['responsible'][$nameUser] = [
              'id' => intval($arTask['RESPONSIBLE_ID']),
              'name' => $nameUser,
            ];
            $nameList[] = $nameUser;
          }
        }
      }
      $nameList = array_unique($nameList);
      sort($nameList);
      $result['list'] = [];
      foreach ($nameList as $name) {
        if (!empty($buf['responsible'][$name])) {
          $result['list'][] = $buf['responsible'][$name];
        }
      }
      $result['list'] = array_values($result['list']);
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return escalation list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function getEscalationByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      $result['list'] = [];
      unset($request['filter']['escalation']);
      $escalationCode = Task::getEscalationPropertyCode();
      $ids = self::getTaskIdsFromFilters($request);
      if (!empty($ids['activeTaskIds'])) {
        $res = \CTasks::GetList(
          [],
          [
            'ID' => $ids['activeTaskIds'],
          ],
          [$escalationCode],
          [],
          [$escalationCode]
        );
        while ($arTask = $res->GetNext()) {
          $name = 'Да';
          if (empty($arTask[$escalationCode])) {
            $name = 'Нет';
          }
          $buf[intval(!empty($arTask[$escalationCode]))] = [
            'id' => intval(!empty($arTask[$escalationCode])),
            'name' => $name,
          ];
        }
      }
      $result['list'] = array_values($buf) ?? [];
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return escalation list by filter(external and internal)
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function getAllEscalationByTaskFilter($request)
  {
    try {
      if (empty($request['projectId']) || !isset($request['trackId'])) {
        http_response_code(400);
        die(json_encode(['error' => ['Не заданы обязательные параметры трек или проект']], JSON_UNESCAPED_UNICODE));
      }
      $result['list'] = [];
      unset($request['filter']['escalation']);
      $ids = self::getTaskIdsFromFilters($request);
      if (!empty($ids['activeTaskIds'])) {
        $res = \CTasks::GetList(
          [],
          [
            'ID' => $ids['activeTaskIds'],
            Escalation::internalCode => true,
          ],
          [Escalation::internalCode],
          [
            'NAV_PARAMS' =>
            [
              'nTopCount' => 1,
            ]
          ],
        );
        if ($arTask = $res->GetNext()) {
          $result['list'][] = [
            'id' => 'internalCode',
            'name' => 'Да(внутренняя)',
          ];
        }
        $res = \CTasks::GetList(
          [],
          [
            'ID' => $ids['activeTaskIds'],
            Escalation::externalCode => true,
          ],
          [Escalation::externalCode],
          [
            'NAV_PARAMS' =>
              [
                'nTopCount' => 1,
              ]
          ],
        );
        if ($arTask = $res->GetNext()) {
          $result['list'][] = [
            'id' => 'externalCode',
            'name' => 'Да(внешняя)',
          ];
        }
        $res = \CTasks::GetList(
          [],
          [
            'ID' => $ids['activeTaskIds'],
            Escalation::externalCode => false,
            Escalation::internalCode => false,
          ],
          [Escalation::externalCode],
          [
            'NAV_PARAMS' =>
              [
                'nTopCount' => 1,
              ]
          ],
        );
        if ($arTask = $res->GetNext()) {
          $result['list'][] = [
            'id' => 'none',
            'name' => 'Нет',
          ];
        }
      }
      http_response_code(200);
      die(json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
      http_response_code(400);
      die(json_encode(['error' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * this function return last date (deadline or plane date finish) for project
   * @param int $projectId id by project
   * @return array date or error
   * $result = [
   *  'date' => date last
   *  'error' => array errors
   * ]
   */
  private static function getLastDateInProject($projectId)
  {
    try {
      $result['date'] = [];
      $res = \CTasks::GetList(
        ['DEADLINE' => 'DESC'],
        [
          'GROUP_ID' => $projectId,
        ],
        ['ID', 'DEADLINE'],
        ['NAV_PARAMS' =>
          [
            'nTopCount' => 1,
          ]
        ],
      );
      while ($arTask = $res->GetNext()) {
        $result['date'] = $arTask['DEADLINE'];
      }

      $res = \CTasks::GetList(
        ['END_DATE_PLAN' => 'DESC'],
        [
          'GROUP_ID' => $projectId,
        ],
        ['ID', 'END_DATE_PLAN'],
        ['NAV_PARAMS' =>
          [
            'nTopCount' => 1,
          ]
        ]
      );
      if ($arTask = $res->GetNext()) {
        if (strtotime($arTask['END_DATE_PLAN']) > strtotime($result['date'])) {
          $result['date'] = $arTask['END_DATE_PLAN'];
        }
      }
      $result['date'] = date('d.m.Y', strtotime($result['date']));
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];

    }
  }

  /**
   * this function return tasks with change op deadlines
   * @param array $request params for risk
   * $request = [
   *  'projectId' => int id by project.required
   *  'page' => int page by list
   * ]
   * @return array $result list with pager
   * $result = [
   *  'items' => array by tasks
   *  'pager' = array by pager
   * ]
   */
  private static function getListTaskWithChangeDeadlineOP($request)
  {
    try {
      $result['items'] = [];
      $res = \CTasks::GetList(
        ['DATE_CREATE' => 'ASC'],
        [
          'GROUP_ID' => $request['projectId'],
          '!UF_CHANGE_DEADLINE' => [false, 0]
        ],
        ['ID', 'TITLE', 'UF_CHANGE_DEADLINE'],
        ['NAV_PARAMS' =>
          [
            'nPageSize' => self::pageSize,
            'iNumPage' => $request['page'] ?: 1,
          ]
        ]
      );
      $userId = User::getId();
      while ($arTask = $res->GetNext()) {
        $result['items'][] = [
          'name' => $arTask['TITLE'],
          'change' => intval($arTask['UF_CHANGE_DEADLINE']),
          'url' => "/company/personal/user/{$userId}/tasks/task/view/{$arTask['ID']}/",
        ];
      }
      $res = \CTasks::GetList(
        ['DATE_CREATE' => 'ASC'],
        [
          'GROUP_ID' => $request['projectId'],
          '!UF_CHANGE_DEADLINE' => [false, 0]
        ],
        ['ID'],
      );
      $count = $res->SelectedRowsCount();
      $result['pager'] = [
        'page' => intval($request['page']) ?: 1,
        'count' => intval($count),
        'pageCount' => ceil($count / self::pageSize),
      ];
      return $result;
    } catch (Exception $e) {
      return ['error' => [$e->getMessage()]];

    }
  }

  /**
   * this function return json for statistic table dashboard tags
   * @param array $request array params for table
   * $request = [
   *  'projectId' => int id by project
   * ]
   */
  public static function getStatisticTable($request)
  {
    if (empty($request['projectId'])) {
      http_response_code(400);
      die(json_encode(['error' => ['Невалидные параметры']], JSON_UNESCAPED_UNICODE));
    }
    $result[] = [
      'code' => 'DateProject',
      'title' => 'Статистика по проекту',
      'info' => [
        'items' => [
          [
            'name' => 'Дата завершения проекта',
            'value' => Task::getProjectDate($request['projectId'])['finish'],
          ],
          [
            'name' => 'Дата начала проекта',
            'value' => Task::getProjectDate($request['projectId'])['start'],
          ]
        ],
      ],
    ];
    $taskCnt = self::getProjectInfo($request['projectId'])['project'];
    $status = Task::status;
    foreach($status as $id => $name){
      $taskCnt['items'][] = [
        'name' => $name,
        'statusId' => $id,
        'value' => Task::listTakByStatus(['status' => $id, 'projectId' => $request['projectId']]),
      ];
    }
    $taskCnt['items'][] = [
      'name' => 'Общее количество задач по проекту',
      'value' => $taskCnt['total'],
      'bold' => true,
    ];
    $result[] = [
      'code' => 'StatisticProject',
      'title' => 'Статистика по задачам проекта',
      'info' => ['items' => $taskCnt['items']],
    ];
    $result[] = [
      'title' => 'Задача с внешней эскалацией',
      'code' => 'ExternalCode',
      'info' => Escalation::getEscalationListByFilter(array_merge($request ?? [], ['filter' => ['externalCode' => 'Y']])),
    ];
    $result[] = [
      'title' => 'Задача с внутренней эскалацией',
      'code' => 'InternalCode',
      'info' => Escalation::getEscalationListByFilter(array_merge($request ?? [], ['filter' => ['internalCode' => 'Y']])),
    ];
    $result[] = [
      'title' => 'Риски, по которым не было своевременной реакции',
      'code' => 'RiskHard',
      'info' => Risk::listRisksForStatistic($request),
    ];
    $result[] = [
      'title' => 'Текущий статус по проекту',
      'code' => 'StatusProject',
      'info' => ['items' =>
        [
          [
            'name' => 'Последний статус',
            'value' => Status::getStatusProjectForDate($request['projectId'])['text'],
          ]
        ]
      ],
    ];
    $result[] = [
      'title' => 'Задачи, у которых наступает срок исполнения в ОП',
      'code' => 'WeekDeadline',
      'info' => Task::taskInOp($request),
    ];
    $result[] = [
      'title' => 'Задачи, перенесенные с одного ОП на другой',
      'code' => 'ChangeDeadline',
      'info' => self::getListTaskWithChangeDeadlineOP($request),
    ];
    $result[] = [
      'title' => 'Задачи со статусом "выполнено в срок"',
      'code' => 'PerformedInTime',
      'info' => Task::performedOnTimeIdsWithPager($request),
    ];
    $result[] = [
      'title' => 'Задачи со статусом "без изменений (в работе)"',
      'code' => 'WorkingNotChange',
      'info' => Task::workingNotChange($request),
    ];
    $result[] = [
      'title' => 'Пользователи, у которых количество задач превышает норму в ОП',
      'code' => 'Users',
      'info' => Task::userWithTasksInOP($request)
    ];
    http_response_code(200);
    die(json_encode($result, JSON_UNESCAPED_UNICODE));
  }

  /**
   * this function return escalation list by filter
   * @param array $request array param for filter
   * $request = [
   *   'projectId' => int id by project
   *   'trackId' => int by track
   *   'search' => string text
   *    'filter' => [
   *      'responsible' => array ids by users
   *      'taskIds' => array ids search by three
   *      'statuses' => array ids by statuses
   *      'escalation' => boolean
   *      'deadline' =>
   *        [
   *          'begin' => date min deadline
   *          'end' => date max deadline
   *        ]
   *    ]
   * ];
   * @return json $result ids by task
   */
  public static function routingFilter($request)
  {
    switch ($request['type']) {
      case 'task':
        TrackDashboard::getThreeByTaskFilter($request);
        break;
      case 'responsible':
        TrackDashboard::getResponsibleByTaskFilter($request);
        break;
      case 'deadline':
        TrackDashboard::getDeadlineByTaskFilter($request);
        break;
      case 'status':
        TrackDashboard::getStatusByTaskFilter($request);
        break;
      case 'escalation':
        TrackDashboard::getAllEscalationByTaskFilter($request);
        break;
      default:
        http_response_code(400);
        die(json_encode(['error' => ['Невалидные параметры']], JSON_UNESCAPED_UNICODE));
    }
  }

  public static function routingStatisticPager($request)
  {
    switch ($request['type']) {
      case 'RiskHard':
        Utils::viewJson(Risk::listRisksForStatistic($request));
        break;
      case 'ExternalCode':
        Utils::viewJson(Escalation::getEscalationListByFilter(array_merge($request ?? [], ['filter' => ['externalCode' => 'Y']])));
        break;
      case 'InternalCode':
        Utils::viewJson(Escalation::getEscalationListByFilter(array_merge($request ?? [], ['filter' => ['internalCode' => 'Y']])));
        break;
      case 'ChangeDeadline':
        Utils::viewJson(self::getListTaskWithChangeDeadlineOP($request));
        break;
      case 'WeekDeadline':
        Utils::viewJson(Task::taskInOp($request));
        break;
      case 'PerformedInTime':
        Utils::viewJson(Task::performedOnTimeIdsWithPager($request));
        break;
      case 'StatisticProject':
        Utils::viewJson(Task::listTakByStatus(array_merge($request, ['status' => $request['statusId']])));
        break;
      case 'WorkingNotChange':
        Utils::viewJson(Task::workingNotChange($request));
        break;
      case 'Users':
        Utils::viewJson(Task::userWithTasksInOP($request));
        break;
      default:
        http_response_code(400);
        die(json_encode(['error' => ['Невалидные параметры']], JSON_UNESCAPED_UNICODE));
    }
  }
}