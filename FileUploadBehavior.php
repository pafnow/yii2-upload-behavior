<?php
/**
 * @author Pafnow
 *
 * Simply attach this behavior to your model, specify attribute and file path.
 * You can use placeholders in path configuration:
 *
 * Usage example:
 *
 * public
 * function behaviors()
 * {
 *     return [
 *         'file-upload' => [
 *             'class' => '\lagman\upload\FileUploadBehavior',
 *             'attribute' => 'fileUpload',
 *             'filePath' => '[[web_root]]/uploads/[[id]].[[extension]]',
 *             'fileUrl' => '/uploads/[[id]].[[extension]]',
 *         ],
 *     ];
 * }
 */
namespace lagman\upload;

use Yii;
use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\db\ActiveRecord;
use yii\helpers\VarDumper;
use yii\web\UploadedFile;

/**
 * Class FileUploadBehavior
 *
 * @property ActiveRecord $owner
 */
class FileUploadBehavior extends \yii\base\Behavior
{
    const EVENT_AFTER_FILE_SAVE = 'afterFileSave';

    /** @var string Name of attribute which holds the attachment. */
    public $attribute = 'upload';
    /** @var string Name template to use for storing files. */
    public $fileName = "{id}";
    /** @var string Path template to use for storing files. */
    public $filePath = '/uploads/[[model]]/[[attribute]]/';
    /** @var string Where to store images. */
    public $fileUrl = '/uploads/[[id]].[[extension]]';
    /** @var string Attribute used to link owner model with it's parent */
    public $parentRelationAttribute;
    /** @var \yii\web\UploadedFile */
    protected $file;
    
    
    private $isEventsActif = true;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return($this->isEventsActif) ? [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ] : [];
    }

    /**
     * Before validate event.
     */
    public function beforeValidate()
    {
    	if ($this->isEventsActif) {
	        $this->file = UploadedFile::getInstance($this->owner, $this->attribute);
	
	        if ($this->file instanceof UploadedFile) {
	            $this->owner->{$this->attribute} = $this->file;
	        }
	        else if (!$this->owner->isNewRecord) {
	            $oldModel = $this->owner->findOne($this->owner->primaryKey);
	            $this->owner->{$this->attribute} = $oldModel->{$this->attribute};
	        }
    	}
    }

    /**
     * Before save event.
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function beforeSave()
    {
    	if ($this->isEventsActif) {
	        if ($this->file instanceof UploadedFile) {
	            if (!$this->owner->isNewRecord) {
	                /** @var static $oldModel */
	                $oldModel = $this->owner->findOne($this->owner->primaryKey);
	                $oldModel->cleanFiles();
	            }
	            $this->owner->{$this->attribute} = $this->file->baseName . '.' . $this->file->extension;
	        }
    	}
    }

    /**
     * Removes files associated with attribute
     */
    protected function cleanFiles()
    {
        if (!empty($this->owner->{$this->attribute}))
        {
            $path = Yii::getAlias('@webroot') .DIRECTORY_SEPARATOR. $this->resolvePath();
            @unlink($path);
        }
    }

	/**
     * Replaces all placeholders in filename variable with corresponding values
     *
     * @param string $filename
     * @return string
     */
    public function resolveFileName()
    {
    	$attributes = array_combine(array_map(function($k){ return '{'.$k.'}'; }, array_keys($this->owner->attributes))
    							   ,array_map(function($v){ return preg_replace('/^-+|-+$/', '', strtolower(preg_replace('/[^a-zA-Z0-9\-]+/', '-', $v))); }, $this->owner->attributes));
        return strtr($this->fileName, $attributes);
    }

    /**
     * Replaces all placeholders in path variable with corresponding values
     *
     * @param string $path
     * @return string
     */
    public function resolvePath()
    {
        $path = Yii::getAlias($this->filePath);

        $r = new \ReflectionClass($this->owner->className());
        $path = str_replace('[[model]]', lcfirst($r->getShortName()), $path);
        $path = str_replace('[[attribute]]', lcfirst($this->attribute), $path);

        $pi = pathinfo($this->owner->{$this->attribute});
        return $path .DIRECTORY_SEPARATOR. $this->resolveFileName().".".strtolower($pi['extension']);
    }

    /**
     * After save event.
     */
    public function afterSave()
    {
    	if ($this->isEventsActif)
    	{
	        if ($this->file instanceof UploadedFile) {
	            $path = Yii::getAlias('@webroot') .DIRECTORY_SEPARATOR. $this->resolvePath();
	            @mkdir(pathinfo($path, PATHINFO_DIRNAME), 777, true);
	            if (!$this->file->saveAs($path)) {
	                throw new Exception('File saving error.');
	            }
	            
	         	$this->isEventsActif = false;
	         	$this->owner->{$this->attribute} = \yii\helpers\FileHelper::normalizePath($this->resolvePath());
	            $this->owner->save();
	            $this->isEventsActif = true;

	            $this->owner->trigger(static::EVENT_AFTER_FILE_SAVE);
	        }
    	}
    }

    /**
     * Returns behavior instance for specified class and attribute
     *
     * @param ActiveRecord $model
     * @param string $attribute
     * @return static
     */
    public static function getInstance(ActiveRecord $model, $attribute)
    {
        foreach ($model->behaviors as $behavior) {
            if ($behavior instanceof static && $behavior->attribute == $attribute)
                return $behavior;
        }

        throw new InvalidCallException('Missing behavior for attribute ' . VarDumper::dumpAsString($attribute));
    }

    /**
     * Before delete event.
     */
    public function beforeDelete()
    {
        $this->cleanFiles();
    }

    /**
     * Returns file url for the attribute.
     *
     * @param string $attribute
     * @return string
     */
    public function getUploadedFileUrl($attribute)
    {
        $behavior = static::getInstance($this->owner, $attribute);
        return $behavior->resolvePath();
    }
}
