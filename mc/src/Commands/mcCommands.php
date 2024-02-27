<?php

namespace Drupal\mc\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;

class mcCommands extends DrushCommands {
  protected $database;
  private $state;
  private $resumeProcess;  

  public function __construct(Connection $database, StateInterface $state) {
    $this->database = $database;
    $this->state = $state;    
  }
  /**
   * 4つのコンテンツをルールに従い編集するコマンド
   * 編集ルール
   * No.   対象URL　　　　　　　　対象コンテンツタイプ　対象フィールド　    文字列置換ルール
   * 1    /*　　　　　　　　　　　基本ページ、記事　　　body               1,2
   * 2    /*　　　　　　　　　　　基本ページ           Title              3
   * 3    /recipes/*　　　　　　　Recipe            Recipe instruction  4
   * 4    /recipes/*を除く全て   すべて               Title             1
   * 
   * 文字列置換ルール
   * No.   変換前　                  変換後
   * 1     delicious                yummy
   * 2     https://www.drupal.org   https://WWW.DRUPAL.ORG
   * 3     Umami                    this site
   * 4     minutes                  mins
   * 
   * @command mc:dbcon
   * @aliases dbcon
   */  
  public function mc() {    
    try {
      // レジューム情報をチェック
      $this->checkResume();
      // データベース接続
      $con = $this->database;
      // 続きから処理を開始
      if ($this->resumeProcess === null) {
        // レジューム情報がない場合、編集ルールNo1から処理を開始
        $this->output()->writeln("Processing No 1...");
        $this->processNo1($con);
        $this->output()->writeln("No 1 processing complete.");
        // 編集ルールNo1が終わったらレジュームをNo2にしておく
        $this->saveResume("No2");
      } 
      // レジューム情報をチェック
      $this->checkResume();
      if ($this->resumeProcess === "No2") {
        //　継続するか聞く
        if (!$this->shouldContinue()) {
          return;
        }        
        // 編集ルールNo2の処理
        $this->output()->writeln("Processing No 2...");
        $this->processNo2($con);
        $this->output()->writeln("No 2 processing complete.");
        // 編集ルールNo2が終わったらレジュームをNo3にしておく
        $this->saveResume("No3");
      } 
      // レジューム情報をチェック
      $this->checkResume();
      if ($this->resumeProcess === "No3") {
        //　継続するか聞く
        if (!$this->shouldContinue()) {
          return;
        }        
        // 編集ルールNo3の処理
        $this->output()->writeln("Processing No 3...");
        $this->processNo3($con);
        $this->output()->writeln("No 3 processing complete.");
        // 編集ルールNo3が終わったらレジュームをNo4にしておく
        $this->saveResume("No4");
      } 
      // レジューム情報をチェック
      $this->checkResume();
      if ($this->resumeProcess === "No4") {
        //　継続するか聞く
        if (!$this->shouldContinue()) {
          return;
        }
        // 編集ルールNo4の処理
        $this->output()->writeln("Processing No 4...");
        $this->processNo4($con);
        $this->output()->writeln("No 4 processing complete.");
        // すべての処理が終わったらレジュームをクリアしておく
        $this->clearResume();
      }
    } catch (\Exception $e) {
      // エラーが発生したので、エラー書き出し
      $this->output()->writeln("An error occurred: {$e->getMessage()}");
    }
  }

  //  続きを実行するか確認する
  private function shouldContinue() {
    $answer = $this->io()->confirm("Continue to the next process?", False);
    return $answer;
  }

  // レジューム情報を保存
  private function saveResume($process) {
    $this->state->set('mc.resumeProcess', $process);
  }

  // レジューム情報をクリア
  private function clearResume() {
    $this->state->delete('mc.resumeProcess');
  }

  // レジューム情報をチェック
  private function checkResume() {
    $this->resumeProcess = $this->state->get('mc.resumeProcess');
  }

