<?php
/**
 * FixtureManager class file.
 * @copyright (c) 2015, Pavel Bariev
 * @license http://www.opensource.org/licenses/bsd-license.php
 */

namespace bariew\yii2Tools\tests;
use bariew\yii2Tools\helpers\MigrationHelper;
use yii\helpers\FileHelper;

/**
 * Manager for loading fixtures for tests.
 * Truncates tables and inserts test data from tests/fixtures/data folder
 * Puts new models into cache. Gets models from cache
 *
 * Usage:
 * FixtureManager::get('user_user', 'admin')
 *
 * @author Pavel Bariev <bariew@yandex.ru>
 *
 */
class FixtureManager
{
    public static $data;
    private static $cacheKey = 'test_fixtures';
    private static $fixturePath = '@app/tests/codeception/fixtures/data';
    private static $modelPath = '@app/modules';

    /**
     * @param array $config
     * @return FixtureManager
     */
    public static function instance($config = [])
    {
        foreach ($config as $attribute => $value) {
            static::$$attribute = $value;
        }
        static::reset();
        return new static();
    }

    public static function init()
    {
        if ($data = \Yii::$app->cache->get(static::$cacheKey)) {
            return static::$data = unserialize($data);
        }
        $dir = \Yii::getAlias(static::$fixturePath);
        $models = static::getModels();
        MigrationHelper::unsetForeignKeyCheck();
        $files = FileHelper::findFiles($dir, ['only' => ['*.php']]);
        asort($files);
        foreach ($files as $file) {
            $table = preg_replace('/\d+\_(.*)/', '$1', basename($file, '.php'));
            \Yii::$app->db->createCommand()->truncateTable($table)->execute();
            if (!$data = require $file) {
                continue;
            }
            foreach ($data as $key => $values) {
                \Yii::$app->db->createCommand()->insert($table, $values)->execute();
                if (isset($models[$table])) {
                    $class = $models[$table];
                    /** @var \yii\db\ActiveRecord $model */
                        $model = new $class($values);
                        static::$data[$table][$key] = $model->hasAttribute('id')
                            ? $model::find()->orderBy(['id' => SORT_DESC])->one()
                            : new $model;

                } else {
                    static::$data[$table][$key] = $values;
                }
            }
        }
        static::update();
        MigrationHelper::setForeignKeyCheck();
    }

    public static function get($table, $index = false)
    {
        if (static::$data === null) {
            static::init();
        }
        $data = static::$data[$table];
        return ($index === false) ? reset($data) : $data[$index];
    }

    public static function update()
    {
        \Yii::$app->cache->set(static::$cacheKey, serialize(static::$data));
    }

    public static function reset()
    {
        \Yii::$app->cache->delete(static::$cacheKey);
        static::$data = null;
        static::init();
    }

    private static function getModels()
    {
        $result = [];
        $files = FileHelper::findFiles(\Yii::getAlias(static::$modelPath), ['only' => ['*/models/*.php']]);
        asort($files);
        foreach ($files as $file) {
            /** @var \yii\db\ActiveRecord $class */
            $class = str_replace([\Yii::getAlias('@app'), '.php', '/'], ['\app', '', '\\'], $file);
            if (!(new \ReflectionClass($class))->hasMethod('tableName')) {
                continue;
            }
            $table = preg_replace('/\W/', '', $class::tableName());
            $result[$table] = isset($result[$table]) ? $result[$table] : $class;
        }
        return $result;
    }
}