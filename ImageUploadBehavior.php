<?php
/**
 * @author Pafnow
 *
 * Simply attach this behavior to your model, specify attribute and file path.
 * You can use placeholders in path configuration:
 *
 * public
 * function behaviors()
 * {
 *     return [
 *         'image-upload' => [
 *              'class' => '\lagman\upload\ImageUploadBehavior',
 *              'attribute' => 'imageUpload',
 *              'thumbs' => [
 *                  'thumb' => ['width' => 400, 'height' => 300],
 *              ],
 *              'filePath' => '[[web_root]]/images/[[model]]/[[id]].[[extension]]',
 *              'fileUrl' => '/images/[[model]]/[[id]].[[extension]]',
 *         ],
 *     ];
 * }
 */

namespace lagman\upload;

use PHPThumb\GD;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Class ImageUploadBehavior
 */
class ImageUploadBehavior extends FileUploadBehavior
{
    public $attribute = 'image';

    public $createThumbsOnSave = true;
    public $createThumbsOnRequest = false;

    /** @var array Thumbnail profiles, array of [width, height] */
    public $thumbs = [
        'thumb' => ['width' => 200, 'height' => 150],
    ];
    
    public $filePath = '/images/[[model]]/[[attribute]]/';

    /**
     * @inheritdoc
     */
    public function events()
    {
        return ArrayHelper::merge(parent::events(), [
            static::EVENT_AFTER_FILE_SAVE => 'afterFileSave',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function cleanFiles()
    {
        parent::cleanFiles();
        foreach (array_keys($this->thumbs) as $profile) {
            @unlink(Yii::getAlias('@webroot') .DIRECTORY_SEPARATOR. $this->resolveThumbPath($profile));
        }
    }
    
    /**
     * Resolves thumbnail path.
     *
     * @param string $profile
     * @return string
     */
    public function resolveThumbPath($profile)
    {
        return preg_replace('#(.*)/(.*)$#','$1/'.$profile.'/$2', $this->resolvePath());
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @return string
     */
    public function getThumbFileUrl($attribute, $profile = 'thumb')
    {
        $behavior = static::getInstance($this->owner, $attribute);
        if ($behavior->createThumbsOnRequest)
            $behavior->createThumbs();
        return $behavior->resolveThumbPath($profile);
    }

    /**
     * After file save event handler.
     */
    public function afterFileSave()
    {
        if ($this->createThumbsOnSave == true)
            $this->createThumbs();
    }

    public function createThumbs()
    {
        $path = Yii::getAlias('@webroot') .DIRECTORY_SEPARATOR. $this->resolvePath();
        foreach ($this->thumbs as $profile => $config) {
            $thumbPath = Yii::getAlias('@webroot') .DIRECTORY_SEPARATOR. $this->resolveThumbPath($profile);
            if (!is_file($thumbPath)) {
                /** @var GD $thumb */
                $thumb = new GD($path);
                if (isset($config['resizeUp']))
                    $thumb->setOptions(array('resizeUp'=>$config['resizeUp']));
                $thumb->adaptiveResize($config['width'], $config['height']);
                @mkdir(pathinfo($thumbPath, PATHINFO_DIRNAME), 777, true);
                $thumb->save($thumbPath);
            }
        }
    }

}