  //　編集ルール1に従ってSELECTして本文を編集。（条件を追加して高速化）
  private function processNo1($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__body', 'n');
    $query->fields('n', ['body_value', 'entity_id', 'bundle']);
    // 条件を追加 基本ページと記事のなかで
    $query->condition('bundle', ['page', 'article'], 'IN');
    //　deliciousもしくはhttps://www.drupal.orgが本文にあるものだけ取り出す
    $query->condition(
      $query->orConditionGroup()
            ->condition('body_value', '%https://www.drupal.org%', 'LIKE')
            ->condition('body_value', '%delicious%', 'LIKE')
    );
    $results = $query->execute()->fetchAll();
    if ($results === []) {
      // empty の場合の処理
      $this->output()->writeln("The result is empty.");
    } else {
      $updateBatch = [];
      foreach ($results as $record) {
        // レコードから必要なフィールド（body_value, entity_id）を取得
        $bodyValue = $record->body_value;
        $entityId = $record->entity_id;
        
        //　文字列置換ルール2が終わっている場合の処理
        if (strpos($bodyValue, 'https://WWW.DRUPAL.ORG') !== false) {
          continue;
        }
        // 文字列置換ルール1を適用
        $bodyValue = $this->replaceRule1($bodyValue, $entityId);
        // 文字列置換ルール2を適用
        $bodyValue = $this->replaceRule2($bodyValue, $entityId);
        // 更新用のデータを追加
        $updateBatch[] = [
            'entity_id' => $entityId,
            'body_value' => $bodyValue,
        ];
      }
      if ($updateBatch === []) {
        // empty の場合の処理
        $this->output()->writeln("The result is empty.");
      } else {
        // バルクアップデートを実行
        $this->bulkUpdate($con, 'node__body', 'entity_id' , 'body_value', $updateBatch);
      }
    }
  }

