<?php

namespace Drupal\mc\Commands;


use Drush\Commands\DrushCommands;

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
      $this->processNo1($con);

      // No2の処理
      $this->processNo2($con);

      // No3の処理
      $this->processNo3($con);

      // No4の処理
      $this->processNo4($con);

    } catch (\Exception $e) {
        // 例外が発生した場合の処理
        $this->output()->writeln("An error occurred: {$e->getMessage()}");
    }
  }


  private function processNo1($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__body', 'n');
    $query->fields('n', ['body_value', 'entity_id', 'bundle']);
    // 条件を追加
    $query->condition('bundle', ['page', 'article'], 'IN');
    $results = $query->execute()->fetchAll();

    foreach ($results as $record) {
        // レコードから必要なフィールド（body_value, entity_id, bundle）を取得
        $bodyValue = $record->body_value;
        $entityId = $record->entity_id;
        $bundle = $record->bundle;

        //Rule1
        $search = 'delicious';
        if (strpos($bodyValue, $search) !== false) {
            $this->updateNodeBody($con, $entityId, str_replace($search, 'yummy', $bodyValue));
        }
        //Rule2
        $search = 'https://www.drupal.org';
        if (strpos($bodyValue, $search) !== false) {
            $this->updateNodeBody($con, $entityId, str_replace($search, 'https://WWW.DRUPAL.ORG', $bodyValue));
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

    foreach ($results as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $title = $record->title;

        //Rule3
        $updatedTitle = str_replace('Umami', 'this site', $title);
        $this->updateNodeTitle($con, $vid, $updatedTitle);
    }
  }

  private function processNo3($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node__field_recipe_instruction', 'n');
    $query->fields('n', ['revision_id', 'field_recipe_instruction_value']);
    // 条件を追加
    $query->condition('field_recipe_instruction_value', '%minutes%', 'LIKE');
    $results = $query->execute()->fetchAll();

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
        $this->updateNodeFieldRecipeInstruction($con, $revision_id, $updated_value);
    }
  }

  private function processNo4($con) {
    // プレースホルダを使ってクエリを構築
    $query = $con->select('node_field_data', 'n');
    $query->fields('n', ['vid', 'type', 'title']);
    $query->condition('type', 'recipe', '<>');
    $query->condition('title', '%delicious%', 'LIKE');
    $results = $query->execute()->fetchAll();

    foreach ($results as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $title = $record->title;

        //Rule4
        $updatedTitle = str_replace('delicious', 'yummy', $title);
        $this->updateNodeTitle($con, $vid, $updatedTitle);
    }
  }
  
  private function updateNodeBody($con, $entityId, $bodyValue) {
    // データベースを更新する
    $con->update('node__body')
        ->fields(['body_value' => $bodyValue])
        ->condition('entity_id', $entityId)
        ->execute();
  }

  private function updateNodeTitle($con, $vid, $title) {
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
  }

  private function updatenodeFieldRecipeInstruction($con, $revision_id, $field_recipe_instruction_value) {
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
  }
}