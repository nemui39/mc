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
    // データベース接続
    $con = \Drupal::database();

    // 静的クエリーの発行
    $sql = 'SELECT bundle, body_value, entity_id FROM node__body';
    $query = $con->query($sql);
    $result = $query->fetchAll();
    
    // 取得したレコードをアウトプットして置換してupdatedb
    foreach ($result as $record) {
      // bundleの表示
      //$this->output()->writeln(print_r($record->bundle, true));
      
      // レコードから必要なフィールド（body_value, vid, bundle）を取得
      $body_value = $record->body_value;
      $entity_id = $record->entity_id;
      $bundle = $record->bundle;

      //ないはずだが一応基本ページか記事の判定をする
      if ($bundle == 'page' || $bundle == 'article') {

        // No1文字列の検索ルール１
        $search = 'delicious';
        if (strpos($body_value, $search) !== false) {
          $this->output()->writeln('No1-------------ルール1-------------------');

          $this->output()->writeln(print_r($record, true));
          // 変換ライン
          $this->output()->writeln('----delicious-----変換後----yummy------');
          // No1文字列の置換ルール1
          $replace = 'yummy';
          $body_value = str_replace($search, $replace, $body_value);
          $this->output()->writeln(print_r($body_value, true));

          // データベースを更新する
          $con->update('node__body')
            ->fields(['body_value' => $body_value])
            ->condition('entity_id', $entity_id)
            ->execute();
        }     

        // No1文字列の検索ルール２
        $search = 'https://www.drupal.org';
        if (strpos($body_value, $search) !== false) {
          $this->output()->writeln('No1-------------ルール2-------------------');
          $this->output()->writeln(print_r($record, true));
          // 変換ライン
          $this->output()->writeln('---https://www.drupal.org-----変換後----https://WWW.DRUPAL.ORG------');
          // No1文字列の置換ルール2
          $replace = 'https://WWW.DRUPAL.ORG';
          $body_value = str_replace($search, $replace, $body_value);
          $this->output()->writeln(print_r($body_value, true));

          // データベースを更新する
          $con->update('node__body')
            ->fields(['body_value' => $body_value])
            ->condition('entity_id', $entity_id)
            ->execute();
        }  
      }
    }
    // No2開始
    // 静的クエリーの発行
    $sql = 'SELECT vid, type, title FROM node_field_data';
    $query = $con->query($sql);
    //　言語複数の場合基本ページも何個かあるのでfechAll使っておく、もしくは言語のカラムを取ってきて判定する。
    $result = $query->fetchAll();
    // 取得したレコードをアウトプットして置換してupdatedb
    foreach ($result as $record) {
      // レコードから必要なフィールド（vid, type, title）を取得
      $vid = $record->vid;
      $type = $record->type;
      $title = $record->title;
      //　基本ページかの判定をする
      if ($type == 'page') { 
        // No2文字列の検索ルール3
        $search = 'Umami';
        if (strpos($title, $search) !== false) {
          $this->output()->writeln('No2-------------ルール3-------------------');

          $this->output()->writeln(print_r($record, true));
          // 変換ライン
          $this->output()->writeln('----Umami-----変換後----this site------');
          // No2文字列の置換ルール3
          $replace = 'this site';
          $title = str_replace($search, $replace, $title);
          $this->output()->writeln(print_r($title, true));

          // データベースを更新する
          //node_field_revision テーブルには過去の全てのリビジョンのデータが格納される
          //node_field_data テーブルには最新のリビジョンのデータのみが格納される
          //node テーブルのvidで最新のリビジョンを判断する
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
      }
    }
    // No3始める
    // /recipes/* の minute を　mins　に置換する
    //　コンテンツタイプ→Recipe フィールド Recipe instruction
    // 文字列置換ルール４
    // 静的クエリーの発行  node_revision__field_recipe_instruction node__field_recipe_instruction'
    $sql = 'SELECT revision_id, field_recipe_instruction_value FROM node__field_recipe_instruction';
    $query = $con->query($sql);
    $result = $query->fetchAll();
    foreach ($result as $record) {
      // レコードから必要なフィールドを取得
      $revision_id = $record->revision_id;
      $field_recipe_instruction_value = $record->field_recipe_instruction_value;
      // No3文字列の検索ルール4
      $search = 'minutes';
      if (strpos($field_recipe_instruction_value, $search) !== false) {
        $this->output()->writeln('No3-------------ルール4-------------------');

        $this->output()->writeln(print_r($record, true));
        // 変換ライン
        $this->output()->writeln('----minutes-----変換後----mins------');
        // No3文字列の置換ルール4
        $replace = 'mins';
        $field_recipe_instruction_value = str_replace($search, $replace, $field_recipe_instruction_value);
        $this->output()->writeln(print_r($field_recipe_instruction_value, true));

        // データベースを更新する
        $con->update('node__field_recipe_instruction')
            ->fields(['field_recipe_instruction_value' => $field_recipe_instruction_value])
            ->condition('revision_id', $revision_id)
            ->execute();
        //node_revision__field_recipe_instructionの方も書き換える
        $con->update('node_revision__field_recipe_instruction')
            ->fields(['field_recipe_instruction_value' => $field_recipe_instruction_value])
            ->condition('revision_id', $revision_id)
            ->execute(); 
      }
    }
    // No4を始める
    // /recipes/*を除く全ての対象フィールドTitle
    // deliciousをyummyに変える
    // 静的クエリーの発行
    $sql = 'SELECT vid, type, title FROM node_field_data';
    $query = $con->query($sql);
    //　言語複数の場合基本ページも何個かあるのでfechAll使っておく
    $result = $query->fetchAll();
    // 取得したレコードをアウトプットして置換してupdatedb
    foreach ($result as $record) {
      // レコードから必要なフィールド（vid, type, title）を取得
      $vid = $record->vid;
      $type = $record->type;
      $title = $record->title;
      // recipes以外の判定をする
      //$this->output()->writeln(print_r($type, true));//確認用
      if ($type !== 'recipe') { 
        // No4文字列の検索ルール1
        $search = 'delicious';
        if (strpos($title, $search) !== false) {
          $this->output()->writeln('No4-------------ルール1-------------------');

          $this->output()->writeln(print_r($record, true));
          // 変換ライン
          $this->output()->writeln('----delicious-----変換後----yummy------');
          // No4文字列の置換ルール1
          $replace = 'yummy';
          $title = str_replace($search, $replace, $title);
          $this->output()->writeln(print_r($title, true));

          // データベースを更新する
          //node_field_revision テーブルには過去の全てのリビジョンのデータが格納される
          //node_field_data テーブルには最新のリビジョンのデータのみが格納される
          //node テーブルのvidで最新のリビジョンを判断する
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
      }
    }
  }
}