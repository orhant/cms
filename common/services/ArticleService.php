<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2020-01-30 14:40
 */

namespace common\services;


use backend\models\search\ArticleSearch;
use common\libs\Constants;
use common\models\Article;
use common\models\ArticleContent;
use common\models\Comment;
use Yii;
use yii\base\Exception;
use yii\web\NotFoundHttpException;

class ArticleService extends Service implements ArticleServiceInterface
{

    public function getSearchModel(array $query, array $options = [])
    {
        return new ArticleSearch();
    }

    public function getModel($id, array $options = [])
    {
        $model = Article::findOne($id);
        if( isset( $options['scenario']) ){
            $model->setScenario( $options['scenario'] );
        }
        return $model;
    }

    public function newModel(array $options = [])
    {
        $type = Article::ARTICLE;
        isset($options['scenario']) && $type = $options['scenario'];
        $model = new Article(['scenario' => $type]);
        $model->loadDefaultValues();
        return $model;
    }

    public function newArticleContentModel(array $options= [])
    {
        return new ArticleContent();
    }

    public function getArticleContentDetail($id, array $options = [])
    {
        $model = ArticleContent::findOne(['aid'=>$id]);
        if( empty($model) ){
            throw new NotFoundHttpException("Id " . $id . " not exists");
        }
        return $model;
    }

    public function create(array $postData, array $options = [])
    {
        $articleModel = new Article(['scenario'=>$options['scenario']]);
        $articleContentModel = new ArticleContent();
        if( !$articleModel->load($postData) || !$articleContentModel->load($postData) ){
            return [
                'articleModel' => $articleModel,
                'articleContentModel' => $articleContentModel,
            ];
        }
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        try{
            if ( !$articleModel->save() ){
                throw new Exception("save article error");
            }

            $articleContentModel->setAttribute("aid", $articleModel->id);
            if( !$articleContentModel->save() ){
                throw new Exception("save article content error");
            }
            $transaction->commit();
        }catch (\Exception $exception){
            $transaction->rollBack();
            return [
                'articleModel' => $articleModel,
                'articleContentModel' => $articleContentModel,
            ];
        }
        return true;
    }

    public function update($id, array $postData, array $options = [])
    {
        $articleModel = $this->getDetail($id, $options);
        $articleContentModel = $this->getArticleContentDetail($id);
        if (!$articleModel->load($postData) || !$articleContentModel->load($postData)) {
            return [
                'articleModel' => $articleModel,
                'articleContentModel' => $articleContentModel,
            ];
        }
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            if (!$articleModel->save()) {
                throw new Exception("save article error");
            }

            if (!$articleContentModel->save()) {
                throw new Exception("save article content error");
            }
            $transaction->commit();
        } catch (Exception $exception) {
            $transaction->rollBack();
            return [
                'articleModel' => $articleModel,
                'articleContentModel' => $articleContentModel,
            ];
        }
        return true;
    }

    public function delete($id, array $options = [])
    {
        $articleModel = $this->getDetail($id, $options);
        $articleContentModel = $this->getArticleContentDetail($id);
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        try{
            if( $articleModel->delete() === false || $articleContentModel->delete() === false || !is_int( Comment::deleteAll(['aid'=>$id]) ) ){
                throw new \Exception("delete article failed");
            }
            $transaction->commit();
        }catch (\Exception $exception){
            $transaction->rollBack();
            return [
                'articleModel' => $articleModel,
                'articleContentModel' => $articleContentModel,
            ];
        };
        return true;
    }

    public function getFlagHeadLinesArticles($limit, $sort = SORT_DESC)
    {
        return Article::find()->limit($limit)->where(['flag_headline'=>Constants::YesNo_Yes])->limit($limit)->with('category')->orderBy(["sort"=>$sort])->all();
    }

    public function getArticleSubTitle($subTitle)
    {
        return Article::findOne(['type' => Article::SINGLE_PAGE, 'sub_title' => $subTitle]);
    }

    public function getArticleById($aid)
    {
        return Article::find()->where(['id'=>$aid, "status"=>Constants::YesNo_Yes, 'type'=>Article::ARTICLE])->one();
    }
}