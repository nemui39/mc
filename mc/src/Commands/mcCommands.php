<?php

namespace Drupal\mc\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Database;

class mcCommands extends DrushCommands {
  /**
   * データベース接続するコマンド
   * 
   * @command mc:dbcon
   * @aliases dbcon
   */
  public function mc() {
    try {
      // データベース接続
      $con = \Drupal::database();

      // No1の処理
      $this->output()->writeln("Processing No 1...");
      $this->processNo1($con);
      $this->output()->writeln("No 1 processing complete.");

      //継続するか聞く
      if (!$this->shouldContinue()) {
        return;
      }

      // No2の処理
      $this->output()->writeln("Processing No 2...");
      $this->processNo2($con);
      $this->output()->writeln("No 2 processing complete.");

      //継続するか聞く
      if (!$this->shouldContinue()) {
        return;
      }

      // No3の処理
      $this->output()->writeln("Processing No 3...");
      $this->processNo3($con);
      $this->output()->writeln("No 3 processing complete.");
 
      //継続するか聞く
      if (!$this->shouldContinue()) {
        return;
      }
      
      // No4の処理
      $this->output()->writeln("Processing No 4...");
      $this->processNo4($con);
      $this->output()->writeln("No 4 processing complete.");
     
    } catch (\Exception $e) {
      $this->output()->writeln("An error occurred: {$e->getMessage()}");
      // エラーが発生したので、レジューム情報を保存
      $this->saveResume();
    }
  }

  private function shouldContinue() {
    $answer = $this->io()->confirm("Continue to the next process?", False);
    return $answer;
  }

  private function processNo1($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__body', 'n');
    $query->fields('n', ['body_value', 'entity_id', 'bundle']);
    // 条件を追加
    $query->condition('bundle', ['page', 'article'], 'IN');
    $results = $query->execute()->fetchAll();
    
    if ($results === null) {
      // null の場合の処理
      // 例: メッセージを出力してログに記録する
      $this->output()->writeln("The result is null.");
    } else {
      foreach ($results as $record) {
        // レコードから必要なフィールド（body_value, entity_id, bundle）を取得
        $bodyValue = $record->body_value;
        $entityId = $record->entity_id;
        $bundle = $record->bundle;

        //Rule1
        $search = 'delicious';
        if (strpos($bodyValue, $search) !== false) {
          $this->output()->writeln("Found 'delicious' in node {$entityId}, replacing with 'yummy'.");
          $this->updateNodeBody($con, $entityId, str_replace($search, 'yummy', $bodyValue));
          $this->output()->writeln("Node {$entityId} body updated.");
        }
        //Rule2
        $search = 'https://www.drupal.org';
        if (strpos($bodyValue, $search) !== false) {
          $this->output()->writeln("Found 'https://www.drupal.org' in node {$entityId}, replacing with 'https://WWW.DRUPAL.ORG'.");
          $this->updateNodeBody($con, $entityId, str_replace($search, 'https://WWW.DRUPAL.ORG', $bodyValue));
          $this->output()->writeln("Node {$entityId} body updated.");
        }
      }
    }
  }

