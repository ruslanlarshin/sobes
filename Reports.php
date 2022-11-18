<?php

namespace NotaMedia\GosTech;

use NotaMedia\GosTech\{User, TimeTask, ListProject, SocNetGroup};

\CModule::IncludeModule('socialnetwork');
\CModule::IncludeModule('tasks');

class Reports
{
  /**
   * this function return info for weekly report
   * @param array $projectIds ids by project
   * @param date $date date for report
   * @return array $result info about projects
   * $result = [
   *  'date' => now date
   *  'items' => []
   *  [
   *    'name' => name project
   *    'statusProject' => status project
   *  ]
   * ]
   */
  public static function weeklyReport($projectIds, $date)
  {
    try {
      $result['date'] = $date;
      foreach ($projectIds as $projectId) {
        if (!empty($projectId)) {
          $result['items'][] = [
            'name' => Task::getProjectName($projectId),
            'statusProject' => Status::getStatusProjectForDate($projectId, $date)['text'] ?? 'Нет статуса для этого проекта по выбранной дате',
          ];
        }
      }
      return $result;
    } catch (Exception $e) {
      $result['error'][] = $e->getMessage();
    }
  }

  /**
   * this function make docx file for report
   * @param array $projectIds ids by project
   * @param date $date date for report
   * @param string $format doc | docx | odt
   * @return array error or url
   */
  public static function generateWeeklyWord($projectIds, $date, $format = 'docx')
  {

    try {
      if(empty($projectIds) || empty($date) || empty($format)){
        return ['error' => ['Невалидные данные']];
      }
      $data = self::weeklyReport($projectIds, $date);
      if (!empty($data['items'])) {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $properties = $phpWord->getDocInfo();
        $properties->setTitle('Еженедельный отчет');
        $section = $phpWord->addSection();
        $section->addText(
          htmlspecialchars('Справка на ' . $data['date']),
          ['name' => 'Arial', 'size' => 14, 'color' => 'black', 'bold' => true, 'italic' => false],
          ['align' => 'center', 'spaceBefore' => 10]
        );
        foreach ($data['items'] as $item) {
          $section->addText(
            htmlspecialchars($item['name']),
            ['name' => 'Arial', 'size' => 12, 'color' => 'black', 'bold' => true, 'italic' => false],
            ['align' => 'left', 'spaceBefore' => 10]
          );
          $section->addText(
            htmlspecialchars($item['statusProject']),
            ['name' => 'Arial', 'size' => 12, 'color' => 'grey', 'bold' => false, 'italic' => true],
            ['align' => 'left', 'spaceBefore' => 10]
          );
          $section->addText('');
        }
        //$date = date('d.m.Y-h:m:s', time());TODO изменения по чтз временные?
        switch ($format) {
          case 'doc':
            $url = '/reports/weekly' . $date . '.doc';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($_SERVER["DOCUMENT_ROOT"] . $url);
            return ['url' => $_SERVER["HTTP_HOST"] . $url];
          case 'docx':
            $url = '/reports/weekly' . $date . '.docx';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($_SERVER["DOCUMENT_ROOT"] . $url);
            return ['url' => $_SERVER["HTTP_HOST"] . $url];
          case 'odt':
            $url = '/reports/weekly' . $date . '.odt';
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'ODText');
            $objWriter->save($_SERVER["DOCUMENT_ROOT"] . $url);
            return ['url' => $_SERVER["HTTP_HOST"] . $url];
          default:
            return ['error' => 'Неверный формат файла'];
        }
      }
    } catch (Exception $e) {
      $result['error'][] = $e->getMessage();
    }
  }
}

?>