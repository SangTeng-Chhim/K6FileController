<?php

namespace app\controllers;
use Da\QrCode\QrCode;
use app\models\gii\Attactment;
use app\models\gii\Notes;
use app\models\gii\CaseStatus;
use app\models\gii\CaseSource;
use app\models\gii\CaseCategories;
use app\models\search\ForensicEvidence as ForensicEvidenceSearch;
use Yii;
use aki\telegram\base\Command;
use app\models\gii\ForensicCase;
use app\models\search\ForensicCaseSearch;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;
use app\models\gii\ForensicEvidence;
/**
 * ForensicCaseController implements the CRUD actions for ForensicCase model.
 * @property Attactment $attachtment
 */
class ForensicCaseController extends Controller
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

        $model = new ForensicCase();
        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $model->load(Yii::$app->request->post());
            return array_merge(ActiveForm::validate($model));
        }

        $login = "reanyouda";
        $password = "IronHeart123";
        $target_directory = "web/uploads/";
        $ftp = new \yii2mod\ftp\FtpClient();
        $host = '172.16.15.168';
        $ftp->connect($host, false, 21);
        $ftp->login($login, $password);


      //  $source_directory = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads';

           //   $ftp->putAll($source_directory, $target_directory);

        $mbSize = ($ftp->dirSize($target_directory)/1024)/1024;
        $file_size = substr($mbSize,-15,4);

        $searchModel = new ForensicCaseSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $countData = ForensicCase::find()->count();
        $caseDone = ForensicCase::find()->where(['status_id' => 1])->count();
        $pending = ForensicCase::find()->where(['!=','status_id',  1])->count();
        $evidenceCount = ForensicEvidence::find()->count();
        return $this->render('index', [
            'model' => $model,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'countData' =>$countData,
            'caseDone' =>$caseDone,
            'pending' =>$pending,
            'evidenceCount' =>$evidenceCount,
            'file_size' => $file_size
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
        $model = $this->findModel($id);
            $login = "reanyouda";
            $password = "IronHeart123";
            $target_directory = "web/uploads/";
            $ftp = new \yii2mod\ftp\FtpClient();
            $host = '172.16.15.168';
            $ftp->connect($host, false, 21);
            $ftp->login($login, $password);

             $qrCode = (new QrCode($model->case_id))
            ->setSize(250)
            ->setMargin(5)
            ->useForegroundColor(51, 153, 255);

            $source_directory = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads';
            date_default_timezone_set('Asia/Phnom_Penh');
            $access = false;

            $accessname = $model->handle_by;
            $permssion = Yii::$app->user->can('admin') ;
            $username = Yii::$app->user->identity->username;
            if($permssion){
                $access = true;
            }
            if (is_array($accessname)) {
                foreach($accessname as $name) {
                   if($username == $name){
                       $access = true;
                   }
                }
            }elseif (  $username = $accessname){
                $access = true;
        }


        if($access){


            $attachmentModel = new Attactment();
            $forensic_evidence = new ForensicEvidence();
            $searchModel = new ForensicEvidenceSearch();
            $noteModel = new Notes();
            $note_data = $noteModel::find() ->where(['forensic_case_id' => $id])->all();

            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
            $dataProvider->query->andWhere([
                'forensic_case_id' => $id,
            ]);
            $forensic_evidence->forensic_case_id = $id;
            $noteModel->forensic_case_id = $id;
            $noteModel->note_time = date('Y/m/d H:i:s');
            $noteModel->user = Yii::$app->user->identity->username;

            if ($forensic_evidence->load(Yii::$app->request->post()) && $forensic_evidence->save()) {

                return $this->redirect(['view', 'id' => $id]);
            }
            if ($noteModel->load(Yii::$app->request->post()) && $noteModel->save()) {

                return $this->redirect(['view', 'id' => $id]);
            }

            //Upload

            $attachmentModel->forensic_case_id = $id;
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
                            //$path = '\\172.16.15.168\web' . $fakeName . "." . $attachment->extension;

                            if(!$attachment->saveAs($path)){
//                            print_r($path);
                                return false;
                            }
                            $ftp->put($target_directory.$fakeName.".".$attachment->extension,$source_directory .'/'. $fakeName.".".$attachment->extension,FTP_BINARY);
                        } catch (\Exception $exception) {
                            print_r($exception->getMessage());

                        }

                    }
                }

                if(count($item_array) >= 1) {
                    $attachmentModel->attactment = json_encode($item_array);
                }else{
                    return $this->redirect(['view', 'id' => $attachmentModel->forensic_case_id]);
                }
                try{
                    $attachmentModel->attachments = null;
                    if($attachmentModel->save())
                        return $this->redirect(['view', 'id' => $attachmentModel->forensic_case_id]);
                } catch (\Exception $exception) {
                    print_r('hello');
                    print_r($exception->getMessage());
                }
            }
            //End





            $caseAttachtments = Attactment::find()
                ->where([
                    'forensic_case_id' => $model->case_id
                ])->all();
            foreach ($caseAttachtments as $key=>$attachtment) {
                $data = json_decode($attachtment->attactment);


                foreach($data as $d) {
                    $type = "office";
                    if($d->extension == "jpg" || $d->extension == "png" || $d->extension == "gif") {
                        $type = "image";
                    }elseif ($d->extension == "pdf"){
                        $type = "pdf";
                    }


                    $initialPreview[] = "http://172.16.15.168/uploads/{$d->fake_name}";

                    $initialPreviewConfig[] = [
                        'type' => $type,

                        'caption' => $d->original_name,
                        'url' => Url::to(["delete-file", "id"=>$attachtment->attactment_id,"name"=> $d->fake_name]),
                        'downloadUrl' =>"http://172.16.15.168/uploads/{$d->fake_name}",

                        'key' =>  $key+1
                    ];
                }


            }

            return $this->render('view', [

                'model' => $model,
                'forensic_evidence'=> $forensic_evidence,
                'searchModel' => $searchModel,
                'noteModel' => $noteModel,
                'note_data' => $note_data,
                'dataProvider' => $dataProvider,
                'attachment' => $attachmentModel,
                'initialPreview' =>  $initialPreview,
                'initialPreviewConfig' => $initialPreviewConfig,
                'qr_code' =>$qrCode->writeDataUri(),



                ]);
        } throw new ForbiddenHttpException();

    }
    public function  actionDeleteFile ($id,$name) {



        $attachmentModel = Attactment::findOne($id);
        if($attachmentModel) {

            $data = json_decode($attachmentModel->attactment);

            $items = array_filter($data, function ($entry) use ($name) {
                if ($entry->fake_name != $name) {
                    return $entry;
                }
//                unlink(Yii::getAlias('@webroot') . '/uploads/' . $name);
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
     * Creates a new ForensicCase model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {

        $model = new ForensicCase();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
//            return $this->redirect(['view', 'id' => $model->case_id]);
            $source = CaseSource::find()
            ->where(['source_id' => $model->source_id])
            ->one();
            $case_type = CaseCategories::find()
            ->where(['category_id' => $model->category_id])
            ->one();
            Yii::$app->telegram->sendMessage([
                'chat_id' => '-1001535864057',
                'text' =>'
ករណីចូលថ្មី ថ្ងៃទី '.$model->case_date_in.'
ករណីឈ្មោះ ៖ '.$model->case_name.'
ប្រភេទករណី ៖ '.$case_type->category_name_kh.'
ប្រភពករណី ៖ '.$source->source_name_kh.'
ឈ្មោះអ្នកប្រគល់ ៖ '.$model->source_name.'
លេខទំនាក់ទំនង ៖ '.$model->source_phone.'
ទទួលដោយ ៖ '.$model->receive_by.'
                 ',
            ]); 
            return $this->redirect('index');
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

       
        $access = false;
        $model = $this->findModel($id);
//        $model->scenario = 'update';

      

        $accessname = $model->handle_by;
        $username = Yii::$app->user->identity->username ;
        $permssion = Yii::$app->user->identity->username ;
        if($permssion){
            $access = true;
        }
        if (is_array($accessname)) {
            foreach($accessname as $name) {
                if($username == $name){
                    $access = true;
                }
            }
        }elseif (  $username = $accessname){
            $access = true;
        }

        if($access){
            if ($model->load(Yii::$app->request->post()) &&  $model->validate()) {
                $status = CaseStatus::find()
                ->where(['status_id' => $model->status_id])
                ->one();
                $source = CaseSource::find()
                ->where(['source_id' => $model->source_id])
                ->one();

             $model->save();
                Yii::$app->telegram->sendMessage([
                    'chat_id' => '-1001535864057',
                    'text' =>'
🔴 មានការកែប្រែព័ត៌មានករណី 🔴
ករណីឈ្មោះ ៖ '.$model->case_name.'
ប្រភពករណី ៖ '.$source->source_name_kh.'
ស្ថានភាពករណី ៖ '.$status->status_name_kh.'
ថ្ងៃចេញ ៖ '.($model->case_date_out == null ? 'មិនបានកំណត់' : $model->case_date_out ).'
ឈ្មោះអ្នកចេញរបាយការណ៍ ៖ '.($model->release_by == null ? 'មិនបានកំណត់' : $model->release_by ).'
កែប្រែប្រព័ន្ធគ្រប់គ្រងដោយ ៖ '.Yii::$app->user->identity->username.'

                     ',
                ]); 

                  
               return $this->redirect(['view', 'id' => $model->case_id]);
            }

            return $this->render('update', [
                'model' => $model,
            ]);
        }
        throw new ForbiddenHttpException();
    }

    /**
     * Deletes an existing ForensicCase model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws ForbiddenHttpException
     */
    public function actionDelete($id)
    {
        if(Yii::$app->user->identity->username == "admin") {
            $model = $this->findModel($id);
            $feModel = ForensicEvidence::find()
                ->where([
                    "forensic_case_id" => $id
                ])->all();
            foreach ($feModel as $m) {
                $m->delete();
            }
            $model->delete();

            return $this->redirect(['index']);
        }
        throw new ForbiddenHttpException();

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
    public function actionReport()
    {
        if(Yii::$app->request->isAjax && Yii::$app->request->isPost) {   
            $date_start = Yii::$app->request->post('datetime_min');
            $date_end = Yii::$app->request->post('datetime_max');
            $model =  ForensicCase::find()
                ->where(['between','case_date_in',"$date_start","$date_end"]);
            $dataProvider = new ActiveDataProvider([
                'query' => $model,
                'pagination' => [
                    'pageSize' => 20,
                ],
            ]);

            return $this->renderAjax("partial/_report_table", ['dataProvider' => $dataProvider]);
        }
        return $this->render('report');
    }
    public function actionMain()
    {
//        $this->get_image('ftp://reanyouda:IronHeart123@172.16.15.168/ftp_upload/upload/','-REsTP95vwbqmnD93p4X9tZuRmQuygS4.png');

        $login = "reanyouda";
        $password = "IronHeart123";
        $target_directory = "web/uploads/";
        $ftp = new \yii2mod\ftp\FtpClient();
        $host = '172.16.15.168';
        $ftp->connect($host, true, 21);
        $ftp->login($login, $password);


        $source_directory = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'uploads';

 //        $ftp->putAll($source_directory, $target_directory);
        $file_size = $ftp->dirSize($target_directory);
        $mbSize = ($file_size/1024)/1024;
//        echo substr($mbSize,-15,4);
//        echo $total_file = $ftp->count($target_directory, 'file');
//        var_dump($ftp->help());



    }
    public function actionTelegram() {

     
         
    }

}