  private function processNo2($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'type', 'title']);
    $query->condition('type', 'page');
    $query->condition('title', '%Umami%', 'LIKE');
    $results = $query->execute()->fetchAll();

    if ($results === null) {
      // null の場合の処理
      // 例: メッセージを出力してログに記録する
      $this->output()->writeln("The result is null.");
    } else {
      foreach ($results as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $title = $record->title;

        //Rule3
        $updatedTitle = str_replace('Umami', 'this site', $title);
        $this->output()->writeln("Found 'Umami' in node {$vid} title, replacing with 'this site'.");
        $this->updateNodeTitle($con, $vid, $updatedTitle);
        $this->output()->writeln("Node {$vid} title updated.");
      }
    }
  }

  private function processNo3($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__field_recipe_instruction', 'n');
    $query->fields('n', ['revision_id', 'field_recipe_instruction_value']);
    // 条件を追加
    $query->condition('field_recipe_instruction_value', '%minutes%', 'LIKE');
    $results = $query->execute()->fetchAll();

    if ($results === null) {
      // null の場合の処理
      // 例: メッセージを出力してログに記録する
      $this->output()->writeln("The result is null.");
    } else {
      foreach ($results as $record) {
        // レコードから必要なフィールド（revision_id, field_recipe_instruction_value）を取得
        $revision_id = $record->revision_id;
        $field_recipe_instruction_value = $record->field_recipe_instruction_value;
        //Rule4
        $search = 'minutes';
        $replacement = 'mins';
        // str_replace() を使用して文字列の置換を行う
        $updated_value = str_replace($search, $replacement, $field_recipe_instruction_value);

        // データベースを更新する
        $this->output()->writeln("Found 'minutes' in node revision {$revision_id} recipe instruction, replacing with 'mins'.");
        $this->updateNodeFieldRecipeInstruction($con, $revision_id, $updated_value);
        $this->output()->writeln("Node revision {$revision_id} recipe instruction updated.");
      }
    }
  }

  private function processNo4($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'type', 'title']);
    $query->condition('type', 'recipe', '<>');
    $query->condition('title', '%delicious%', 'LIKE');
    $results = $query->execute()->fetchAll();

    if ($results === null) {
      // null の場合の処理
      // 例: メッセージを出力してログに記録する
      $this->output()->writeln("The result is null.");
      } else {
      foreach ($results as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $title = $record->title;

        //Rule4
        $updatedTitle = str_replace('delicious', 'yummy', $title);
        $this->output()->writeln("Found 'delicious' in node {$vid} title, replacing with 'yummy'.");
        $this->updateNodeTitle($con, $vid, $updatedTitle);
        $this->output()->writeln("Node {$vid} title updated.");
      }
    }
  }
  
  private function updateNodeBody($con, $entityId, $bodyValue) {
    // ログを出力: Node Body を更新する処理を開始
    $this->output()->writeln("Updating Node Body...");
    // データベースを更新する
    $con->update('node__body')
        ->fields(['body_value' => $bodyValue])
        ->condition('entity_id', $entityId)
        ->execute();
    // ログを出力: Node Body を更新する処理を終了
    $this->output()->writeln("Node Body update completed.");  
  }

  private function updateNodeTitle($con, $vid, $title) {
    // ログを出力: Node Title を更新する処理を開始
    $this->output()->writeln("Updating Node Title...");
    // データベースを更新する
    $con->update('node_field_data')
        ->fields(['title' => $title])
        ->condition('vid', $vid)
        ->execute();
    //node_field_revisionの方も書き換える
    $con->update('node_field_revision')
        ->fields(['title' => $title])
        ->condition('vid', $vid)
        ->execute(); 
    // ログを出力: Node Title を更新する処理を終了
    $this->output()->writeln("Node Title update completed."); 
  }

  private function updateNodeFieldRecipeInstruction($con, $revision_id, $field_recipe_instruction_value) {
    // ログを出力: Node Field Recipe Instruction を更新する処理を開始
    $this->output()->writeln("Updating Node Field Recipe Instruction...");
    // データベースを更新する
    $con->update('node__field_recipe_instruction')
        ->fields(['field_recipe_instruction_value' => $field_recipe_instruction_value])
        ->condition('revision_id', $revision_id)
        ->execute();
    //node_field_revisionの方も書き換える
    $con->update('node_revision__field_recipe_instruction')
        ->fields(['field_recipe_instruction_value' => $field_recipe_instruction_value])
        ->condition('revision_id', $revision_id)
        ->execute(); 
    // ログを出力: Node Field Recipe Instruction を更新する処理を終了
    $this->output()->writeln("Node Field Recipe Instruction update completed.");
  }
}