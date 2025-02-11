<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\widgets\ActiveForm;
use app\forms\ConferenceForm;
use app\services\conference\ConferenceService;
use app\entities\Conference;
use app\entities\ConferenceSearch;
use app\entities\ConferenceWishlist;
use app\entities\ConferenceParticipant;
use app\entities\ConferenceParticipantSearch;
use app\entities\Certificate;
use app\entities\User;

/**
 * ConferenceController implements the CRUD actions for Conference model.
 */
class ConferenceController extends Controller
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
                    'close' => ['POST']
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index', 'delete', 'delete-participant'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [User::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all Conference
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ConferenceSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Conference model.
     *
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * @return string
     */
    public function actionCurrent ()
    {
        $model = ConferenceService::conferenceCurrent();

        return $this->render('current',[
            'model' => $model,
        ]);
    }

    /**
     * Create new conference
     *
     * @param null $id
     * @throws \yii\base\InvalidConfigException
     * @return string
     */
    public function actionCreateForm($id = null)
    {
        $form = new ConferenceForm($id);

        return $this->renderAjax('create', [
            'model' => $form,
        ]);
    }

    /**
     * Conference update
     *
     * @param integer $id
     * @throws \yii\base\InvalidConfigException
     * @return mixed
     */
    public function actionUpdateForm($id)
    {
        $form = new ConferenceForm($id);

        return $this->renderAjax('update', [
            'model' => $form,
        ]);
    }

    /**
     * Conference form validate
     *
     * @throws \yii\base\InvalidConfigException
     * @return array
     */
    public function actionFormValidate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $form = new ConferenceForm();

        $form->load(Yii::$app->request->post());

        return ActiveForm::validate($form);
    }

    /**
     * Conference create
     *
     * @param $id null
     * @throws \yii\base\InvalidConfigException
     * @return void
     */
    public function actionCreate($id = null)
    {
        $form = new ConferenceForm($id);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            if (ConferenceService::conferenceCreate($form)) {
                Yii::$app->session->setFlash('success', 'Событие успешно зарегистрировано в системе!');
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка! Событие не зарегистрировано. Обратитесь к администратору системы.');
            }

            $this->redirect(
                '/site/index'
            );
        }
    }

    /**
     * Conference update
     *
     * @param integer $id
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdate($id)
    {
        $form = new ConferenceForm($id);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            if (ConferenceService::conferenceUpdate($form, Conference::findOne($id))) {
                Yii::$app->session->setFlash('success', 'Событие успешно обновлено!');
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка! Событие не обновлено. Обратитесь к администратору системы.');
            }

            $this->redirect(
                '/conference/index'
            );
        }
    }

    /**
     * Deletes an existing Conference model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     * @throws \Exception
     * @throws \Throwable
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->updateAttributes(['deleted' => 1]);

        return $this->redirect([
            '/conference/index'
        ]);
    }

    /**
     * @param $id
     * @return Response
     */
    public  function actionClose($id)
    {
        $conference = Conference::findOne($id);

        $conference->updateAttributes(['end_time' => time()]);

        Yii::$app->session->setFlash('success', 'Конференция закрыта!');

        return $this->redirect([
            '/site/index'
        ]);
    }

    /**
     * @param $id
     * @return string
     */
    public function actionParticipant($id)
    {
        $searchModel = new ConferenceParticipantSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $id);

        return $this->renderAjax('participants', [
            'dataProvider' => $dataProvider,
            'searchModel'  => $searchModel,
            'conference'   => Conference::findOne($id),
        ]);
    }

    /**
     * @param $user_id
     * @param $conference_id
     * @return string
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDeleteParticipant($user_id, $conference_id)
    {
        $participant = ConferenceParticipant::findOne([
            'user_id' => $user_id,
            'conference_id' => $conference_id,
        ]);

        if ($participant->method == Conference::LEARNING_FULL_TIME) {
            $certificate = Certificate::findOne([
                'user_id' => $user_id,
                'conference_id' => $conference_id,
            ]);

            $certificate->delete();
        }

        if ($participant->delete()) {
            Yii::$app->session->setFlash('success', 'Пользователь удален с конференции!');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка! Пользователь не удален с конференции. Обратитесь к администратору системы.');
        }

        return $this->actionParticipant($conference_id);
    }

    /**
     * @param $id
     */
    public function actionAddToWishList($id)
    {
        if (ConferenceWishlist::findOne(['user_id' => Yii::$app->user->id, 'conference_id' => $id])) return;

        $wishList = new ConferenceWishlist();

        $wishList->user_id = Yii::$app->user->id;
        $wishList->conference_id = $id;

        if ($wishList->save()) {
            Yii::$app->session->setFlash('success','Конференция добавлена в избранное!');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка! Конференция не добавлена в избранное. Обратитесь к администратору системы.');
        }
    }

    /**
     * @param $id
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDeleteFromWishList($id)
    {
        $wishList = ConferenceWishlist::findOne([
            'user_id' => Yii::$app->user->id,
            'conference_id' => $id,
        ]);

        if (!$wishList) return;

        if ($wishList->delete()) {
            Yii::$app->session->setFlash('success', 'Конференция удалена из избранного!');
        } else {
            Yii::$app->session->setFlash('error', 'Ошибка! Конференция не удалена из избранного. Обратитесь к администратору системы.');
        };
    }

    /**
     * Finds the Conference model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Conference the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Conference::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