  //　編集ルール2に従ってSELECTして本文を編集。
  private function processNo2($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'title']);
    $query->condition('type', 'page');
    $query->condition('title', '%Umami%', 'LIKE');
    $results = $query->execute()->fetchAll();
    // バルクアップデート用の配列を初期化
    $updateBatch = [];
    if ($results === []) {
      // empty の場合の処理
      $this->output()->writeln("The result is empty.");
    } else {
      foreach ($results as $record) {
        // レコードから必要なフィールド（vid, title）を取得
        $vid = $record->vid;
        $title = $record->title;
        // 文字列置換ルール3を適用
        $updatedTitle = $this->replaceRule3($title, $vid);
        // バルクアップデート用の配列に追加
        $updateBatch[] = [
            'vid' => $vid,
            'title' => $updatedTitle,
        ];
      }
      // バルクアップデート実行
      $this->bulkUpdate($con, 'node_field_data', 'vid' , 'title', $updateBatch);
      $this->bulkUpdate($con, 'node_field_revision' , 'vid' , 'title', $updateBatch);
    }
  }

  //　編集ルール3に従ってSELECTして本文を編集。
  private function processNo3($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__field_recipe_instruction', 'n');
    $query->fields('n', ['revision_id', 'field_recipe_instruction_value']);
    // DB抽出条件に本文中にminutesがあることを追加
    $query->condition('field_recipe_instruction_value', '%minutes%', 'LIKE');
    $results = $query->execute()->fetchAll();
    if ($results === []) {
        // empty の場合の処理
        $this->output()->writeln("The result is empty.");
    } else {
      // バルクアップデート用の配列を初期化
      $updateBatch = [];
      foreach ($results as $record) {
        // レコードから必要なフィールド（revision_id, field_recipe_instruction_value）を取得
        $revision_id = $record->revision_id;
        $field_recipe_instruction_value = $record->field_recipe_instruction_value;
        //　文字列置換ルール4
        $updated_value = $this->replaceRule4($field_recipe_instruction_value, $revision_id);
        // バルクアップデート用の配列に追加
        $updateBatch[] = ['revision_id' => $revision_id, 'field_recipe_instruction_value' => $updated_value];
        $this->output()->writeln("Found 'minutes' in node revision {$revision_id} recipe instruction, replacing with 'mins'.");
      }
      // バルクアップデートを実行
      $this->bulkUpdate($con, 'node__field_recipe_instruction', 'revision_id' , 'field_recipe_instruction_value', $updateBatch);
      $this->bulkUpdate($con, 'node_revision__field_recipe_instruction', 'revision_id' , 'field_recipe_instruction_value', $updateBatch);
    }
  }

  //　編集ルール4に従ってSELECTしてタイトルを編集。
  private function processNo4($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'type', 'title']);
    $query->condition('type', 'recipe', '<>');
    $query->condition('title', '%delicious%', 'LIKE');
    $results = $query->execute()->fetchAll();
    if ($results === []) {
      // empty の場合の処理
      $this->output()->writeln("The result is empty.");
    } else {
      // バルクアップデート用の配列を初期化
      $updateBatch = [];
      foreach ($results as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $title = $record->title;
        //　文字列置換ルール1
        $updatedTitle = $this->replaceRule1($title, $vid);
        // バルクアップデート用の配列に追加
        $updateBatch[] = ['vid' => $vid, 'title' => $updatedTitle];
      }
      // バルクアップデートを実行
      $this->bulkUpdate($con, 'node_field_data' , 'vid' , 'title', $updateBatch);
      $this->bulkUpdate($con, 'node_field_revision' , 'vid' , 'title', $updateBatch);
    }
  }

  private function bulkUpdate($con, $tableName , $bId , $field, $updateBatch) {
    // ログを出力: バルクアップデート処理を開始
    $this->output()->writeln("Bulk updating $tableName $field...");
    // バルクアップデート用の配列を準備
    $caseStatement = [];
    $params = [];
    $ids = [];
    foreach ($updateBatch as $index => $data) {
      $id = $data[$bId];
      $value = $data[$field];
      if (!in_array($id, $ids)) {
        // :id_0 :value_0 ユニークキーを生成
        // $caseStatementにペアにしてクエリを追加していく
        $caseStatement[] = "WHEN :id_$index THEN :value_$index";
        // ここでバインド紐づけ
        $params[":id_$index"] = $id;
        $params[":value_$index"] = $value;
        $ids[] = $id;
      }
    }
    // バルクアップデート用のクエリを実行
    // プレースホルダの部分を作るarray_keys($params)は$paramsのキーをすべて取得、
    // implode(',',はコンマ区切りで連結した文字列を返す。
    $query = "UPDATE {{$tableName}} SET $field = (CASE $bId " . implode(' ', $caseStatement) . " END) WHERE $bId IN (" . implode(',', $ids) . ")";
    $con->query($query, $params);
    // ログを出力: バルクアップデート処理を終了
    $this->output()->writeln("Bulk update completed.");
  }

  // 文字列置換ルール１
  private function replaceRule1($bodyValue, $entityId) {
    if (strpos($bodyValue, 'delicious') !== false) {
      echo "Found 'delicious' in node {$entityId}, replacing with 'yummy'.\n";
      $bodyValue = str_replace('delicious', 'yummy', $bodyValue);
    }
    return $bodyValue;
  }

  // 文字列置換ルール２
  private function replaceRule2($bodyValue, $entityId) {
    if (strpos($bodyValue, 'https://www.drupal.org') !== false) {
      echo "Found 'https://www.drupal.org' in node {$entityId}, replacing with 'https://WWW.DRUPAL.ORG'.\n";
      $bodyValue = str_replace('https://www.drupal.org', 'https://WWW.DRUPAL.ORG', $bodyValue);
    }
    return $bodyValue;
  }

  // 文字列置換ルール３
  private function replaceRule3($bodyValue, $entityId) {
    if (strpos($bodyValue, 'Umami') !== false) {
      echo "Found 'Umami' in node {$entityId}, replacing with 'this site'.\n";
      $bodyValue = str_replace('Umami', 'this site', $bodyValue);
    }
    return $bodyValue;
  }

  // 文字列置換ルール４
  private function replaceRule4($bodyValue, $entityId) {
    if (strpos($bodyValue, 'minutes') !== false) {
      echo "Found 'minutes' in node {$entityId}, replacing with 'mins'.\n";
      $bodyValue = str_replace('minutes', 'mins', $bodyValue);
    }
    return $bodyValue;
  }

}