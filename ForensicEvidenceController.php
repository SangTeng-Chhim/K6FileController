<?php

namespace app\controllers;

use app\models\gii\Attactment;
use app\models\search\ForensicDataSearch;
use Yii;
use app\models\gii\ForensicEvidence;
use app\models\search\ForensicEvidence as ForensicEvidenceSearch;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\gii\ForensicData;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;

/**
 * ForensicEvidenceController implements the CRUD actions for ForensicEvidence model.
 */
class ForensicEvidenceController extends Controller
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
     * Lists all ForensicEvidence models.
     * @return mixed
     */
    public function actionIndex()
    {

        $searchModel = new ForensicEvidenceSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $model->load(Yii::$app->request->post());
            return array_merge(ActiveForm::validate($model));
        }

        return $this->render('index', [
            'searchModel' => $searchModel,

            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ForensicEvidence model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $login = "reanyouda";
        $password = "IronHeart123";
        $target_directory = "web/uploads/";
        $ftp = new \yii2mod\ftp\FtpClient();
        $host = '172.16.15.168';
        $ftp->connect($host, false, 21);
        $ftp->login($login, $password);

        $source_directory = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads';
        $model = $this->findModel($id);
        $attachmentModel = new Attactment();
        $forensic_data = new ForensicData();
        $searchModel = new ForensicDataSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->andWhere([
            'forensic_evidence_id' => $id,
        ]);

        $forensic_data->forensic_evidence_id = $id;

        if ($forensic_data->load(Yii::$app->request->post()) && $forensic_data->save()) {
            return $this->redirect(['view', 'id' => $id]);
        }

        //Upload
        $attachmentModel->forensic_case_id = $model->forensic_case_id;
        $attachmentModel->is_private = 1;
        $attachmentModel->evidence_id = $id;
        $initialPreview = [];
        $initialPreviewConfig = [];
        if ($attachmentModel->load(Yii::$app->request->post()) ) {
            $item_array = [];
            $attachmentModel->attachments = UploadedFile::getInstances($attachmentModel, 'attachments');
            if($attachmentModel->attachments) {
                foreach ($attachmentModel->attachments as $attachment) {
                    try {
                        $fakeName = Yii::$app->security->generateRandomString(32);
                        date_default_timezone_set('Asia/Phnom_Penh');
                        $item_array[] = [
                            'fake_name' => $fakeName . "." . $attachment->extension,
                            'original_name' => $attachment->baseName . "." . $attachment->extension,
                            'extension' => $attachment->extension,
                            'date'=> date('Y-m-d H:i:s')
                        ];

                        $path = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fakeName . "." . $attachment->extension;

                        if(!$attachment->saveAs($path)){
//                            print_r($path);
                            return false;
                        }
                        $ftp->put($target_directory.$fakeName.".".$attachment->extension,$source_directory .'/'. $fakeName.".".$attachment->extension,FTP_BINARY);
                    } catch (\Exception $exception) {
                        print_r($exception->getMessage());
                        return $this->render('view', [
                            'model' => $attachmentModel,
                        ]);
                    }
                }
            }

            if(count($item_array) >= 1) {
                $attachmentModel->attactment = json_encode($item_array);
            }else{
                return $this->redirect(['forensic-evidence/view', 'id' => $id]);
            }
            try{
                $attachmentModel->attachments = null;
                if($attachmentModel->save())
                    return $this->redirect(['forensic-evidence/view', 'id' => $id]);
            } catch (\Exception $exception) {
                print_r('hello');
                print_r($exception->getMessage());
            }
        }

        $caseAttachtments = Attactment::find()
            ->where([
                'forensic_case_id' => $model->forensic_case_id,
                'evidence_id' => $id,
                'is_private' => 1
            ])->all();
        foreach ($caseAttachtments as $key=>$attachtment) {
            $data = json_decode($attachtment->attactment);

            foreach ($data as $d) {
                $type = "office";
                if ($d->extension == "jpg" || $d->extension == "png" || $d->extension == "gif") {
                    $type = "image";
                } elseif ($d->extension == "pdf") {
                    $type = "pdf";
                }
                $initialPreview[] = "http://172.16.15.168/uploads/{$d->fake_name}";
                $initialPreviewConfig[] = [
                    'type' => $type,

                    'caption' => $d->original_name,
                    'url' => Url::to(["delete-file", "id" => $attachtment->attactment_id, "name" => $d->fake_name]),
                    'downloadUrl' => "http://172.16.15.168/uploads/{$d->fake_name}",

                    'key' => $key + 1
                ];
            }
        }

        return $this->render('view', [
            'model' => $model,
            'forensic_evidence_id' => $id,
            'forensic_data' => $forensic_data,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'attachment' => $attachmentModel,
            'initialPreview' =>  $initialPreview,
            'initialPreviewConfig' => $initialPreviewConfig,
        ]);
    }
    public function  actionDeleteFile ($id,$name) {
        $attachmentModel = Attactment::findOne($id);
        if($attachmentModel) {

            $data = json_decode($attachmentModel->attactment);

            $items = array_filter($data, function ($entry) use ($name) {
                if ($entry->fake_name != $name) {
                    return $entry;
                }
                //unlink(Yii::getAlias('@webroot') . '/uploads/' . $name);
                $login = "reanyouda";
                $password = "IronHeart123";
                $target_directory = "web/uploads/";
                $ftp = new \yii2mod\ftp\FtpClient();
                $host = '172.16.15.168';
                $ftp->connect($host, false, 21);
                $ftp->login($login, $password);
                $ftp->delete($target_directory.$name);

            });
            if (count($items) > 0 ) {

                $data = json_encode(array_values($items),true);
                $attachmentModel->attactment = $data;
                $attachmentModel->save();

            }elseif (count($items) <= 0){
                $attachmentModel->delete();
            }

            return json_encode($items);

        }
    }
    /**
     * Creates a new ForensicEvidence model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new ForensicEvidence();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing ForensicEvidence model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->evidence_id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ForensicEvidence model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $forensic_id = $model->forensic_case_id;
        $model->delete();

        return $this->redirect(['forensic-case/view', 'id' => $forensic_id]);
    }

    /**
     * Finds the ForensicEvidence model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ForensicEvidence the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ForensicEvidence::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
