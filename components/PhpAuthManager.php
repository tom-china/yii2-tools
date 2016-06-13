<?php
/**
 * PhpAuthManager class file.
 * @copyright (c) 2016, Pavel Bariev
 * @license http://www.opensource.org/licenses/bsd-license.php
 */

namespace bariew\yii2Tools\components;
use Yii;
use yii\base\Event;
use yii\rbac\Assignment;
use yii\web\Controller;
use yii\web\HttpException;

/**
 * Description.
 * Php rbac manager with url access support. It raises event for current web controller
 * and checks whether there is a rbac access permission named like <module>/<controller>/<action> for the current user.
 * It also supports regexps for permission names like <module>/\w+/\w+
 *
 * Usage: add to your config file components:
   'authManager'   => [
        'class' => 'bariew\yii2Tools\components\PhpAuthManager',
        'defaultRoles' => ['app/site/.*', 'user/default/.*', 'page/default/.*'], // everyone can access these urls (app is for base controllers)
    ],
 *
 * @author Pavel Bariev <bariew@yandex.ru>
 *
 */
class PhpAuthManager extends \yii\rbac\PhpManager
{
    /**
     * @inheritdoc
     */
    public $itemFile = '@app/rbac/items.php';
    /**
     * @inheritdoc
     */
    public $assignmentFile = '@app/rbac/assignments.php';
    /**
     * @inheritdoc
     */
    public $ruleFile = '@app/rbac/rules.php';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        Event::on(Controller::className(), 'beforeAction', [$this, 'beforeActionAccess']);
        if(Yii::$app->user->isGuest){
            return;
        }
        $this->assignments[Yii::$app->user->id]['default'] = new Assignment([
            'userId' => Yii::$app->user->id,
            'roleName' => 'default',
            'createdAt' => time(),
        ]);
    }

    /**
     * Checks whether current user has access to current controller action.
     * @param Event $event controller beforeAction event.
     * @throws \yii\web\HttpException
     */
    public function beforeActionAccess(Event $event)
    {
        $controller = $event->sender;
        if (!Yii::$app->user->can($controller->module->id.'/'.$controller->id.'/'.$controller->action->id)) {
            throw new HttpException(403, Yii::t('app/rbac', 'Access denied'));
        }
    }

    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $permissionName = preg_replace('#^\/(.*)#', '$1', $permissionName);
        foreach ($this->getPermissions() as $permission) {
            if ($permission->type == $permission::TYPE_ROLE) {
                continue;
            }
            if (!preg_match('#^'.$permission->name.'$#', $permissionName)) {
                continue;
            }
            if (parent::checkAccess($userId, $permission->name, $params)) {
                return true;
            }
        }
        return parent::checkAccess($userId, $permissionName, $params);
    }
}