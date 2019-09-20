<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Model\Dao\Page;
use Model\Dao\Story;
use Model\Dao\Selection;

// 編集ページ(Get Request)
$app->get('/story/{story_id}/{page_id}/edit', function (Request $request, Response $response, array $args) {

    $data = [
        "user" => [
            "name" => $_SESSION['user_info']['name'],
        ],
        "page_id" => $args["page_id"],
    ];

    //GETされた内容を取得します。

    $selection = new Selection($this->db);
    $selection_result = $selection->getSelectionByStoryId($args["story_id"]);

    $selection_list = $selection->getSelectionByStoryIdAndPageId($args["story_id"],$args["page_id"]);

    $data['selection_list'] = $selection_list;

    $selection_data = [];
    foreach($selection_result as $value){
        $row = ["from" => $value['page_id'], "to" => $value['ahead'], "text" => $value['content']];
        array_push($selection_data, $row);
    }

    $page = new Page($this->db);
    $page_data = $page->getPageByStoryId($args["story_id"]);
    $master = [];
    foreach ($page_data as $value) {
        $row = [ "key" => $value['page_id'] , "text" => $value['page_id'].":".$value['title']];
        array_push($master,$row);
    }

    $data['selection'] = json_encode($selection_data);
    $data['master'] = json_encode($master);

    $data['input'] = $page->getPageByStoryAndPage($args["story_id"],$args["page_id"]);

    // Render index view
    return $this->view->render($response, 'story/edit.twig', $data);

});

// 編集ページ(Post Request)
$app->post('/story/{story_id}/{page_id}/edit', function (Request $request, Response $response, array $args) {

    //POSTされた内容を取得します
    $data = $request->getParsedBody();

	//DB操作に用いるインスタンスの取得
    $page = new Page($this->db);
    $selection = new Selection($this->db);
    $story = new Story($this->db);

	//現在のDBの状況を取得
	$currentStory=$story->select(
		array("id"=>$args["story_id"]),"","",1,false
	);
    $current_page = $page->select([
        "story_id" => $args["story_id"],
        "page_id" => $args["page_id"],
    ]);

	//ページテーブル用のデータ作成
    $param = [
        "story_id" => $args["story_id"],
        "page_id" => $args["page_id"],
        "picture" => $data["picture"],
        "content" => $data["content"]
    ];

	//ページの作成・更新
    if ($current_page === false) {	# 新規作成
        $page->insert($param);
    } else {	#更新
        $page->update($param, ["story_id", "page_id"]);
    }

	//既存のselectionのdelete
    $delete_selections = $selection->select([
        "story_id" => $args["story_id"],
        "page_id" => $args["page_id"]
    ], "", "", 5, true);
    foreach ($delete_selections as $select) {
        $selection->delete(intval($select["id"]));
    };

	//selectionのinsert
    $count = 1;
    while(true) {
        $key = "selection" . strval($count);
        if (isset($data[$key])) {
            $param = [
                "story_id" => $args["story_id"],
                "page_id" => $args["page_id"],
                "content" => $data[$key],
                "ahead" => intval($data[$key . "ahead"])
            ];

            $selection->insert($param);
            $count += 1;
        } else {
            break;
        };
    }

	//必要な場合はDB上のnext_idを編集
	if (isset($currentStory["next_id"])){
		if((int)$args["page_id"]===(int)$currentStory["next_id"]){
			$story->update(array(
					"id"=>$args["story_id"],
					"next_id"=>($args["page_id"]+1)
				)
			);
		}
	}
    return $response->withRedirect('/story/' . $args["story_id"] . '/' . ($args["page_id"]+1) . '/edit');
});
