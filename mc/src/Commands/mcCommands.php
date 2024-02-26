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
  private function saveResume($process = null) {
    if ($process) {
      $this->state->set('mc.resumeProcess', $process);
    } else {
      $this->state->delete('mc.resumeProcess');
    }
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
        // レコードから必要なフィールド（body_value, entity_id, bundle）を取得
        $bodyValue = $record->body_value;
        $entityId = $record->entity_id;
        $bundle = $record->bundle;
        //　文字列置換ルール2が終わっている場合の処理
        if (strpos($bodyValue, 'https://WWW.DRUPAL.ORG') !== false) {
          continue;
        }
        //　文字列置換ルール1
        if (strpos($bodyValue, 'delicious') !== false) {
            $this->output()->writeln("Found 'delicious' in node {$entityId}, replacing with 'yummy'.");
            $bodyValue = str_replace('delicious', 'yummy', $bodyValue);
        }
        //　文字列置換ルール2
        if (strpos($bodyValue, 'https://www.drupal.org') !== false) {
            $this->output()->writeln("Found 'https://www.drupal.org' in node {$entityId}, replacing with 'https://WWW.DRUPAL.ORG'.");
            $bodyValue = str_replace('https://www.drupal.org', 'https://WWW.DRUPAL.ORG', $bodyValue);
        }
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
        $this->updateNodeBodyBulk($con, $updateBatch);
        $this->output()->writeln("Bulk update complete.");
      }
  }
}

  //　編集ルール2に従ってSELECTして本文を編集。
  private function processNo2($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'type', 'title']);
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
        //　文字列置換ルール3
        $updatedTitle = str_replace('Umami', 'this site', $title);
        $this->output()->writeln("Found 'Umami' in node {$vid} title, replacing with 'this site'.");
        // バルクアップデート用の配列に追加
        $updateBatch[] = [
            'vid' => $vid,
            'title' => $updatedTitle,
        ];
      }
      // バルクアップデート実行
      $this->updateNodeTitleBulk($con, $updateBatch);
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
        $search = 'minutes';
        $replacement = 'mins';
        // str_replace() を使用して文字列の置換を行う
        $updated_value = str_replace($search, $replacement, $field_recipe_instruction_value);
        // バルクアップデート用の配列に追加
        $updateBatch[] = ['revision_id' => $revision_id, 'field_recipe_instruction_value' => $updated_value];
        $this->output()->writeln("Found 'minutes' in node revision {$revision_id} recipe instruction, replacing with 'mins'.");
      }
      // バルクアップデートを実行
      $this->updateNodeFieldRecipeInstructionBulk($con, $updateBatch);
      $this->output()->writeln("Bulk update completed.");
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
            $updatedTitle = str_replace('delicious', 'yummy', $title);
            $this->output()->writeln("Found 'delicious' in node {$vid} title, replacing with 'yummy'.");
            // バルクアップデート用の配列に追加
            $updateBatch[] = ['vid' => $vid, 'title' => $updatedTitle];
        }
        // バルクアップデートを実行
        $this->updateNodeTitleBulk($con, $updateBatch);
        $this->output()->writeln("Bulk update completed.");
    }
  }

  // node__bodyバルクアップデート用のメソッド
  private function updateNodeBodyBulk($con, $updateBatch) {
    // ログを出力: Node Body をバルクアップデートする処理を開始
    $this->output()->writeln("Bulk updating Node Body...");
    // バルクアップデート用の配列を準備
    $cases = [];
    $params = [];
    foreach ($updateBatch as $index => $data) {
        $entityId = $data['entity_id'];
        $bodyValue = $data['body_value'];
        // :entity_id_0 :entity_id_1 ユニークキーを生成
        $keyEntity = ':entity_id_' . $index;
        // :body_value_0 :body_value_1 ユニークキーを生成
        $keyBody = ':body_value_' . $index;
        // $cases配列と$params配列に追加.
        // $cases配列にはエンティティIDのプレースホルダをキーとし、それに対応する本文のプレースホルダを値として格納
        $cases[$keyEntity] = $keyBody;
        // $params配列にはプレースホルダと実際の値のペアが格納
        $params[$keyEntity] = $entityId;
        $params[$keyBody] = $bodyValue;
    }
    // バルクアップデート用のクエリを構築
    // テーブルにnode__bodyを指定してentity_idごとに値をセット
    $query = "UPDATE {node__body} SET body_value = (CASE entity_id ";
    // queryを構築:entity_id_0には:body_value_0を:entity_id_1には:body_value_1
    foreach ($cases as $entityKey => $bodyKey) {
        $query .= "WHEN $entityKey THEN $bodyKey ";
    }
    // プレースホルダの部分を作るarray_keys($params)は$paramsのキーをすべて取得、
    // implode(',',はコンマ区切りで連結した文字列を返す。
    $query .= "END) WHERE entity_id IN (" . implode(',', array_keys($params)) . ")";
    // データベースを更新する
    // クエリが実行されるときにプレースホルダに対応した値がバインドされる。
    $con->query($query, $params);
    // ログを出力: Node Body をバルクアップデートする処理を終了
    $this->output()->writeln("Bulk update completed.");
  }

  // titleバルクアップデート用のメソッド
  private function updateNodeTitleBulk($con, $updateBatch) {
    // ログを出力: Node Title をバルクアップデートする処理を開始
    $this->output()->writeln("Bulk updating Node Title...");    
    // バルクアップデート用の配列を作成
    $caseStatement = [];
    $params = [];
    // vidを配列に追加
    $vids = [];
    foreach ($updateBatch as $index => $data) {
        $vid = $data['vid'];
        $title = $data['title'];
        $vids[] = $vid;
        $caseStatement[] = "WHEN :vid_$index THEN :title_$index";
        $params[":vid_$index"] = $vid;
        $params[":title_$index"] = $title;
    }
    // バルクアップデート用のクエリを実行
    $query = "UPDATE {node_field_data} SET title = (CASE vid " . implode(' ', $caseStatement) . " END) WHERE vid IN (" . implode(',', $vids) . ")";
    $con->query($query, $params);
    // node_field_revisionの方も更新
    $query = "UPDATE {node_field_revision} SET title = (CASE vid " . implode(' ', $caseStatement) . " END) WHERE vid IN (" . implode(',', $vids) . ")";
    $con->query($query, $params);
    // ログを出力: Node Title をバルクアップデートする処理を終了
    $this->output()->writeln("Bulk update completed.");
  }

  // recipe instruction バルクアップデート用のメソッド
  private function updateNodeFieldRecipeInstructionBulk($con, $updateBatch) {
    // ログを出力: Node Field Recipe Instruction をバルクアップデートする処理を開始
    $this->output()->writeln("Bulk updating Node Field Recipe Instruction...");    
    // バルクアップデート用の配列を作成
    $caseStatement = [];
    $params = [];
    $revisionIds = []; // revision_idの配列を初期化
    foreach ($updateBatch as $index => $data) {
      $revision_id = $data['revision_id'];
      $field_recipe_instruction_value = $data['field_recipe_instruction_value'];
      // revision_idがすでに配列に含まれていないことを確認する
      if (!in_array($revision_id, $revisionIds)) {
        $caseStatement[] = "WHEN :revision_id_$index THEN :field_recipe_instruction_value_$index";
        $params[":revision_id_$index"] = $revision_id;
        $params[":field_recipe_instruction_value_$index"] = $field_recipe_instruction_value;
        // revision_idを配列に追加
        $revisionIds[] = $revision_id;
      }
    }
    // バルクアップデート用のクエリを実行（node__field_recipe_instruction）
    $con->query("UPDATE {node__field_recipe_instruction} SET field_recipe_instruction_value = (CASE revision_id " . implode(' ', $caseStatement) . " END) WHERE revision_id IN (" . implode(',', $revisionIds) . ")", $params);
    // バルクアップデート用のクエリを実行（node_revision__field_recipe_instruction）
    $con->query("UPDATE {node_revision__field_recipe_instruction} SET field_recipe_instruction_value = (CASE revision_id " . implode(' ', $caseStatement) . " END) WHERE revision_id IN (" . implode(',', $revisionIds) . ")", $params);
    // ログを出力: Node Field Recipe Instruction をバルクアップデートする処理を終了
    $this->output()->writeln("Bulk update completed.");
  }

}