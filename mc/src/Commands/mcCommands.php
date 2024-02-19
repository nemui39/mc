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
    // 静的クエリーの発行
    $sql = 'SELECT bundle, body_value, entity_id FROM node__body';
    $query = $con->query($sql);
    $nodeBodyRecords = $query->fetchAll();

    foreach ($nodeBodyRecords as $record) {
        // レコードから必要なフィールド（body_value, entity_id, bundle）を取得
        $bodyValue = $record->body_value;
        $entityId = $record->entity_id;
        $bundle = $record->bundle;

        if ($bundle == 'page' || $bundle == 'article') {
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
  }

  private function processNo2($con) {
    // 静的クエリーの発行
    $sql = 'SELECT vid, type, title FROM node_field_data';
    $query = $con->query($sql);
    $nodeFieldDataRecords = $query->fetchAll();

    foreach ($nodeFieldDataRecords as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $type = $record->type;
        $title = $record->title;
        //Rule3
        if ($type == 'page' && strpos($title, 'Umami') !== false) {
            $this->updateNodeTitle($con, $vid, str_replace('Umami', 'this site', $title));
        }
    }
  }

  private function processNo3($con) {
    // 静的クエリーの発行
    $sql = 'SELECT revision_id, field_recipe_instruction_value FROM node__field_recipe_instruction';
    $query = $con->query($sql);
    $nodeFieldRecipeInstructionRecords = $query->fetchAll();

    foreach ($nodeFieldRecipeInstructionRecords as $record) {
        // レコードから必要なフィールド（revision_id, field_recipe_instruction_value）を取得
        $revision_id = $record->revision_id;
        $field_recipe_instruction_value = $record->field_recipe_instruction_value;
        //Rule4
        $search = 'minutes';
        if (strpos($field_recipe_instruction_value, $search) !== false) {
            $this->updatenodeFieldRecipeInstruction($con, $revision_id, str_replace($search, 'mins', $field_recipe_instruction_value));
        }
    }
  }

  private function processNo4($con) {
    // 静的クエリーの発行
    $sql = 'SELECT vid, type, title FROM node_field_data';
    $query = $con->query($sql);
    $nodeFieldDataRecords = $query->fetchAll();

    foreach ($nodeFieldDataRecords as $record) {
        // レコードから必要なフィールド（vid, type, title）を取得
        $vid = $record->vid;
        $type = $record->type;
        $title = $record->title;
        $search = 'delicious';

        if ($type !== 'recipe' && strpos($title, $search) !== false){
          //Rule4
          $this->updateNodeTitle($con, $vid, str_replace($search, 'yummy', $title));
        }
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