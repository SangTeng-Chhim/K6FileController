<?php

namespace app\controllers;

use Yii;
use app\models\ForensicCase;
use app\models\ForensicCaseSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\gii\CaseCategory;

/**
 * CaseController implements the CRUD actions for ForensicCase model.
 */
class CaseController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all ForensicCase models.
     * @return mixed
     */
    public function actionIndex()
    {
        
        $category = CaseCategory::find()->where(['category_id' => 2])->one();
        $searchModel = new ForensicCaseSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $category_name = $category->category_name_en;

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'category' =>$category_name
        ]);
    }

    /**
     * Displays a single ForensicCase model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $CaseData = ForensicCase::find()->where(['case_id' => $id])->one();
        $CateID = $CaseData->category_id;

        $category = CaseCategory::find()->where(['category_id' => $CateID])->one();
        $category_name = $category->category_name_en;
        return $this->render('view', [
            'model' => $this->findModel($id),
            'category' =>$category_name,

        ]);
    }

    /**
     * Creates a new ForensicCase model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new ForensicCase();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->case_id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing ForensicCase model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->case_id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ForensicCase model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the ForensicCase model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ForensicCase the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ForensicCase::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
   

}
